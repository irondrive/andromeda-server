<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Exceptions.php");

require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog; // phpstan
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

/** The base class from which apps must inherit */
abstract class BaseApp
{
    /** Reference to the main API package */
    protected ApiPackage $API;
    /** Reference to the main database */
    protected ObjectDatabase $database;

    /** All apps are constructed when Andromeda runs */
    public function __construct(ApiPackage $api)
    {
        $this->API = $api; $this->database = $api->GetDatabase();
    }
    
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    /**
     * Run an action on the app with the given input
     * @param Input $input the user input
     * @return mixed the value to be output to the user
     */
    public abstract function Run(Input $input);

    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return array<string> possible commands
     */
    public abstract function getUsage() : array;
    
    /** @return string the lowercase name of the app */
    public abstract function getName() : string;
    
    /** @return string the app's version string */
    public abstract function getVersion() : string;
    
    /** 
     * Return this app's ActionLog extension class name, if used (or null)
     * If not null, the app is responsible for creating its ActionLog entries
     * @return ?class-string<ActionLog>
     */
    public function getLogClass() : ?string { return null; }

    /** Tells the app to commit any changes made outside the database */
    public function commit() { }
    
    /** Tells the app to rollback any changes made outside the database */
    public function rollback() { }
}
