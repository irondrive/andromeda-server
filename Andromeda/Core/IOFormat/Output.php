<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions\ClientException;

/** 
 * Represents the output to be shown to the user 
 * 
 * Output consists of a success/failure flag, an HTTP response code, and a response body.
 * The response body can be anything that is JSON encodable.
 */
class Output
{
    public const CODE_SUCCESS               = 200;
    public const CODE_CLIENT_ERROR          = 400;
    public const CODE_CLIENT_DENIED         = 403;
    public const CODE_CLIENT_NOTFOUND       = 404;
    public const CODE_SERVER_ERROR          = 500;
    public const CODE_SERVER_UNIMPLEMENTED  = 501;
    public const CODE_SERVER_UNAVAILABLE    = 503;
    
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
     * @return mixed 
     * @throws Exceptions\InvalidOutpropException if $outprop is invalid
     */
    private function NarrowAppdata(?string $outprop = null)
    {
        $appdata = $this->appdata;
        if ($outprop !== null && is_array($appdata))
            foreach (explode('.',$outprop) as $key)
            {
                if (is_array($appdata) && array_key_exists($key,$appdata))
                    $appdata = $appdata[$key];
                else throw new Exceptions\InvalidOutpropException($key);
            }
        return $appdata;
    }
    
    /** 
     * Returns the Output object as a client array 
     * @param ?string $outprop if not null, narrow $appdata to the desired property (format a.b.c.)
     * @return array<mixed> if success: `{ok:true, code:int, appdata:mixed}` \
         if failure: `{ok:false, code:int, message:string}`
     * @throws Exceptions\InvalidOutpropException if $outprop is invalid
     */
    public function GetAsArray(?string $outprop = null) : array 
    {
        $array = array('ok'=>$this->ok, 'code'=>$this->code);

        if ($this->ok) 
            $array['appdata'] = $this->NarrowAppdata($outprop);
        else $array['message'] = $this->message;
        
        if ($this->metrics !== null) $array['metrics'] = $this->metrics;
        if ($this->debug !== null) $array['debug'] = $this->debug;

        return $array; 
    }
    
    /** 
     * Returns the output as a single human-readable string (narrowed to appdata/message, unless metrics/debug)
     * @param ?string $outprop if not null, narrow $appdata to the desired property (format a.b.c)
     * @throws Exceptions\InvalidOutpropException if $outprop is invalid
     */
    public function GetAsString(?string $outprop = null) : string
    {
        if ($this->debug !== null || $this->metrics !== null)
            return print_r($this->GetAsArray($outprop),true);

        if (!$this->ok) return $this->message;

        $appdata = $this->NarrowAppdata($outprop);

        if ($appdata === null)
            return 'SUCCESS';
        else if ($appdata === true)
            return 'TRUE';
        else if ($appdata === false)
            return 'FALSE';
        else if (is_scalar($appdata))
            return (string)$appdata;
        else return print_r($appdata,true);
    }
    
    private function __construct(bool $ok = true, int $code = self::CODE_SUCCESS)
    {
        $this->ok = $ok; 
        $this->code = $code;
    }
    
    /** 
     * Constructs an Output object representing a success response 
     * @param mixed $appdata
     */
    public static function Success($appdata) : Output
    {
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
        $output = new Output(false, self::CODE_SERVER_ERROR);
        $output->message = 'SERVER_ERROR';
        
        if ($debug !== null) 
            $output->debug = $debug;
        
        return $output;
    }

    /**
     * Parses a response from a remote Andromeda API request into an Output object
     * @param array<mixed> $data the response data from the remote request
     * @throws Exceptions\InvalidParseException if the response is malformed
     * @return Output the output object constructed from the response
     */
    public static function ParseArray(array $data) : Output
    {
        if (!array_key_exists('ok',$data) || !array_key_exists('code',$data)) 
            throw new Exceptions\InvalidParseException();
        
        if (!is_bool($data['ok']) || !is_int($data['code'])) 
            throw new Exceptions\InvalidParseException();

        $ok = $data['ok']; $code = $data['code'];

        if ($ok === true)
        {
            if (!array_key_exists('appdata',$data)) 
                throw new Exceptions\InvalidParseException();

            $output = new Output($ok, $code); 
            $output->appdata = $data['appdata']; 
            return $output;
        }
        else
        {
            if (!array_key_exists('message',$data)) 
                throw new Exceptions\InvalidParseException();
            
            if (!is_string($data['message'])) 
                throw new Exceptions\InvalidParseException();
            
            throw new ClientException(
                $data['message'], $code);
        }
    }   
}

