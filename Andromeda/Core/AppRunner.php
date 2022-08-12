<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Exceptions.php");
require_once(ROOT."/Core/RunContext.php");
require_once(ROOT."/Core/ApiPackage.php");

require_once(ROOT."/Core/Database/DBStats.php");
use Andromeda\Core\Database\DBStats; // phpstan

require_once(ROOT."/Core/Exceptions/BaseExceptions.php");
use Andromeda\Core\Exceptions\ClientException;

require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/Output.php");
require_once(ROOT."/Core/IOFormat/Interfaces/HTTP.php");
use Andromeda\Core\IOFormat\{Input,Output};
use Andromeda\Core\IOFormat\Interfaces\HTTP;

require_once(ROOT."/Core/Logging/ActionLog.php");
use Andromeda\Core\Logging\ActionLog;

/**
 * The main class that creates and runs apps
 * 
 * AppRunner is also a Singleton so it can be fetched anywhere with GetInstance().
 * An AppRunner handles a single request, with an arbitrary number of appruns.
 * 
 * The Andromeda transaction model is that there is always a global commit
 * or rollback at the end of the request. A rollback may follow a bad commit.
 * There will NEVER be a rollback followed by a commit. There may be > 1 commit.
 */
final class AppRunner extends Singleton
{
    private ApiPackage $apipack;

    /** @var array<string,BaseApp> apps indexed by name */
    private array $apps = array(); 
    
    /** @var RunContext[] stack frames for nested Run() calls */
    private array $stack = array(); 

    /** @var bool true if Run() has been called since the last commit or rollback */
    private bool $dirty = false;
    
    /** @var array<RunContext> action/context history */
    private array $action_history = array();
    
    /** @var array<DBStats> commit stats */
    private array $commit_stats = array();
    
    /**
     * Gets an array of instantiated apps
     * @return array<string, BaseApp>
     */
    public function GetApps() : array { return $this->apps; }

    /** Returns the RunContext that is currently being executed */
    public function GetContext() : ?RunContext { return Utilities::array_last($this->stack); }
    
    /** @return array<RunContext> action/context history */
    public function GetActionHistory() : array { return $this->action_history; }
    
    /** @return array<DBStats> commit stats */
    public function GetCommitStats() : array { return $this->commit_stats; }

    /** Creates the AppRunner service, loading/constructing all apps */
    public function __construct(ApiPackage $apipack)
    {
        parent::__construct();
        $this->apipack = $apipack;

        $apps = array();
        
        if (file_exists(ROOT."/Apps/Core/CoreApp.php"))
            $apps[] = 'core'; // always enabled if present
        
        $config = $apipack->TryGetConfig();
        if ($config !== null && $config->getVersion() === andromeda_version)
            $apps = array_merge($apps, $config->GetApps());

        foreach ($apps as $app) $this->TryLoadApp($app);

        register_shutdown_function(function(){
            if ($this->dirty) $this->rollback(); });
    }
    
    /** Loads the main include file for an app and constructs it */
    protected function TryLoadApp(string $app) : bool
    {
        $app = strtolower($app);
        
        $uapp = Utilities::FirstUpper($app);
        $path = ROOT."/Apps/$uapp/$uapp"."App.php";
        $app_class = "Andromeda\\Apps\\$uapp\\$uapp".'App';
        
        if (is_file($path)) require_once($path); else return false;
        
        if (!class_exists($app_class)) return false;
            
        if (!array_key_exists($app, $this->apps))
            $this->apps[$app] = new $app_class($this->apipack);
            
        return true;
    }
    
    /** Loads the main include file for an app and constructs it */
    public function LoadApp(string $app) : self
    {
        if (!$this->TryLoadApp($app))
            throw new FailedAppLoadException($app);
        return $this;
    }

