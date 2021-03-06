<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{ObjectDatabase, DBStats, DatabaseException, DatabaseConfigException};

require_once(ROOT."/Core/Exceptions/ErrorManager.php");
require_once(ROOT."/Core/Exceptions/Exceptions.php");
use Andromeda\Core\Exceptions\{ErrorManager, ClientException};

require_once(ROOT."/Core/IOFormat/IOInterface.php");
require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/Output.php");
require_once(ROOT."/Core/IOFormat/Interfaces/AJAX.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface};
use Andromeda\Core\IOFormat\Interfaces\AJAX;

require_once(ROOT."/Core/Logging/RequestLog.php");
require_once(ROOT."/Core/Logging/ActionLog.php");
require_once(ROOT."/Core/Logging/RequestMetrics.php");
use Andromeda\Core\Logging\{RequestLog, ActionLog, RequestMetrics};

/** Exception indicating that the requested app is invalid */
class UnknownAppException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_APP"; }

/** Exception indicating that the server is configured as disabled */
class MaintenanceException extends Exceptions\ClientException { public $code = 503; public $message = "SERVER_DISABLED"; }

/** Exception indicating that the server failed to load a configured app */
class FailedAppLoadException extends Exceptions\ServerException  { public $message = "FAILED_LOAD_APP"; }

/** Andromeda cannot rollback and then commit since database/objects state is not sufficiently reset */
class CommitAfterRollbackException extends Exceptions\ServerException { public $message = "COMMIT_AFTER_ROLLBACK"; }

/** FinalizeOutput requires the database to not already be undergoing a transaction */
class FinalizeTransactionException extends Exceptions\ServerException { public $message = "FINALIZE_OUTPUT_IN_TRANSACTION"; }

class RunContext 
{ 
    private Input $input;     
    private ?ActionLog $actionlog;
    private ?DBStats $metrics;
    
    public function GetInput() : Input { return $this->input; }
    public function GetActionLog() : ?ActionLog { return $this->actionlog; }
    
    public function __construct(Input $input, ?ActionLog $actionlog){ 
        $this->input = $input; $this->actionlog = $actionlog; }
        
    public function GetMetrics() : ?DBStats { return $this->metrics; }
    public function SetMetrics(DBStats $metrics) { $this->metrics = $metrics; }
}

/**
 * The main container class managing API singletons and running apps
 * 
 * Main is also a Singleton so it can be fetched anywhere with GetInstance().
 * A Main handles a single request, with an arbitrary number of appruns.
 */
final class Main extends Singleton
{
    /** @var DBStats performance metrics for construction */
    private DBStats $construct_stats; 
    
    /** @var array<RunContext> logged actions w/ stats */
    private array $action_stats = array(); 
    
    /** @var array<DBStats> commit stats */
    private array $commit_stats = array();
    
    /** @var DBStats total request time */
    private DBStats $total_stats;
    
    /** @var RequestLog */
    private RequestLog $reqlog;
    
    /** @var float time of request */
    private float $time;
    
    /** @var array<string,BaseApp> apps indexed by name */
    private array $apps = array(); 
    
    /** @var RunContext[] stack frames for nested Run() calls */
    private array $stack = array(); 
    
    /** @var ?Config */
    private ?Config $config = null; 
    
    /** @var ?ObjectDatabase */
    private ?ObjectDatabase $database = null; 
    
    /** @var ErrorManager */
    private ErrorManager $error_manager;
    
    /** @var IOInterface */
    private IOInterface $interface;
    
    /** @var bool true if Run() has been called since the last commit or rollback */
    private bool $dirty = false;
    
    /** Gets the timestamp of when the request was started */
    public function GetTime() : float { return $this->time; }
    
    /**
     * Gets an array of instantiated apps
     * @return array<string, BaseApp>
     */
    public function GetApps() : array { return $this->apps; }

    /** Returns true if the global ObjectDatabase instance is valid */
    public function HasDatabase() : bool { return $this->database !== null; }
    
    /** Returns the global ObjectDatabase instance or throws if not configured */
    public function GetDatabase() : ObjectDatabase
    {
        if ($this->database === null)
        {
            $class = get_class($this->dbException);
            throw $class::Copy($this->dbException); // new trace
        }
        else return $this->database;
    }
    
    /** Returns the global config object or null if not installed */
    public function TryGetConfig() : ?Config { return $this->config; }
    
    /** Returns the global config object if installed else throws */
    public function GetConfig() : Config
    {
        if ($this->config === null)
        {
            $this->GetDatabase(); // assert db exists
            
            $class = get_class($this->cfgException);
            throw $class::Copy($this->cfgException); // new trace
        }
        else return $this->config;
    }
    
    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->error_manager; }
    
    /** Returns the RunContext that is currently being executed */
    public function GetContext() : ?RunContext { return Utilities::array_last($this->stack); }
    
    private DatabaseConfigException $dbException; // reason DB did not init
    private DatabaseException $cfgException; // reason config did not init

    /**
     * Initializes the Main API
     * 
     * Creates the error manager, initializes the interface, initializes the
     * database, loads global config, loads and constructs registered apps 
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errorman the error manager instance
     * @throws MaintenanceException if the server is not enabled
     * @throws FailedAppLoadException if a registered app fails to load
     */
    public function __construct(IOInterface $interface, ErrorManager $errorman)
    {
        $this->error_manager = $errorman->SetAPI($this);
        
        parent::__construct();
        
        $this->time = microtime(true);
        $this->interface = $interface;
        $this->total_stats = new DBStats();
        
        $interface->Initialize();

        $apps = array();
        
        if (file_exists(ROOT."/Apps/Core/CoreApp.php"))
            $apps[] = 'core'; // always enabled if present
        
        try 
        {
            $dbconf = $interface->GetDBConfigFile();
            $this->database = new ObjectDatabase($dbconf);
            $this->database->pushStatsContext();
        }
        catch (DatabaseConfigException $e)
        {
            if ($dbconf !== null) throw $e;
            $this->dbException = $e;
        }
        
        if ($this->database) try
        {
            $this->config = Config::GetInstance($this->database);

            if (!$this->isEnabled()) throw new MaintenanceException();
            
            if ($this->config->isReadOnly()) 
                $this->database->setReadOnly();
            
            if ($this->config->getVersion() === andromeda_version)
                $apps = array_merge($apps, $this->config->GetApps());
        }
        catch (DatabaseException $e) 
        {
            $this->cfgException = $e;
        }
        
        foreach ($apps as $app) $this->TryLoadApp($app);

        if ($this->database)
        {
            if ($this->config && !$this->config->isReadOnly()
                    && $this->config->GetEnableRequestLog())
                $this->reqlog = RequestLog::Create($this);
            
            $this->construct_stats = $this->database->popStatsContext();
            $this->total_stats->Add($this->construct_stats);
        }
        
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
            $this->apps[$app] = new $app_class($this);
            
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
        
        if (!array_key_exists($app, $this->apps)) throw new UnknownAppException();
        
        $dbstats = $this->GetDebugLevel() >= Config::ERRLOG_DETAILS;
        
        if ($dbstats && $this->database) 
            $this->database->pushStatsContext();
        
        $actionlog = isset($this->reqlog) ? $this->reqlog->LogAction($input) : null;
            
        $context = new RunContext($input, $actionlog);
        $this->stack[] = $context; $this->dirty = true;
        
        $data = $this->apps[$app]->Run($input);

        if ($this->database) $this->database->saveObjects();
        
        array_pop($this->stack);
        
        if ($dbstats && $this->database)
        {
            $stats = $this->database->popStatsContext();
            $this->total_stats->Add($stats);
            
            $context->SetMetrics($stats);
            $this->action_stats[] = $context;
        }
        
        return $data;
    }     

    /**
     * Calls into a remote API to run the given Input command
     * 
     * Note that this breaks transactions - the remote API will 
     * commit before we get the response to this remote call.
     * @param string $url the base URL of the remote API
     * @see Main::Run()
     */
    public function RunRemote(string $url, Input $input)
    {
        $data = AJAX::RemoteRequest($url, $input);

        return Output::ParseArray($data)->GetAppdata();
    }
    
    private bool $rolledback = false;
    
    /** Runs the given $func in try/catch and logs if exception */
    public static function LoggedTry(callable $func)
    {
        try { return $func(); } catch (\Throwable $e) { 
            ErrorManager::GetInstance()->LogException($e); }
    }
    
    /**
     * Rolls back the current transaction. Internal only, do not call via apps.
     * 
     * First rolls back each app, then the database, then saves mandatorySave objects if not a server error
     * @param ?\Throwable $e the exception that caused the rollback (or null)
     */
    public function rollback(?\Throwable $e = null) : void
    {
        Utilities::RunAtomic(function()use($e)
        {
            $this->rolledback = true;
            
            foreach ($this->apps as $app) $this->LoggedTry(
                function()use($app) { $app->rollback(); });
            
            if ($this->database) 
            {
                $this->database->rollback();
                
                if ($e instanceof ClientException) 
                    $this->LoggedTry(function()use($e)
                {
                    if (isset($this->reqlog)) 
                        $this->reqlog->SetError($e);
                    
                    $this->database->saveObjects(true);
                    $this->innerCommit(false);
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
     * @throws CommitAfterRollbackException if a rollback was previously performed
     */
    public function commit() : void
    {
        if ($this->rolledback) throw new CommitAfterRollbackException();
        
        Utilities::RunAtomic(function()
        {
            if ($this->database)
            {
                if ($this->GetDebugLevel() >= Config::ERRLOG_DETAILS) 
                    $this->database->pushStatsContext();
                
                $this->database->saveObjects();
                
                $this->innerCommit(true);
                
                if ($this->GetDebugLevel() >= Config::ERRLOG_DETAILS) 
                {
                    $commit_stats = $this->database->popStatsContext();
                    $this->commit_stats[] = $commit_stats;
                    $this->total_stats->Add($commit_stats);
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
    private function innerCommit(bool $apps) : void
    {
        $rollback = $this->database->isReadOnly() || 
            ($this->config && $this->config->isDryRun());
        
        if ($apps) foreach ($this->apps as $app) 
            if ($rollback) $app->rollback(); else $app->commit();
        
        if ($rollback) $this->database->rollback(); 
        else $this->database->commit();
    }

    /** if false, requests are not allowed (always true for privileged interfaces) */
    private function isEnabled() : bool
    {
        if ($this->config === null) return true;
        
        return $this->config->isEnabled() || $this->interface->isPrivileged();
    }
    
    /** Returns the configured debug level, or the interface's default */
    public function GetDebugLevel() : int
    {
        if ($this->config)
        {            
            $debug = $this->config->GetDebugLevel();
            
            if (!$this->config->GetDebugOverHTTP() && 
                !$this->interface->isPrivileged()) $debug = 0;
            
            return $debug;
        }
        else return $this->interface->GetDebugLevel();
    }
    
    /**
     * Compiles performance metrics and adds them to the given output
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     */
    public function FinalizeOutput(Output $output, bool $isError = false) : void
    {
        $mlevel = $this->config ? $this->config->GetMetricsLevel() : $this->interface->GetMetricsLevel();
        
        if (!$this->database || !$mlevel) return;
        
        if  ($this->GetDebugLevel() < Config::ERRLOG_DETAILS &&
             !$this->interface->isPrivileged()) return;
        // TODO should check debug over HTTP config?
        
        if ($this->database->inTransaction())
            throw new FinalizeTransactionException();
            
        try
        {
            $metrics = RequestMetrics::Create(
                $mlevel, $this->database, $this->reqlog ?? null,
                $this->construct_stats, $this->action_stats,
                $this->commit_stats, $this->total_stats);
            
            $output->SetMetrics($metrics->GetClientObject($isError));
            
            $metrics->Save(); $this->innerCommit(false);
        }
        catch (\Throwable $e)
        {
            if ($this->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $this->error_manager->LogException($e, false);
        }
    }
}
