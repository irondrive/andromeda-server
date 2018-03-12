<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/interfaces/AJAX.php"); use Andromeda\Core\IOFormat\Interfaces\AJAX;
require_once(ROOT."/core/ioformat/interfaces/CLI.php"); use Andromeda\Core\IOFormat\Interfaces\CLI;

abstract class IOInterface
{
    public static function TryGet() : ?IOInterface
    {
        if (Interfaces\AJAX::isApplicable()) return new Interfaces\AJAX();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }
    
    public const MODE_AJAX = 1; public const MODE_CLI = 2;
    
    abstract public static function getMode() : int;
    abstract public static function isApplicable() : bool;
    abstract public function GetInput() : Input;
    abstract public function WriteOutput(Output $output);
    
    abstract public function getAddress() : string;
    abstract public function getUserAgent() : string;
}