    /**
     * Calls into an app to run the given Input command
     * 
     * Calls Run() on the requested app and then saves (but does not commit) 
     * any modified objects. These calls can be nested - apps can call Run for 
     * other apps but should always do so via the API, not directly to the app
     * @param Input $input the command to run
     * @throws UnknownAppException if the requested app is invalid
     */
    public function Run(Input $input)
    {        
        $app = $input->GetApp();
        
        if (!array_key_exists($app, $this->apps)) 
            throw new UnknownAppException();

        if ($this->apipack->GetMetricsLevel())
            $this->apipack->GetDatabase()->GetInternal()->pushStatsContext();

        $logclass = $this->apps[$app]::getLogClass();
        
        $actionlog = null; $reqlog = $this->apipack->GetRequestLog();
        if ($logclass !== null && $reqlog !== null) 
            $actionlog = $reqlog->LogAction($input, ActionLog::class);

        $context = new RunContext($input, $actionlog);
        $this->stack[] = $context; 
        $this->dirty = true;
        
        $retval = $this->apps[$app]->Run($input);
        
        if ($this->apipack->HasDatabase()) 
            $this->apipack->GetDatabase()->saveObjects();
        
        array_pop($this->stack);

        if ($this->apipack->GetMetricsLevel())
        {
            $stats = $this->apipack->GetDatabase()->GetInternal()->popStatsContext();
            
            $context->SetMetrics($stats);
            $this->action_history[] = $context;
        }
        
        return $retval;
    }

    /**
     * Calls into a remote API to run the given Input command
     * 
     * Note that this breaks transactions - the remote API will 
     * commit before we get the response to this remote call.
     * @param string $url the base URL of the remote API
     * @see AppRunner::Run()
     */
    public function RunRemote(string $url, Input $input)
    {
        $data = HTTP::RemoteRequest($url, $input);

        return Output::ParseArray($data)->GetAppdata();
    }

    /**
     * Rolls back the current transaction. Internal only, do not call via apps.
     * 
     * First rolls back each app, then the database, then saves mandatorySave objects if not a server error
     * @param ?\Throwable $e the exception that caused the rollback (or null)
     */
    public function rollback(?\Throwable $e = null) : void
    {
        Utilities::RunNoTimeout(function()use($e)
        {
            $errman = $this->apipack->GetErrorManager();
            
            foreach ($this->apps as $app) $errman->LoggedTry(
                function()use($app) { $app->rollback(); });

            if ($this->apipack->HasDatabase()) 
            {
                $db = $this->apipack->GetDatabase();
                
                $db->rollback();
                
                if ($e instanceof ClientException) 
                    $errman->LoggedTry(function()use($e,$db)
                {
                    $reqlog = $this->apipack->GetRequestLog();
                    if ($reqlog !== null) $reqlog->SetError($e);
                        
                    $db->saveObjects(true); // save log + any "always" DB fields
                    $this->commitDatabase(false);
                    
                    if ($reqlog !== null) $reqlog->WriteFile();
                });
            }
            
            $this->dirty = false;
        });
    }
    
    /**
     * Commits the current transaction. Internal only, do not call via apps.
     * 
     * First commits each app, then the database.  Does a rollback 
     * instead if the request was specified as a dry run.
     * @param bool $main true if this is the main index.php commit
     */
    public function commit(bool $main = false) : void
    {
        Utilities::RunNoTimeout(function()use($main)
        {
            if ($this->apipack->HasDatabase())
            {
                $db = $this->apipack->GetDatabase();
                
                if ($this->apipack->GetMetricsLevel())
                    $db->GetInternal()->pushStatsContext();
                
                $db->saveObjects();
                
                $reqlog = $this->apipack->GetRequestLog();
                if ($main && $reqlog !== null) $reqlog->WriteFile();
                
                $this->commitDatabase(true);
                
                if ($this->apipack->GetMetricsLevel()) 
                {
                    $commit_stats = $db->GetInternal()->popStatsContext();
                    $this->commit_stats[] = $commit_stats;
                }
            }
            else foreach ($this->apps as $app) $app->commit();
            
            $this->dirty = false;
        });
    }
    
    /**
     * Commits the database or does a rollback if readOnly/dryrun
     * @param bool $apps if true, commit/rollback apps also
     */
    private function commitDatabase(bool $apps) : void
    {
        $rollback = $this->apipack->isCommitRollback();
        
        if ($apps) foreach ($this->apps as $app) 
            if ($rollback) $app->rollback(); else $app->commit();
        
        $db = $this->apipack->GetDatabase();
            
        if ($rollback) $db->rollback(); 
        else $db->commit();
    }
}
