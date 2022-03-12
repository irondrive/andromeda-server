<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

/** The base class for Andromeda exceptions */
abstract class BaseException extends \Exception
{
    public function __construct(?string $details = null) 
    {
        if ($details !== null) $this->message .= ": $details"; 
    }
}

/** An exception that can be created by copying the message from another */
trait Copyable
{
    // must have a constructor with no args for copying
    final public function __construct(?string $details = null) 
    { 
        parent::__construct($details); 
    }
    
    /** Copy the message another exception, converting its type */
    public static function Copy(\Throwable $e) : self
    {
        $e2 = new static(); $e2->message = $e->getMessage(); return $e2;
    }
    
    /** Append the message from another exception, converting its type */
    public static function Append(\Throwable $e) : self
    {
        $e2 = new static(); $e2->message .= ": ".$e->getMessage(); return $e2;
    }
}

/** Base class for server exceptions (errors in server code) */
abstract class ServerException extends BaseException
{
    public $code = 500; public $message = "GENERIC_SERVER_ERROR";
}

/** Represents an non-exception error from PHP */
class PHPError extends ServerException
{
    public function __construct(int $code, string $string, string $file, int $line)
    {
        $this->code = $code; $this->message = $string; $this->file = $file; $this->line = $line;
    }
}

/** Base class for errors caused by the client's request */
abstract class ClientException extends BaseException { }

/** Class for errors caused by the client's request (custom) */
class CustomClientException extends BaseException
{
    public static function Create(int $code, string $message)
    {
        $e = new self(); $e->code = $code; $e->message = $message; return $e;
    }
}

/** Base class for generally invalid client requests (HTTP 400) */
abstract class ClientErrorException extends ClientException { public $code = 400; public $message = "INVALID_REQUEST"; }

/** Base class for client requests that are denied (HTTP 403) */
abstract class ClientDeniedException extends ClientException { public $code = 403; public $message = "ACCESS_DENIED"; }

/** Base class for client requests referencing something that was not found (HTTP 404) */
abstract class ClientNotFoundException extends ClientException { public $code = 404; public $message = "NOT_FOUND"; }

/** Exception indicating something is not implemented - these are not unexpected server errors (HTTP 501) */
class NotImplementedException extends ClientException { public $code = 501; public $message = "NOT_IMPLEMENTED"; }
