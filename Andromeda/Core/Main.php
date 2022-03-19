<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Database/Database.php"); 
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{Database, ObjectDatabase, DBStats, DatabaseException, DatabaseConfigException};

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

/** FinalMetrics requires the database to not already be undergoing a transaction */
class FinalizeTransactionException extends Exceptions\ServerException { public $message = "OUTPUT_IN_TRANSACTION"; }

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
 * 
 * The Andromeda transaction model is that there is always a global commit
 * or rollback at the end of the request. A rollback may follow a bad commit.
 * There will NEVER be a rollback followed by a commit. There may be > 1 commit.
 */
final class Main extends Singleton
{
    /** @var DBStats performance metrics for construction */
    private DBStats $construct_stats; 
    
    /** @var array<RunContext> action/context history */
    private array $action_history = array();
    
    /** @var array<DBStats> commit stats */
    private array $commit_stats = array();
    
    /** @var DBStats total request performance metrics */
    private DBStats $total_stats;
    
    /** Optional request log for this request */
    private ?RequestLog $requestlog = null;
    
    /** @var float time of request */
    private float $time;
    
    /** @var array<string,BaseApp> apps indexed by name */
    private array $apps = array(); 
    
    /** @var RunContext[] stack frames for nested Run() calls */
    private array $stack = array(); 
    
    private ?ObjectDatabase $database = null;
    private ?Config $config = null;
    
    private ErrorManager $error_manager;
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
    
    /** Instantiates and returns a new ObjectDatabase connection */
    public function InitDatabase() : ObjectDatabase
    {
        $dbconf = Database::LoadConfig(
            $this->interface->GetDBConfigFile());
        
        return new ObjectDatabase(new Database($dbconf));
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
    
    /** Returns the request log entry for this request */
    public function GetRequestLog() : ?RequestLog { return $this->requestlog; }
    
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
            $this->database = $this->InitDatabase();
        }
        catch (DatabaseConfigException $e)
        {
            if ($interface->GetDBConfigFile() !== null) throw $e;
            
            $this->dbException = $e;
        }
        
        if ($this->database) try
        {
            $this->config = Config::GetInstance($this->database);

            if (!$this->isEnabled()) throw new MaintenanceException();
            
            if ($this->config->isReadOnly()) 
                $this->database->GetInternal()->setReadOnly();
            
            if ($this->config->getVersion() === andromeda_version)
                $apps = array_merge($apps, $this->config->GetApps());
        }
        catch (DatabaseException $e) 
        {
            $this->cfgException = $e;
        }
        
        foreach ($apps as $app) $this->TryLoadApp($app);

        if ($this->config && !$this->config->isReadOnly()
            && $this->config->GetEnableRequestLog())
        {
            $this->requestlog = RequestLog::Create($this);
        }
        
        if ($this->database)
        {
            $this->construct_stats = $this->database->GetInternal()->popStatsContext();
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
        
        if (!array_key_exists($app, $this->apps)) 
            throw new UnknownAppException();

        if ($this->GetMetricsLevel()) 
            $this->database->GetInternal()->pushStatsContext();

        $logclass = $this->apps[$app]::getLogClass();
        $actionlog = ($logclass === null && $this->requestlog !== null)
            ? $this->requestlog->LogAction($input, ActionLog::class) : null;

        $context = new RunContext($input, $actionlog);
        $this->stack[] = $context; 
        $this->dirty = true;
        
        $retval = $this->apps[$app]->Run($input);
        
        if ($this->database) $this->database->saveObjects();
        
        array_pop($this->stack);

        if ($this->GetMetricsLevel())
        {
            $stats = $this->database->GetInternal()->popStatsContext();
            
            $this->total_stats->Add($stats);
            $this->action_history[] = $context;
            $context->SetMetrics($stats);
        }
        
        return $retval;
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
            foreach ($this->apps as $app) $this->error_manager->LoggedTry(
                function()use($app) { $app->rollback(); });
            
            if ($this->database) 
            {
                $this->database->rollback();
                
                if ($e instanceof ClientException) 
                    $this->error_manager->LoggedTry(function()use($e)
                {
                    if ($this->requestlog !== null)
                        $this->requestlog->SetError($e);
                        
                    $this->database->saveObjects(true);
                    
                    if ($this->requestlog !== null) 
                        $this->requestlog->WriteFile();
                    
                    $this->commitDatabase(false);
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
     */
    public function commit() : void
    {
        Utilities::RunNoTimeout(function()
        {
            if ($this->database)
            {
                if ($this->GetMetricsLevel()) 
                    $this->database->GetInternal()->pushStatsContext();
                
                $this->database->saveObjects();
                
                if ($this->requestlog !== null)
                    $this->requestlog->WriteFile();
                        
                $this->commitDatabase(true);
                
                if ($this->GetMetricsLevel()) 
                {
                    $commit_stats = $this->database->GetInternal()->popStatsContext();
                    
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
    private function commitDatabase(bool $apps) : void
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
    
    /** 
     * Returns the configured debug level, or the interface's default 
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetDebugLevel(bool $output = false) : int
    {
        if ($this->config)
        {            
            $debug = $this->config->GetDebugLevel();
            
            if ($output && !$this->config->GetDebugOverHTTP() && 
                !$this->interface->isPrivileged()) $debug = 0;
            
            return $debug;
        }
        else return $this->interface->GetDebugLevel();
    }
    
    /**
     * Returns the configured metrics level, or the interface's default
     * Returns 0 automatically if the database is not present
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetMetricsLevel(bool $output = false) : int
    {
        if (!$this->database) return 0; // required
        
        if ($this->config)
        {
            $metrics = $this->config->GetMetricsLevel();
            
            if ($output && !$this->config->GetDebugOverHTTP() &&
                !$this->interface->isPrivileged()) $metrics = 0;
            
            return $metrics;
        }
        else return $this->interface->GetMetricsLevel();
    }
    
    /**
     * Compiles performance metrics and adds them to the given output
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     */
    public function FinalMetrics(Output $output, bool $isError = false) : void
    {
        if (!$this->database || !($mlevel = $this->GetMetricsLevel())) return;
        
        if ($this->database->GetInternal()->inTransaction())
            throw new FinalizeTransactionException();
        
        try
        {
            $this->total_stats->stopTiming();
            
            $metrics = RequestMetrics::Create(
                $mlevel, $this->database, $this->requestlog,
                $this->construct_stats, $this->action_history,
                $this->commit_stats, $this->total_stats)->Save();
            
            $this->commitDatabase(false);
            
            if ($this->GetMetricsLevel(true))
                $output->SetMetrics($metrics->GetClientObject($isError));
        }
        catch (\Throwable $e)
        {
            if ($this->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $this->error_manager->LogException($e, false);
        }
    }
}
