<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class InvalidParseException extends Exceptions\ServerException { public $message = "PARSE_OUTPUT_INVALID"; }
class InvalidOutputException extends Exceptions\ServerException { public $message = "CANNOT_STRINGIFY_OUTPUT"; }

class Output
{
    private $ok; private $code; private $data; private $metrics; private $debug;
    
    public function GetOK() : bool { return $this->ok; }
    public function GetHTTPCode() : int { return $this->code; }
    
    public function GetData() { return $this->data; }

    public function SetMetrics(array $metrics) { $this->metrics = $metrics; }
    public function SetDebug(array $debug) { $this->debug = $debug; }
    
    public function GetAsArray(bool $debug = true) : array 
    {
        $array = array('ok'=>$this->ok, 'code'=>$this->code);
        
        $array[($this->ok ? 'appdata' : 'message')] = $this->data;
        
        if ($this->metrics !== null && $debug) $array['metrics'] = $this->metrics;
        if ($this->debug !== null && $debug) $array['debug'] = $this->debug;

        return $array; 
    }
    
    public function GetAsString() : string
    {
        if (!is_string($this->data) || $this->debug != null) 
            throw new InvalidOutputException();
        return $this->data;
    }
    
    private function __construct(bool $ok, int $code, $data)
    {        
        if (is_array($data) && count($data) === 1 && array_key_exists(0,$data)) $data = $data[0];
        
        $this->ok = $ok; $this->code = $code; $this->data = $data;      
    }
    
    public static function Success($data) : Output
    {
        return new Output(true, 200, $data);
    }
    
    public static function ClientException(\Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output(false, $e->getCode(), $e->getMessage());
        
        if ($debug !== null) $output->SetDebug($debug);
        
        return $output;
    }
    
    public static function ServerException(?array $debug = null) : Output
    {
        $output = new Output(false, 500, 'SERVER_ERROR');
        
        if ($debug !== null) $output->SetDebug($debug);
        
        return $output;
    }

    public static function ParseArray(array $data) : Output
    {
        if (!array_key_exists('ok',$data) || !array_key_exists('code',$data)) throw new InvalidParseException();

        $ok = $data['ok']; $code = $data['code'];

        if ($ok === true)
        {
            if (!array_key_exists('appdata',$data)) throw new InvalidParseException();

            return new Output($ok, $code, $data['appdata']);
        }
        else
        {
            if (!array_key_exists('message',$data)) throw new InvalidParseException();

            if ($code >= 500)       throw new Exceptions\ServerException($data['message']);
            else if ($code == 404)  throw new Exceptions\Client404Exception($data['message']);
            else if ($code == 403)  throw new Exceptions\CLient403Exception($data['message']);
            else if ($code == 400)  throw new Exceptions\Client400Exception($data['message']);
            else throw new InvalidParseException();
        }
    }
    
}


