<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/IOFormat/SafeParam.php");

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

/** An exception indicating that the requested parameter name does not exist */
class SafeParamKeyMissingException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_KEY_MISSING: $key"; } }
    
/** An exception indicating that the requested parameter has a null value */
class SafeParamNullValueException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_VALUE_NULL: $key"; } }

/**
 * A thin class that manages a collection of SafeParam objects
 * 
 * This class exists rather than having $params directly in Input
 * because a param can itself contain a collection of other params
 * (see SafeParam::TYPE_OBJECT) represented by another SafeParams
 */
class SafeParams
{
    private array $params = array();
    
    /** Returns true if the named parameter exists */
    public function HasParam(string $key) : bool
    {
        return array_key_exists($key, $this->params);
    }
    
    /** Adds the parameter to this object with the given name and value */
    public function AddParam(string $key, $value) : self
    { 
        $param = new SafeParam($key, $value);
        $this->params[$param->GetKey()] = $param; 
        return $this;
    }
    
    private ?array $logref = null; private int $loglevel;
    
    /** Takes an array reference for logging fetched parameters */
    public function SetLogRef(?array &$logref, int $loglevel) : self {
        $this->logref = &$logref; $this->loglevel = $loglevel; return $this; }
    
    /** Logs the given $data as $key or sets a sub-log reference if it's a SafeParams */
    protected function LogData(?int $minlog, int $type, string $key, $data) : void
    {
        if ($type === SafeParam::TYPE_RAW || $this->logref === null) return;

        if (!$minlog || $minlog > $this->loglevel) return;
        
        if ($data instanceof self)
        {
            $this->logref[$key] = array();
            
            $data->SetLogRef($this->logref[$key], $this->loglevel);
        }
        else $this->logref[$key] = $data;
    }
        
    /** Never log this input parameter (always used for RAW) */
    const PARAMLOG_NEVER = 0;
    
    /** Log the parameter only if details is full */
    const PARAMLOG_ONLYFULL = Config::RQLOG_DETAILS_FULL;
    
    /** Log the parameter if log details are enabled */
    const PARAMLOG_ALWAYS = Config::RQLOG_DETAILS_BASIC;
    
    /**
     * Gets the requested parameter (present and not null)
     * @param int $minlog minimum log level for logging (0 for never) - RAW is never logged!
     * @throws SafeParamKeyMissingException if the parameter is missing
     * @throws SafeParamNullValueException if the parameter is null
     * @see SafeParam::GetValue()
     */
    public function GetParam(string $key, int $type, int $minlog = self::PARAMLOG_ONLYFULL, ?array $values = null, ?callable $valfunc = null)
    {
        if (!$this->HasParam($key)) throw new SafeParamKeyMissingException($key);
        
        $data = $this->params[$key]->GetValue($type, $values, $valfunc);
        if ($data === null) throw new SafeParamNullValueException($key);
        
        $this->LogData($minlog, $type, $key, $data); return $data;
    }
    
    /** Same as GetParam() but returns null if the param is not present */
    public function GetOptParam(string $key, int $type, int $minlog = self::PARAMLOG_ONLYFULL, ?array $values = null, ?callable $valfunc = null)
    {
        if (!$this->HasParam($key)) return null;
        
        $data = $this->params[$key]->GetValue($type, $values, $valfunc);
        if ($data === null) throw new SafeParamNullValueException($key);

        $this->LogData($minlog, $type, $key, $data); return $data;
    }
    
    /** Same as GetParam() but returns null if the param is present and null */
    public function GetNullParam(string $key, int $type, int $minlog = self::PARAMLOG_ONLYFULL, ?array $values = null, ?callable $valfunc = null)
    {
        if (!$this->HasParam($key)) throw new SafeParamKeyMissingException($key);
        
        $data = $this->params[$key]->GetValue($type, $values, $valfunc);
        
        $this->LogData($minlog, $type, $key, $data); return $data;
    }
    
    /** Same as GetParam() but returns null if the param is not present, or is present and null */
    public function GetOptNullParam(string $key, int $type, int $minlog = self::PARAMLOG_ONLYFULL, ?array $values = null, ?callable $valfunc = null)
    {
        if (!$this->HasParam($key)) return null;
        
        $data = $this->params[$key]->GetValue($type, $values, $valfunc);
        
        $this->LogData($minlog, $type, $key, $data); return $data;
    }
    
    /** Returns a plain associative array of each parameter's name mapped to its raw value */
    public function GetClientObject() : array
    {
        return array_map(function($param){ return $param->GetRawValue(); }, $this->params);
    }
}
