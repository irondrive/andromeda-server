<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php");
require_once(ROOT."/core/exceptions/Exceptions.php");
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_ACTION"; }

/** An exception indicating that the app is missing its config */
class UnknownConfigException extends Exceptions\ServerException { public $message = "MISSING_CONFIG"; }

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
    
    public function Install(Input $input) { }
    
    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return array<string> possible commands
     */
    public abstract static function getUsage() : array;
    
    /** Returns the app's version information */
    public abstract static function getVersion() : string;
    
    /** Tells the app to commit any changes made outside the database */
    public function commit() { }
    
    /** Tells the app to rollback any changes made outside the database */
    public function rollBack() { }
}
