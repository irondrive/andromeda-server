<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Exceptions/Exceptions.php");
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_ACTION"; }

/** An exception indicating that the app is not installed and needs to be */
class InstallRequiredException extends Exceptions\ServerException { public $message = "APP_INSTALL_REQUIRED"; }

/** An exception indicating that the metadata file is missing */
class MissingMetadataException extends Exceptions\ServerException { public $message = "APP_METADATA_MISSING"; }

/** The base class from which apps must inherit */
abstract class AppBase implements Transactions
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
    
    /** @return string the name of the app */
    public abstract static function getName() : string;
    
    /** Return this app's BaseAppLog class name, if used (or null) */
    protected static function getLogClass() : ?string { return null; }
    
    private static array $metadata = array();
    
    /** 
     * Loads a metadata for the given app with the given key
     * 
     * Loads the app's JSON metadata file but not its code
     */
    protected static function getMetadata(string $app, string $key)
    {
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
    
    /** @return int Returns the major API version this app is compatible with */
    public static function getAppReqVersion(string $app) : int
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
 * Describes an app that stores database versions
 * and has upgrade scripts for upgrading the database
 */
abstract class UpgradableApp extends AppBase
{    
    public static function getUsage() : array { return array('upgrade'); }
    
    /** @return DBVersion that database object that stores the app version */
    protected abstract function getDBVersion() : DBVersion;
    
    /** @return array<string,callable> the array of upgrade scripts indexed by version (in order!) */
    protected static function getUpgradeScripts() : array
    {
        return require(ROOT."/Apps/".static::getName()."/upgrade/scripts.php");
    }
    
    /**
     * Iterates over the list of upgrade scripts, running them
     * sequentially until the DB is up to date with the code
     */
    public function Upgrade() : void
    {        
        $oldVersion = $this->getDBVersion()->getVersion();
        
        foreach (static::getUpgradeScripts() as $newVersion=>$script)
        {
            if (version_compare($newVersion, $oldVersion) === 1 &&
                version_compare($newVersion, static::getVersion()) <= 0)
            {
                $script(); $this->getDBVersion()->setVersion($newVersion);
            }
        }
        
        $this->getDBVersion()->setVersion(static::getVersion());
    }
    
    /**
     * Complements Run() with checking if upgrade is required and running it
     * @param Input $input the app action input object
     * @throws UpgradeRequiredException if the DB version does not match
     * @return bool true if the upgrade action was performed
     */
    protected function CheckUpgrade(Input $input) : bool
    {
        if ($this->getDBVersion()->getVersion() !== static::getVersion())
        {
            if ($input->GetAction() === 'upgrade')
            {
                $this->Upgrade(); return true;
            }
            else throw new UpgradeRequiredException(static::getName());
        }
        return false;
    }
}

