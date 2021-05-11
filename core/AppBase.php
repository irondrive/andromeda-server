<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php");
require_once(ROOT."/core/exceptions/Exceptions.php");
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_ACTION"; }

/** An exception indicating that the app is missing its config */
class UnknownConfigException extends Exceptions\ServerException { public $message = "MISSING_CONFIG"; }

/** An exception indicating that the metadata file is missing */
class MissingMetadataException extends Exceptions\ServerException { public $message = "METADATA_MISSING"; }

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
    
    public function Test(Input $input) { }
    
    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return array<string> possible commands
     */
    public abstract static function getUsage() : array;
    
    /** Return this app's BaseAppLog class name, if used (or null) */
    public static function getLogClass() : ?string { return null; }
    
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
            $path = ROOT."/apps/$app/metadata.json";
            
            if (!file_exists($path)) throw new MissingMetadataException();

            self::$metadata[$app] = Utilities::JSONDecode(file_get_contents($path));
        }
        
        return self::$metadata[$app][$key] ?? null;
    }
    
    /** Returns the API version this app is compatible with */
    public static function getReqVersion(string $app) : int
    {
        return self::getMetadata($app,'api-version');
    }
    
    /** Returns the app's version information */
    public static function getVersion(string $app) : string
    {
        return self::getMetadata($app,'version');
    }
    
    /** Returns the list of apps this app depends on */
    public static function getRequires(string $app) : array 
    { 
        return self::getMetadata($app,'requires') ?? array();
    }    
    
    /** Tells the app to commit any changes made outside the database */
    public function commit() { }
    
    /** Tells the app to rollback any changes made outside the database */
    public function rollback() { }
}
