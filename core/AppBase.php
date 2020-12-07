<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Transactions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/exceptions/Exceptions.php");

class UnknownActionException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_ACTION"; }
class UnknownConfigException extends Exceptions\ClientErrorException { public $message = "MISSING_CONFIG"; }

abstract class AppBase implements Transactions
{
    protected Main $API;

    public function __construct(Main $API)
    {
        $this->API = $API;
    }
    
    public abstract function Run(Input $input);
    public function Test(Input $input) { }
    
    public abstract static function getUsage() : array;
    public abstract static function getVersion() : array;
    
    public function commit() { }
    public function rollBack() { }
}
