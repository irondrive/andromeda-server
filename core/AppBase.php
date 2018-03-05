<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class UnknownActionException extends Exceptions\Client400Exception { public $message = "UNKNOWN_ACTION"; }

abstract class AppBase
{
    protected $API;

    public function __construct(Main $API)
    {
        $this->API = $API;
    }
    
    public abstract function Run(Input $input);
    
}
