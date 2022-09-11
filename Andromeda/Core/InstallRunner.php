<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/ApiPackage.php");
require_once(ROOT."/Core/InstallerApp.php");
require_once(ROOT."/Core/BaseRunner.php");

require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/Exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/IOFormat/IOInterface.php");
require_once(ROOT."/Core/IOFormat/Input.php");
use Andromeda\Core\IOFormat\{IOInterface, Input};

require_once(ROOT."/Core/Database/Exceptions.php");
use Andromeda\Core\Database\{DatabaseConnectException, DatabaseMissingException};

/** 
 * A special runner class for loading and running app installers
 * 
 * Creates and holds its own resources instead of a separate ApiPackage
 */
class InstallRunner extends BaseRunner
{
    private IOInterface $interface;
    private ErrorManager $errorman;
    
    private ?ObjectDatabase $database = null;
    /** The exception thrown when db loading failed */
    private ?DatabaseConnectException $dbexc = null;
    
    /** @var array<string, InstallerApp> */
    private array $installers = array();
    
    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns a reference to the global error manager */
    public function GetErrorManager() : ErrorManager { return $this->errorman; }
    
    /** Returns true if the database connection is available */
    public function HasDatabase() : bool { return $this->database !== null; }
    
    /** Returns true if the database did not give a DatabaseMissingException */
    public function HasDatabaseConfig() : bool { return !($this->dbexc instanceof DatabaseMissingException); }
    
    /** 
     * Returns the ObjectDatabase instance
     * @throws DatabaseConnectException if not available
     */
    public function RequireDatabase() : ObjectDatabase
    {
        if ($this->database === null) 
        {
            // ASSERT: dbexc XOR database are null
            assert($this->dbexc !== null); throw $this->dbexc; 
        }
        return $this->database;
    }

    /**
     * Returns the array of installer apps
     * @return array<string,InstallerApp>
     */
    public function GetInstallers() : array { return $this->installers; }
    
    /** 
     * Creates the AppRunner service, loading/constructing all installers 
     *
     * @param IOInterface $interface the interface that began the request
     * @param ErrorManager $errman error manager reference
     * @throws InstallDisabledException if install is globally disabled
     */
    public function __construct(IOInterface $interface, ErrorManager $errman)
    {
        parent::__construct();
        $this->interface = $interface;
        $this->errorman = $errman;
        
        if (!$interface->isPrivileged() && 
            defined('ALLOW_HTTP_INSTALL') && !ALLOW_HTTP_INSTALL)
            throw new InstallDisabledException();

        try { $this->database = ApiPackage::InitDatabase($interface); }
        catch (DatabaseConnectException $e) { $this->dbexc = $e; }
        
        if ($this->database !== null)
            $this->errorman->SetDatabase($this->database);
        
        if ($this->database !== null) try
        {
            $config = Config::GetInstance($this->database);
            $interface->AdjustConfig($config);
            $this->errorman->SetConfig($config);
        }
        catch (InstallRequiredException | UpgradeRequiredException $e) { /* leave un-init */ }
        
        foreach (Config::ScanApps() as $app)
        {
            $uapp = Utilities::FirstUpper($app);
            $path = ROOT."/Apps/$uapp/$uapp"."InstallApp.php";
            $class = "Andromeda\\Apps\\$uapp\\$uapp".'InstallApp';
            
            if (is_file($path)) require_once($path); else continue;
            if (class_exists($class) && is_a($class, InstallerApp::class, true))
            {
                $this->installers[$app] = new $class($this);
            }
        }
        
        $this->errorman->SetRunner($this);
    }
    
    /**
     * Calls into an installer to run the given Input command
     *
     * Calls Run() on the requested installer and then saves 
     * (but does not commit) any modified objects.
     * @param Input $input the user input command to run
     * @throws UnknownAppException if the requested app is invalid
     * @return mixed the app-specific return value
     */
    public function Run(Input $input)
    {
        $app = $input->GetApp();
        if (!array_key_exists($app, $this->installers))
            throw new UnknownAppException();

        $context = new RunContext($input, null);
        $this->stack[] = $context;
        $this->dirty = true;
        
        $installer = $this->installers[$app];
        $retval = $installer->Run($input);

        if ($this->database !== null)
            $this->database->SaveObjects();
        
        array_pop($this->stack);
        
        return $retval;
    }
    
    /** Rolls back the current database transaction */
    public function rollback(?\Throwable $e = null) : void
    {
        Utilities::RunNoTimeout(function()
        {
            if ($this->database !== null)
                $this->database->rollback();
            
            $this->dirty = false;
        });
    }
    
    /** Commits the current database transaction */
    public function commit() : void
    {
        Utilities::RunNoTimeout(function()
        {
            if ($this->database !== null)
            {
                if ($this->interface->isDryRun())
                    $this->database->rollback();
                else $this->database->commit();
            }
            
            $this->dirty = false;
        });
    }
}

