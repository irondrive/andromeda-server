<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/AppRunner.php");
require_once(ROOT."/Core/MetricsHandler.php");

require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{Database, ObjectDatabase};
require_once(ROOT."/Core/Database/Exceptions.php");
use Andromeda\Core\Database\{DatabaseException, DatabaseConfigException};

require_once(ROOT."/Core/Exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/IOFormat/IOInterface.php");
use Andromeda\Core\IOFormat\IOInterface;

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
    private MetricsHandler $perfstats;

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
    
    /** Returns the global performance metrics handler */
    public function GetMetricsHandler() : MetricsHandler { return $this->perfstats; }

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
    public function __construct(IOInterface $interface, ErrorManager $errorman, MetricsHandler $perfstats)
    {
        $this->error_manager = $errorman->SetAPI($this);
        
        parent::__construct();
        
        $this->time = microtime(true);
        $this->interface = $interface;
        $this->perfstats = $perfstats;
        
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
    
    /** Return true if we will rollback on commit (dryrun or read-only) */
    public function isCommitRollback() : bool
    {
        return ($this->database && $this->database->isReadOnly()) ||
            ($this->config && $this->config->isDryRun());
    }
}
