<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/interfaces/AJAX.php"); use Andromeda\Core\IOFormat\Interfaces\AJAX;
require_once(ROOT."/core/ioformat/interfaces/CLI.php"); use Andromeda\Core\IOFormat\Interfaces\CLI;

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
    
    abstract public function getAddress() : string;    
    abstract public function getUserAgent() : string;
    
    const OUTPUT_NONE = 0; const OUTPUT_PLAIN = 1; const OUTPUT_JSON = 2; const OUTPUT_PRINTR = 3;

    public function SetOutmode(int $outmode) : self { $this->outmode = $outmode; return $this; }
    abstract public static function GetDefaultOutmode() : int;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }
    
    abstract public function GetInputs(?Config $config) : array;
    abstract public function WriteOutput(Output $output);
}
