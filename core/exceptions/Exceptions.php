<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

abstract class ClientException extends \Exception { 
	public function __construct(?string $message = null) { if ($message !== null) $this->message = $message; }
}

class ClientErrorException extends ClientException { public $code = 400; public $message = "INVALID_REQUEST"; }
class ClientDeniedException extends ClientException { public $code = 403; public $message = "ACCESS_DENIED"; }
class ClientNotFoundException extends ClientException { public $code = 404; public $message = "NOT_FOUND"; }

class ServerException extends \Exception { 
    public $code = 500; public $message = "GENERIC_SERVER_ERROR";
    public function __construct(string $details = "") { $this->details = $details; } 
    public function getDetails() : string { return $this->details; }
}

class PHPException extends ServerException {    
    public function __construct(int $code, string $string, string $file, $line) {
        $this->code = $code; $this->details = $string; $this->file = $file; $this->line = $line;
        try { $this->message = array_flip(array_slice(get_defined_constants(true)['Core'], 0, 16, true))[$code]; }
        catch (\Throwable $e) { $this->message = "PHP_GENERIC_ERROR"; }
    } }

class NotImplementedException extends ClientException { 
    public $code = 501; public $message = "NOT_IMPLEMENTED"; 
    public function __construct(string $details = "") { $this->message = $this->message.($details?": $details":""); } 
}
