<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Transactions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class UnknownActionException extends Exceptions\Client400Exception { public $message = "UNKNOWN_ACTION"; }

abstract class AppBase implements Transactions
{
    protected $API;

    public function __construct(Main $API)
    {
        $this->API = $API;
    }
    
    public abstract function Run(Input $input);
    public static function Test(Main $API, Input $input) { }
    
    public static function getVersion() : array { return array(0,0,0); }
    
    public function commit() { }
    public function rollBack() { }
}
