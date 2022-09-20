<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions\ClientException;

require_once(ROOT."/Core/IOFormat/Exceptions.php");

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
    /** @var mixed */
    private $appdata; 
    /** @var ?array<mixed> */
    private ?array $metrics = null;
    /** @var ?array<mixed> */
    private ?array $debug = null;
    
    /** Returns whether or not the request succeeded */
    public function isOK() : bool { return $this->ok; }
    
    /** Returns the response code for the request */
    public function GetCode() : int { return $this->code; }
    
    /** Returns the error message string (only if not isOK) */
    public function GetMessage() : string { return $this->message; }
    
    /** 
     * Returns the response body to be returned (only if isOK) 
     * @return mixed
     */
    public function GetAppdata() { return $this->appdata; }
    
    /** 
     * Sets performance metrics to be returned
     * @param ?array<mixed> $metrics 
     * @return $this
     */
    public function SetMetrics(?array $metrics) : self { 
        $this->metrics = $metrics; return $this; }
    
    /** 
     * Returns the Output object as a client array 
     * @return array<mixed> if success: `{ok:true, code:int, appdata:mixed}` \
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
        if ($this->debug !== null || $this->metrics !== null) return null;
        
        if ($this->ok)
        {
            if ($this->appdata === null)
                $retval = 'SUCCESS';
            else if ($this->appdata === true)
                $retval = 'TRUE';
            else if ($this->appdata === false)
                $retval = 'FALSE';
            else $retval = $this->appdata;
            
            return print_r($retval, true);
        }
        else return print_r($this->message, true);
    }
    
    private function __construct(bool $ok = true, int $code = 200)
    {
        $this->ok = $ok; 
        $this->code = $code;
    }
    
    /** 
     * Constructs an Output object representing a success response 
     * @param array<mixed> $appdata
     */
    public static function Success(array $appdata) : Output
    {
        // if we only ran a single input, make the output array be that result
        if (count($appdata) === 1 && array_key_exists(0,$appdata)) 
            $appdata = $appdata[0];
        
        $output = new Output();
        $output->appdata = $appdata; 
        return $output;
    }
    
    /** 
     * Constructs an Output object representing a client error, showing the exception and possibly extra debug 
     * @param ?array<mixed> $debug
     */
    public static function ClientException(ClientException $e, ?array $debug = null) : Output
    {
        $output = new Output(false, $e->getCode());
        $output->message = $e->getMessage();
        
        if ($debug !== null) 
            $output->debug = $debug;
        
        return $output;
    }
    
    /** 
     * Constructs an Output object representing a non-client error, possibly with debug 
     * @param ?array<mixed> $debug
     */
    public static function ServerException(?array $debug = null) : Output
    {
        // hide the code/message by default
        $output = new Output(false, 500);
        $output->message = 'SERVER_ERROR';
        
        if ($debug !== null) 
            $output->debug = $debug;
        
        return $output;
    }

    /**
     * Parses a response from a remote Andromeda API request into an Output object
     * @param array<mixed> $data the response data from the remote request
     * @throws InvalidParseException if the response is malformed
     * @return Output the output object constructed from the response
     */
    public static function ParseArray(array $data) : Output
    {
        if (!array_key_exists('ok',$data) || !array_key_exists('code',$data)) 
            throw new InvalidParseException();
        
        if (!is_bool($data['ok']) || !is_int($data['code'])) 
            throw new InvalidParseException();

        $ok = (bool)$data['ok']; 
        $code = (int)$data['code'];

        if ($ok === true)
        {
            if (!array_key_exists('appdata',$data)) 
                throw new InvalidParseException();

            $output = new Output($ok, $code); 
            $output->appdata = $data['appdata']; 
            return $output;
        }
        else
        {
            if (!array_key_exists('message',$data)) 
                throw new InvalidParseException();
            
            if (!is_string($data['message'])) 
                throw new InvalidParseException();
            
            throw new ClientException(
                (string)$data['message'], $code);
        }
    }   
}

