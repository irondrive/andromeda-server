<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\DBStats; // phpstan
use Andromeda\Core\Errors\BaseExceptions\ClientException;
use Andromeda\Core\IOFormat\{Input,Output};
use Andromeda\Core\IOFormat\Interfaces\HTTP;
use Andromeda\Core\Logging\{ActionLog, RequestLog};

/**
 * The main runner class that loads apps, runs actions, and handles the transaction
 * 
 * A commit may follow a rollback only if it is saving alwaysSave fields or the request log.
 */
class AppRunner extends BaseRunner
{
    private ApiPackage $apipack;

    /** 
     * apps indexed by name 
     * @var array<string,BaseApp>
     */
    private array $apps = array(); 

    /** Optional request log for this request */
    private ?RequestLog $requestlog = null;

    /**
     * Gets an array of instantiated apps
     * @return array<string, BaseApp>
     */
    public function GetApps() : array { return $this->apps; }

    /** Returns the request log entry for this request */
    public function GetRequestLog() : ?RequestLog { return $this->requestlog; }
    
    /** Creates the AppRunner service, loading/constructing all apps */
    public function __construct(ApiPackage $apipack)
    {
        parent::__construct();
        $this->apipack = $apipack;

        $apps = array();
        
        if (is_file(ROOT."/Apps/Core/CoreApp.php"))
            $apps[] = 'core'; // always enabled if present
        
        $config = $apipack->GetConfig();
        
        $apps = array_merge($apps, $config->GetApps());

        foreach ($apps as $app) $this->TryLoadApp($app);
        
        if (!$config->isReadOnly() &&
            $config->GetEnableRequestLog())
        {
            $this->requestlog = RequestLog::Create($apipack);
        }
        
        $apipack->GetErrorManager()->SetRunner($this);
    }
    
    /** Loads the main include file for an app and constructs it */
    protected function TryLoadApp(string $app) : bool
    {
        $app = strtolower($app);
        
        $uapp = Utilities::FirstUpper($app);
        $path = ROOT."/Apps/$uapp/$uapp"."App.php";
        $class = "Andromeda\\Apps\\$uapp\\$uapp".'App';
        
        if (is_file($path)) require_once($path); else return false;
        
        if (!class_exists($class) || !is_a($class, BaseApp::class, true)) return false;
            
        if (!array_key_exists($app, $this->apps))
            $this->apps[$app] = new $class($this->apipack);
            
        return true;
    }
    
    /** Loads the main include file for an app and constructs it */
    public function LoadApp(string $app) : self
    {
        if (!$this->TryLoadApp($app))
            throw new Exceptions\FailedAppLoadException($app);
        return $this;
    }
    
    /** Unloads the given app (no error if not loaded) */
    public function UnloadApp(string $app) : self
    {
        unset($this->apps[$app]); return $this;
    }

    /**
     * Calls into an app to Run() the given Input command, saves all objects,
     * commits, finally and writes output to the interface
     * 
     * NOTE this function is NOT re-entrant - do NOT call it from apps!
     * @param Input $input the user input command to run
     * @throws Exceptions\UnknownAppException if the requested app is invalid
     */
    public function Run(Input $input) : void
    {
        $appname = $input->GetApp();
        if (!array_key_exists($appname, $this->apps)) 
            throw new Exceptions\UnknownAppException($appname);

        $app = $this->apps[$appname];
        $db = $this->apipack->GetDatabase();
        $innerDb = $db->GetInternal();
        
        $actionlog = null; if ($this->requestlog !== null 
                            && $app->getLogClass() === null)
        {
            $actionlog = $this->requestlog->LogAction($input, ActionLog::class);
        }
        
        $this->context = $context = new RunContext($input, $actionlog);

        if (($doMetrics = $this->apipack->GetMetricsLevel() > 0))
            $context->SetActionMetrics($innerDb->startStatsContext());

        $retval = $app->Run($input);
        $db->SaveObjects();

        if ($doMetrics)
        {
            $innerDb->stopStatsContext(); // action
            $commitStats = new DBStats();
            $this->timedCommit($commitStats);
            $context->SetCommitMetrics($commitStats);
        }
        else $this->commit();

        $interface = $this->apipack->GetInterface();
        $output = Output::Success($retval);
        
        if ($interface->UserOutput($output))
        {
            if (!$doMetrics) $this->commit();
            else $this->timedCommit($commitStats);
        }

        $this->apipack->GetMetricsHandler()
            ->SaveMetrics($this->apipack, $context, $output);

        $interface->FinalOutput($output);
        $this->context = null;
    }

    /**
     * Calls into a remote API to run the given Input command
     * 
     * Note that this breaks transactions - the remote API will 
     * commit before we get the response to this remote call.
     * @param string $url the base URL of the remote API
     * @see AppRunner::Run()
     * @return mixed
     */
    public function RunRemote(string $url, Input $input)
    {
        $data = HTTP::RemoteRequest($url, $input);

        return Output::ParseArray($data)->GetAppdata();
    }

    /**
     * Rolls back the current transaction
     * 
     * First rolls back each app, then the database, then saves alwaysSave objects if not a server error
     * @param ?\Throwable $e the exception that caused the rollback (or null)
     */
    public function rollback(?\Throwable $e = null) : void
    {
        Utilities::RunNoTimeout(function()use($e)
        {
            $db = $this->apipack->GetDatabase();
            $errman = $this->apipack->GetErrorManager();
            
            foreach ($this->apps as $app) $errman->LoggedTry(
                function()use($app) { $app->rollback(); });
            
            $db->rollback();
            
            if ($e instanceof ClientException) 
                $errman->LoggedTry(function()use($e,$db)
            {
                if ($this->requestlog !== null)
                    $this->requestlog->SetError($e);
                    
                $db->SaveObjects(true); // any "always" DB fields
                $this->doCommit(false);
                
                if ($this->requestlog !== null)
                    $this->requestlog->WriteFile();
            });
        });
    }

    /** Performs a commit, timing stats and adding to $stats if not null */
    protected function timedCommit(DBStats $stats) : void
    {
        $db = $this->apipack->GetDatabase()->GetInternal();
        $commitStats = $db->startStatsContext();

        $this->commit();

        $commitStats->stopTiming();
        $stats->Add($commitStats, true);
    }
    
    /**
     * Commits the current transaction
     * 
     * First commits each app, then the database.  Does a rollback 
     * instead if the request was specified as a dry run.
     */
    protected function commit() : void
    {
        Utilities::RunNoTimeout(function()
        {
            $db = $this->apipack->GetDatabase();
            $db->SaveObjects();
            $this->doCommit(true);
            
            if ($this->requestlog !== null) 
                $this->requestlog->WriteFile();
        });
    }
    
    /**
     * Commits the database or does a rollback if readOnly/dryrun
     * @param bool $apps if true, commit/rollback apps also
     */
    private function doCommit(bool $apps) : void
    {
        $db = $this->apipack->GetDatabase();
        $rollback = $this->apipack->isCommitRollback();
        
        if ($apps) foreach ($this->apps as $app) 
            if ($rollback) $app->rollback(); else $app->commit();

        if ($rollback) $db->rollback(); else $db->commit();
    }
}
