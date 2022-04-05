<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, DatabaseException};
require_once(ROOT."/Core/Exceptions/Exceptions.php");

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ACTION", $details);
    }
}

/** An exception indicating that the app is not installed and needs to be */
class InstallRequiredException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_INSTALL_REQUIRED", $details);
    }
}

/** Exception indicating that the database upgrade scripts must be run */
class UpgradeRequiredException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_UPGRADE_REQUIRED", $details);
    }
}

/** An exception indicating that the metadata file is missing */
class MissingMetadataException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_METADATA_MISSING", $details);
    }
}

/** The base class from which apps must inherit */
abstract class BaseApp
{
    /** Reference to the main API, for convenience */
    protected Main $API;

    /** All apps are constructed when Andromeda runs */
    public function __construct(Main $API)
    {
        $this->API = $API;
    }
    
    /**
     * The main entry point into the app
     * @param Input $input the user input
     * @return mixed the value to be output to the user
     */
    public abstract function Run(Input $input);
    
    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return array<string> possible commands
     */
    public abstract static function getUsage() : array;
    
    /** @return string the lowercase name of the app */
    public abstract static function getName() : string;
    
    /** 
     * Return this app's ActionLog extension class name, if used (or null)
     * @return ?class-string<ActionLog>
     */
    public static function getLogClass() : ?string { return null; }
    
    private static array $metadata = array();
    
    /** 
     * Loads a metadata for the given app with the given key
     * 
     * Loads the app's JSON metadata file but not its code
     */
    protected static function getMetadata(string $app, string $key)
    {
        $app = strtolower($app);
        
        if (!array_key_exists($app, self::$metadata))
        {
            $uapp = Utilities::FirstUpper($app);
            $path = ROOT."/Apps/$uapp/metadata.json";
            
            if (!file_exists($path)) throw new MissingMetadataException();

            self::$metadata[$app] = Utilities::JSONDecode(file_get_contents($path));
        }
        
        return self::$metadata[$app][$key] ?? null;
    }
    
    /** @return array<string> Returns the list of apps this app depends on */
    public static function getAppRequires(string $app) : array
    {
        return self::getMetadata($app,'requires') ?? array();
    }
    
    /** @return string Returns the major.minor API version this app is compatible with */
    public static function getAppApiVersion(string $app) : string
    {
        return self::getMetadata($app,'api-version');
    }
    
    /** @return string the app's version information */
    public static function getVersion() : string 
    { 
        return self::getMetadata(static::getName(),'version'); 
    }

    /** Tells the app to commit any changes made outside the database */
    public function commit() { }
    
    /** Tells the app to rollback any changes made outside the database */
    public function rollback() { }
}

/** 
 * Describes an app that needs database installation
 * and has upgrade scripts for upgrading the database
 * and has a BaseConfig that stores the schema version
 * @template ConfigType of BaseConfig
 */
abstract class InstalledApp extends BaseApp
{    
    protected static function getInstallFlags() : string { return ""; }
    protected static function getUpgradeFlags() : string { return ""; }
    
    protected static function getInstallUsage() : array 
    {
        $istr = 'install'; if ($if = static::getInstallFlags()) $istr .= " $if";
        $ustr = 'upgrade'; if ($uf = static::getUpgradeFlags()) $ustr .= " $uf";
        
        return array($istr,$ustr);
    }
    
    public static function getUsage() : array { return static::getInstallUsage(); }
    
    /**
     * Return the BaseConfig class for this app 
     * @return class-string<ConfigType>
     */
    protected abstract static function getConfigClass() : string;
    
    /** @var ConfigType */
    protected BaseConfig $config;    
    
    protected ObjectDatabase $database;
    
    public function __construct(Main $API)
    {
        parent::__construct($API);
        
        if ($this->API->HasDatabase())
        {
            $this->database = $this->API->GetDatabase();
            
            try 
            {
                $class = static::getConfigClass();
                $this->config = $class::GetInstance($this->database);
            }
            catch (DatabaseException $e) { }
        }
    }
    
    /** Returns true if the user is allowed to install/upgrade */
    protected function allowInstall() : bool
    {
        return $this->API->GetInterface()->isPrivileged() ||
            !defined('HTTPINSTALL') || HTTPINSTALL;
    }
    
    /** Returns the path of the app's code folder */
    protected static function getTemplateFolder() : string
    {
        return ROOT.'/Apps/'.Utilities::FirstUpper(static::getName());
    }
    
    /** @return array<string,callable> the array of upgrade scripts indexed by version (in order!) */
    protected static function getUpgradeScripts() : array
    {
        return require(static::getTemplateFolder().'/_upgrade/scripts.php');
    }

    /**
     * Checks if the client is running/needs to run install/upgrade
     * {@inheritDoc}
     * @see \Andromeda\Core\BaseApp::Run()
     * @throws InstallRequiredException if the DB is not installed
     * @throws UpgradeRequiredException if the DB version does not match
     * @return mixed false if nothing was done else the app-specific retval
     */
    public function Run(Input $input)
    {
        $this->API->GetDatabase(); // assert db exists
        
        if (!isset($this->config))
        {
            if ($input->GetAction() === 'install' && $this->allowInstall())
            {
                return $this->Install($input->GetParams());
            }
            else throw new InstallRequiredException(static::getName());
        }
        else if ($this->config->getVersion() !== static::getVersion())
        {
            if ($input->GetAction() === 'upgrade' && $this->allowInstall())
            {
                return $this->Upgrade($input->GetParams());
            }
            else throw new UpgradeRequiredException(static::getName());
        }
        else return false;
    }
    
    /** Installs the app by importing its SQL file and creating config */
    protected function Install(SafeParams $params)
    {
        $this->API->GetInterface()->DisallowBatch();
        
        $this->database->GetInternal()->importTemplate(static::getTemplateFolder());
        
        $this->config = (static::getConfigClass())::Create($this->database)->Save();
    }
    
    /**
     * Iterates over the list of upgrade scripts, running them
     * sequentially until the DB is up to date with the code
     */
    protected function Upgrade(SafeParams $params)
    {
        $this->API->GetInterface()->DisallowBatch();
        
        $oldVersion = $this->config->getVersion();

        foreach (static::getUpgradeScripts() as $newVersion=>$script)
        {
            if (version_compare($newVersion, $oldVersion) === 1 &&
                version_compare($newVersion, static::getVersion()) <= 0)
            {
                $script(); $this->config->setVersion($newVersion);
                
                $this->API->commit(); // commit after every step
            }
        }
        
        $this->config->setVersion(static::getVersion());
    }
}

