<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class InvalidParseException extends Exceptions\ServerException { public $message = "PARSE_OUTPUT_INVALID"; }

/** 
 * Represents the output to be shown to the user 
 * 
 * Output consists of a success/failure flag, an HTTP response code, and a response body.
 * The response body can be anything that is JSON encodable.
 */
class Output
{
    private bool $ok; 
    private int $code; 
    private $data; 
    
    private ?array $metrics = null; 
    private ?array $debug = null;
    
    /** Returns whether or not the request succeeded */
    public function GetOK() : bool { return $this->ok; }
    
    /** Returns the HTTP response code for the request */
    public function GetHTTPCode() : int { return $this->code; }
    
    /** Returns the response body to be returned */
    public function GetData() { return $this->data; }
    
    /** Sets performance metrics to be returned */
    public function SetMetrics(?array $metrics) : void { $this->metrics = $metrics; }
    
    /** Sets debugging information to be returned */
    public function SetDebug(?array $debug) : void { $this->debug = $debug; }
    
    /** 
     * Returns the Output object as a client array 
     * @return array if success: `{ok:true, code:int, appdata:mixed}` \
         if failure: `{ok:false, code:int, message:string}`
     */
    public function GetAsArray() : array 
    {
        $array = array('ok'=>$this->ok, 'code'=>$this->code);
        
        $array[($this->ok ? 'appdata' : 'message')] = $this->data;
        
        if ($this->metrics !== null) $array['metrics'] = $this->metrics;
        if ($this->debug !== null) $array['debug'] = $this->debug;

        return $array; 
    }
    
    /**
     * Returns the Output object as a single string (or null if it's more than a string)
     */
    public function GetAsString() : ?string
    {
        $extras = $this->debug !== null || $this->metrics !== null;
        
        if (!is_string($this->data) || $extras) return null;
        
        return $this->data;
    }
    
    private function __construct(bool $ok, int $code, $data)
    {
        // if we only ran a single input, make the output array be that result
        if (is_array($data) && count($data) === 1 && array_key_exists(0,$data)) $data = $data[0];
        
        $this->ok = $ok; $this->code = $code; $this->data = $data;      
    }
    
    /** Constructs an Output object representing a success response */
    public static function Success($data) : Output
    {
        return new Output(true, 200, $data);
    }
    
    /** Constructs an Output object representing a client error, showing the exception and possibly extra debug */
    public static function ClientException(\Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output(false, $e->getCode(), $e->getMessage());
        
        if ($debug !== null) $output->SetDebug($debug);
        
        return $output;
    }
    
    /** Constructs an Output object representing a generic server error, possibly with debug */
    public static function ServerException(?array $debug = null) : Output
    {
        $output = new Output(false, 500, 'SERVER_ERROR');
        
        if ($debug !== null) $output->SetDebug($debug);
        
        return $output;
    }

    /**
     * Parses a response from a remote Andromeda API request into an Output object
     * @param array $data the response data from the remote request
     * @throws InvalidParseException if the response is malformed
     * @return Output the output object constructed from the response
     */
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
            if (!array_key_exists('code',$data) || !array_key_exists('message',$data)) throw new InvalidParseException();
            
            throw Exceptions\ClientException::Create($data['code'], $data['message']);
        }
    }
    
}


