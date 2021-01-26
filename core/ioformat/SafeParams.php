<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/ioformat/SafeParam.php");

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** An exception indicating that the requested parameter name does not exist */
class SafeParamKeyMissingException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_KEY_MISSING: $key"; } }
    
/** An exception indicating that the requested parameter has a null value */
class SafeParamNullValueException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_VALUE_NULL: $key"; } }

/**
 * A thin class that manages a collection of SafeParam objects
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
    
    /**
     * Gets the requested parameter
     * @param string $key the name of the parameter
     * @param int $type the type of the parameter
     * @param callable $usrfunc optional function for custom validation
     * @throws SafeParamKeyMissingException if the parameter is not present
     * @throws SafeParamNullValueException if the parameter is null
     * @see SafeParam::GetValue()
     */
    public function GetParam(string $key, int $type, ?callable $usrfunc = null)
    {
        if (!$this->HasParam($key)) throw new SafeParamKeyMissingException($key);
        
        $data = $this->params[$key]->GetValue($type, $usrfunc);
        if ($data !== null) return $data;
        else throw new SafeParamNullValueException($key);
    }
    
    /** Same as GetParam() but returns null rather than throwing exceptions */
    public function TryGetParam(string $key, int $type, ?callable $usrfunc = null)
    {
        if (!$this->HasParam($key)) return null;
        return $this->params[$key]->GetValue($type, $usrfunc);
    }
    
    /** Returns a plain associative array of each parameter's name mapped to its raw value */
    public function GetClientObject() : array
    {
        return array_map(function($param){ return $param->GetRawValue(); }, $this->params);
    }
}
