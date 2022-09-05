<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/AppRunner.php");
require_once(ROOT."/Core/MetricsHandler.php");
require_once(ROOT."/Core/Exceptions.php");

require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
require_once(ROOT."/Core/Database/Exceptions.php");
use Andromeda\Core\Database\{Database, ObjectDatabase, DatabaseMissingException, DatabaseConnectException};

require_once(ROOT."/Core/Exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/IOFormat/IOInterface.php");
use Andromeda\Core\IOFormat\IOInterface;

/** The main container class creating and managing API resources */
class ApiPackage
{
    private ObjectDatabase $database;
    private Config $config;
    
    private AppRunner $apprunner;
    private ErrorManager $errorman;
    private IOInterface $interface;
    private MetricsHandler $metrics;

    /** 
     * Instantiates and returns a new ObjectDatabase connection 
     * @param IOInterface $interface interface to get config from
     * @throws DatabaseMissingException if there is no config file
     * @throws DatabaseConnectException if the connection fails
     */
    public static function InitDatabase(IOInterface $interface) : ObjectDatabase
    {
        $dbconf = Database::LoadConfig($interface->GetDBConfigFile());
        
        return new ObjectDatabase(new Database($dbconf, $interface->isPrivileged()));
    }
    
    /** Returns the global ObjectDatabase instance */
    public function GetDatabase() : ObjectDatabase { return $this->database; }

    /** Returns the global config object */
    public function GetConfig() : Config { return $this->config; }

    /** Returns the created apprunner interface */
    public function GetAppRunner() : AppRunner { return $this->apprunner; }

    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->errorman; }
    
    /** Returns the global performance metrics handler */
    public function GetMetricsHandler() : MetricsHandler { return $this->metrics; }

    /**
     * Creates a new API package with the given resources
     *
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errman error manager reference
     * @throws DatabaseMissingException if there is no config file
     * @throws DatabaseConnectException if the connection fails
     * @throws InstallRequiredException if the Config is not available
     * @throws UpgradeRequiredException if the Config version is wrong
     * @throws MaintenanceException if the server is not enabled
     */
    public function __construct(IOInterface $interface, ErrorManager $errman)
    {
        $this->interface = $interface;
        $this->errorman = $errman;
        
        $this->metrics = new MetricsHandler();

        $this->database = self::InitDatabase($interface)->SetApiPackage($this);
        $this->errorman->SetDatabase($this->database);

        $this->config = Config::GetInstance($this->database);
            
        $interface->AdjustConfig($this->config);
        $this->errorman->SetConfig($this->config);

        $enabled = $this->config->isEnabled() || $interface->isPrivileged();
        if (!$enabled) throw new MaintenanceException();
        
        if ($this->config->isReadOnly())
            $this->database->GetInternal()->SetReadOnly();
            
        $this->database->GetInternal()->SetLogValues(
            $this->GetDebugLevel() >= Config::ERRLOG_SENSITIVE);
        
        $this->apprunner = new AppRunner($this);
        $this->errorman->SetApiPackage($this);
        
        $this->metrics->EndInitStats($this->database);
    }

    /**
     * Returns the configured debug level, accounting for the interface
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetDebugLevel(bool $output = false) : int
    {
        return $this->config->GetDebugLevel($output ? $this->interface : null);
    }
    
    /**
     * Returns the configured performance metrics level
     * @param bool $output if true, adjust level to interface privilege
     */
    public function GetMetricsLevel(bool $output = false) : int
    {
        return $this->config->GetMetricsLevel($output ? $this->interface : null);
    }
    
    /** Return true if we will rollback on commit (dryrun or read-only) */
    public function isCommitRollback() : bool
    {
        return $this->database->isReadOnly() || $this->interface->isDryRun();
    }
}
