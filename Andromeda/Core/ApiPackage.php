<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/AppRunner.php");

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{Database, ObjectDatabase, DBStats};
require_once(ROOT."/Core/Database/Exceptions.php");
use Andromeda\Core\Database\{DatabaseException, DatabaseConfigException};

require_once(ROOT."/Core/Exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/IOFormat/IOInterface.php");
require_once(ROOT."/Core/IOFormat/Output.php");
use Andromeda\Core\IOFormat\{IOInterface,Output};

require_once(ROOT."/Core/Logging/RequestLog.php");
require_once(ROOT."/Core/Logging/RequestMetrics.php");
use Andromeda\Core\Logging\{RequestLog, RequestMetrics};

/**
 * The main container class managing API singletons
 *
 * This is also a Singleton so it can be fetched anywhere with GetInstance().
 */
final class ApiPackage extends Singleton
{
    private ?ObjectDatabase $database = null;
    private ?Config $config = null;
    
    private AppRunner $apprunner;
    private ErrorManager $error_manager;
    private IOInterface $interface;
    
    /** @var DBStats performance metrics for construction */
    private DBStats $construct_stats;
    
    /** @var DBStats total request performance metrics */
    private DBStats $total_stats;
    
    /** Optional request log for this request */
    private ?RequestLog $requestlog = null;
    
    /** @var float time of request */
    private float $time;
    
    /** Gets the timestamp of when the request was started */
    public function GetTime() : float { return $this->time; }
    
    /** Returns true if the global ObjectDatabase instance is valid */
    public function HasDatabase() : bool { return $this->database !== null; }

    /** Returns the global ObjectDatabase instance or throws if not configured */
    public function GetDatabase() : ObjectDatabase
    {
        if ($this->database === null)
        {
            $this->error_manager->LogBreakpoint(); // new trace
            
            throw $this->dbException;
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
            
            $this->error_manager->LogBreakpoint(); // new trace
            
            throw $this->cfgException;
        }
        else return $this->config;
    }
    
    /** Returns the created apprunner interface */
    public function GetAppRunner() : AppRunner { return $this->apprunner; }
    
    /** Returns true if the apprunner has been constructed (error manager only) */
    public function HasAppRunner() : bool { return isset($this->apprunner); }
    
    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->error_manager; }
    
    /** Returns the request log entry for this request */
    public function GetRequestLog() : ?RequestLog { return $this->requestlog; }
    
    private DatabaseConfigException $dbException; // reason DB did not init
    private DatabaseException $cfgException; // reason config did not init
    /**
     * Initializes the Main API resources
     *
     * Creates the error manager, initializes the interface, 
     * initializes the database, loads global config, creates AppRunner
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errorman the error manager instance
     * @throws MaintenanceException if the server is not enabled
     */
    public function __construct(IOInterface $interface, ErrorManager $errorman)
    {
        $this->error_manager = $errorman->SetAPI($this);
        
        parent::__construct();
        
        $this->time = microtime(true);
        $this->interface = $interface;
        $this->total_stats = new DBStats();
        
        $interface->Initialize(); // after creating stats!
        
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
                $this->database->GetInternal()->SetReadOnly();
        }
        catch (DatabaseException $e)
        {
            $this->cfgException = $e;
        }
        
        $this->apprunner = new AppRunner($this);
        
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
    
    /** Return true if we want to rollback on commit (dryrun or read-only) */
    public function isCommitRollback() : bool
    {
        return $this->database->isReadOnly() ||
            ($this->config && $this->config->isDryRun());
    }
    
    /**
     * Compiles performance metrics and adds them to the given output, and logs
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     */
    public function SaveMetrics(Output $output, bool $isError = false) : void
    {
        if (!$this->database || !($mlevel = $this->GetMetricsLevel())) return;
        
        // saving metrics must be in its own transaction for commit/rollback
        if ($this->database->GetInternal()->inTransaction())
            throw new FinalizeTransactionException();
        
        try
        {
            $actions = $this->apprunner->GetActionHistory();
            $commits = $this->apprunner->GetCommitStats();
            
            foreach ($actions as $context)
                $this->total_stats->Add($context->GetMetrics());
            
            foreach ($commits as $commit)
                $this->total_stats->Add($commit);
            
            $this->total_stats->stopTiming();
            
            $metrics = RequestMetrics::Create(
                $mlevel, $this->database, 
                $this->requestlog, $this->construct_stats,
                $actions, $commits, $this->total_stats)->Save();
            
            if ($this->isCommitRollback()) 
                $this->database->rollback();
            else $this->database->commit();
            
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
