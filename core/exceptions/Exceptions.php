<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/ErrorManager.php");

abstract class BaseException extends \Exception {    
    public static function Copy(\Throwable $e) : self { 
        $e2 = new static(); $e2->message = $e->getMessage(); return $e2;
    }
}

abstract class ClientException extends BaseException { 
    public static function Create(int $code, string $message) {
        $e = new self(); $e->code = $code; $e->message = $message; return $e;
    }
}

class ClientErrorException extends ClientException { public $code = 400; public $message = "INVALID_REQUEST"; }
class ClientDeniedException extends ClientException { public $code = 403; public $message = "ACCESS_DENIED"; }
class ClientNotFoundException extends ClientException { public $code = 404; public $message = "NOT_FOUND"; }
class NotImplementedException extends ClientException { public $code = 501; public $message = "NOT_IMPLEMENTED"; }

class ServerException extends BaseException { 
    
    public $code = 0; public $message = "GENERIC_SERVER_ERROR";
    
    public function __construct(string $details = null) { if ($details) $this->message .= ": $details"; }
}

class PHPError extends ServerException 
{    
    public function __construct(int $code, string $string, string $file, $line) 
    {
        $this->code = $code; $this->message = $string; $this->file = $file; $this->line = $line;
    } 
}
