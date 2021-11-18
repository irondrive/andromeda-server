<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

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
    
    private string $message;
    private $appdata; 
    
    private ?array $metrics = null; 
    private ?array $debug = null;
    
    /** Returns whether or not the request succeeded */
    public function isOK() : bool { return $this->ok; }
    
    /** Returns the HTTP response code for the request */
    public function GetHTTPCode() : int { return $this->code; }
    
    /** Returns the error message string (only if not isOK) */
    public function GetMessage() : string { return $this->message; }
    
    /** Returns the response body to be returned (only if isOK) */
    public function GetAppdata() { return $this->appdata; }
    
    /** Sets performance metrics to be returned */
    public function SetMetrics(?array $metrics) : self { $this->metrics = $metrics; return $this; }
    
    /** 
     * Returns the Output object as a client array 
     * @return array if success: `{ok:true, code:int, appdata:mixed}` \
         if failure: `{ok:false, code:int, message:string}`
     */
    public function GetAsArray() : array 
    {
        $array = array('ok'=>$this->ok, 'code'=>$this->code);
        
        if ($this->ok) 
             $array['appdata'] = $this->appdata;
        else $array['message'] = $this->message;
        
        if ($this->metrics !== null) $array['metrics'] = $this->metrics;
        if ($this->debug !== null) $array['debug'] = $this->debug;

        return $array; 
    }
    
    /** Returns the appdata as a single string (or null if not possible) */
    public function GetAsString() : ?string
    {
        $extras = $this->debug !== null || $this->metrics !== null;
        
        $data = $this->ok ? $this->appdata : $this->message;
        
        if (!is_string($data) || $extras) return null;
        
        return $data;
    }
    
    private function __construct(bool $ok, int $code)
    {
        $this->ok = $ok; $this->code = $code;   
    }
    
    /** Constructs an Output object representing a success response */
    public static function Success(array $appdata) : Output
    {
        // if we only ran a single input, make the output array be that result
        if (count($appdata) === 1 && array_key_exists(0,$appdata)) $appdata = $appdata[0];
        
        $output = new Output(true, 200); $output->appdata = $appdata; return $output;
    }
    
    /** Constructs an Output object representing a client error, showing the exception and possibly extra debug */
    public static function ClientException(\Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output(false, $e->getCode());
        
        $output->message = $e->getMessage();
        
        if ($debug !== null) $output->debug = $debug;
        
        return $output;
    }
    
    /** Constructs an Output object representing a server error, possibly with debug */
    public static function ServerException(\Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output(false, $e->getCode());
        
        $output->message = 'SERVER_ERROR';
        
        if ($debug) $output->debug = $debug;
        
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
        
        if (!is_bool($data['ok']) || !is_int($data['code'])) throw new InvalidParseException();

        $ok = (bool)$data['ok']; $code = (int)$data['code'];

        if ($ok === true)
        {
            if (!array_key_exists('appdata',$data)) throw new InvalidParseException();

            $output = new Output($ok, $code); $output->appdata = $data['appdata']; return $output;
        }
        else
        {
            if (!array_key_exists('message',$data)) throw new InvalidParseException();
            
            if (!is_string($data['message'])) throw new InvalidParseException();
            
            throw Exceptions\CustomClientException::Create($code, (string)$data['message']);
        }
    }   
}

