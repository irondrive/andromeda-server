<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Singleton;
require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/interfaces/AJAX.php");
require_once(ROOT."/core/ioformat/interfaces/CLI.php");

if (!function_exists('json_encode')) die("PHP JSON Extension Required\n");

/**
 * Describes an abstract PHP I/O interface abstraction
 */
abstract class IOInterface extends Singleton
{
    /** Constructs and returns a singleton of the appropriate interface type */
    public static function TryGet() : ?self
    {
        if (Interfaces\AJAX::isApplicable()) return new Interfaces\AJAX();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }

    /** Returns whether or not the interface is in use for this request */
    abstract public static function isApplicable() : bool;
    
    /** Returns whether or not the interface grants privileged access */
    abstract public static function isPrivileged() : bool;
    
    /** Called during API construction to initialize the interface, e.g. to gather global arguments */
    public function Initialize() : void { }
    
    /** 
     * Retrieves an array of input objects to run 
     * @return Input[]
     */
    abstract public function GetInputs(?Config $config) : array;
    
    /** Returns the address of the user on the interface */
    abstract public function getAddress() : string;    
    
    /** Returns the user agent string on the interface */
    abstract public function getUserAgent() : string;
    
    /** Returns the debugging level requested by the interface */
    public function GetDebugLevel() : int { return 0; }
    
    /** Returns the path to the DB config file requested by the interface */
    public function GetDBConfigFile() : ?string { return null; }
    
    const OUTPUT_PLAIN = 1; const OUTPUT_JSON = 2; const OUTPUT_PRINTR = 3;

    /** Gets the default output mode for the interface */
    abstract public static function GetDefaultOutmode() : int;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }
    
    /** Tells the interface to ignore printing output */
    public function DisableOutput() : self 
    {
        $this->outmode = false; return $this; 
    }
    
    private static $retfuncs = array();
    
    /** Registers a user output handler function to run after the initial commit */
    public function RegisterOutputHandler(callable $f) : self 
    {
        array_push(self::$retfuncs, $f); return $this; 
    }
    
    /** Tells the interface to run the custom user output functions */
    public function UserOutput(Output $output) : bool
    {
        foreach (self::$retfuncs as $f) $f($output);
        
        return count(self::$retfuncs) > 0;
    }
    
    /** Tells the interface to print its final output */
    public abstract function FinalOutput(Output $output);
}
