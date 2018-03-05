<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

class ClientException extends \Exception { public $code = 500; public $message = "UNKNOWN_CLIENT_EXCEPTION"; }

class Client400Exception extends ClientException { public $code = 400; public $message = "INVALID_REQUEST"; }
class Client403Exception extends ClientException { public $code = 403; public $message = "ACCESS_DENIED"; }
class Client404Exception extends ClientException { public $code = 404; public $message = "NOT_FOUND"; }

class ServerException extends \Exception { 
    public $code = 500; public $message = "GENERIC_SERVER_ERROR";
    public function __construct(string $details = "") { $this->details = $details; } 
    public function getDetails() : string { return $this->details; }
}

class PHPException extends ServerException {    
    public function __construct(int $code, string $string, string $file, $line) {
        $this->code = $code; $this->details = $string; $this->file = $file; $this->line = $line;
        try { $this->message = array_flip(array_slice(get_defined_constants(true)['Core'], 0, 16, true))[$code]; }
            catch (\Throwable $e) { $this->message = "PHP_ERROR"; }
    } }

class NotImplementedException extends ClientException   { public $code = 501; public $message = "NOT_IMPLEMENTED"; }
