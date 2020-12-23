<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/interfaces/AJAX.php");
require_once(ROOT."/core/ioformat/interfaces/CLI.php");

if (!function_exists('json_encode')) die("PHP JSON Extension Required\n");

abstract class IOInterface
{
    public static function TryGet() : ?IOInterface
    {
        if (Interfaces\AJAX::isApplicable()) return new Interfaces\AJAX();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }
    
    const MODE_AJAX = 1; const MODE_CLI = 2;
    
    abstract public static function getMode() : int;
    abstract public static function isApplicable() : bool;
    
    abstract public function GetInputs(?Config $config) : array;
    
    abstract public function getAddress() : string;    
    abstract public function getUserAgent() : string;
    
    const OUTPUT_PLAIN = 1; const OUTPUT_JSON = 2; const OUTPUT_PRINTR = 3;

    abstract public static function GetDefaultOutmode() : int;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }
    
    public function DisableOutput() : self 
    { 
        $this->outmode = false; return $this; 
    }
    
    private static $retfuncs = array();
    public function RegisterOutputHandler(callable $f) : self 
    { 
        array_push(self::$retfuncs, $f); return $this; 
    }
    
    public function UserOutput(Output $output)
    {
        foreach (self::$retfuncs as $f) $f($output);
    }
    
    public abstract function FinalOutput(Output $output);
}
