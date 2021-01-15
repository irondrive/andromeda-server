<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Core\JSONDecodingException;

abstract class SafeParamException extends Exceptions\ClientErrorException { }

class SafeParamInvalidException extends SafeParamException {
    public function __construct(string $key, ?int $type) { 
        $type = SafeParam::GetTypeString($type); 
        $this->message = "SAFEPARAM_INVALID_DATA: $type $key"; } }

class SafeParamUnknownTypeException extends SafeParamException{ 
    public function __construct(string $type) { 
        $this->message = "SAFEPARAM_TYPE_UNKNOWN: $type"; } }
    
class SafeParamKeyMissingException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_KEY_MISSING: $key"; } }

class SafeParamNullValueException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_VALUE_NULL: $key"; } }
    
class SafeParams
{
    private array $params = array();
    
    public function HasParam(string $key) : bool
    {
        return array_key_exists($key, $this->params);
    }
    
    public function AddParam(string $key, $data) : self
    { 
        $param = new SafeParam($key, $data);
        $this->params[$param->GetKey()] = $param; 
        return $this;
    }
    
    public function AddParamsArray(array $params) : self
    {
        foreach ($params as $key=>$val)
            $this->AddParam($key, $val);
        return $this;
    }
    
    public function GetParamsArray() : array { return $this->params; }
    
    public function GetParam(string $key, int $type, ?callable $usrfunc = null)
    {
        if (!$this->HasParam($key)) throw new SafeParamKeyMissingException($key);
        
        $data = $this->params[$key]->GetValue($type, $usrfunc);
        if ($data !== null) return $data;
        else throw new SafeParamNullValueException($key);
    }
    
    public function TryGetParam(string $key, int $type, ?callable $usrfunc = null)
    {
        if (!$this->HasParam($key)) return null;
        return $this->params[$key]->GetValue($type, $usrfunc);
    }
    
    public function GetClientObject() : array
    {
        return array_map(function($param){ return $param->GetRawValue(); }, $this->params);
    }
}

class SafeParam
{
    private $key; private $value;

    const TYPE_BOOL     = 1;
    const TYPE_INT      = 2;
    const TYPE_FLOAT    = 3;
    const TYPE_RANDSTR  = 4;
    const TYPE_ALPHANUM = 5; 
    const TYPE_NAME     = 6;
    const TYPE_EMAIL    = 7;
    const TYPE_FSNAME   = 8;
    const TYPE_FSPATH   = 9;
    const TYPE_HOSTNAME = 10;
    const TYPE_TEXT     = 11;
    const TYPE_RAW      = 12;
    const TYPE_OBJECT   = 13;

    const TYPE_ARRAY = 16;

    const TYPE_STRINGS = array(
        null => 'custom',
        self::TYPE_BOOL => 'bool',
        self::TYPE_INT => 'int',
        self::TYPE_FLOAT => 'float',
        self::TYPE_RANDSTR => 'randstr',
        self::TYPE_ALPHANUM => 'alphanum',
        self::TYPE_NAME => 'name',
        self::TYPE_EMAIL => 'email',
        self::TYPE_FSNAME => 'fsname',
        self::TYPE_FSPATH => 'fspath',
        self::TYPE_HOSTNAME => 'hostname',
        self::TYPE_TEXT => 'text',
        self::TYPE_RAW => 'raw',
        self::TYPE_OBJECT => 'object',
        self::TYPE_ARRAY => 'array'
    );

    public static function GetTypeString(?int $type) : string
    {
        $array = self::TYPE_STRINGS[self::TYPE_ARRAY];
        $suffix = ($type & self::TYPE_ARRAY) ? " $array" : "";
        
        if ($type !== null) $type &= ~self::TYPE_ARRAY;
        
        if (!array_key_exists($type, self::TYPE_STRINGS))
            throw new SafeParamUnknownTypeException($type);
            
        return self::TYPE_STRINGS[$type].$suffix;
    }
    
    public function __construct(string $key, $value)
    {
        if (is_string($value)) $value = trim($value);
        
        if ($value === null || $value === "" || $value === "null") $value = null;
        
        $key = filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
        
        $this->key = $key; $this->value = $value;
    }
    
    public function GetKey() : string { return $this->key; }
    
    public static function MaxLength(int $len) : callable { 
        return function($val)use($len){ return mb_strlen($val) <= $len; }; }
        
    public function GetRawValue() { return $this->value; }
    
    public function GetValue(int $type, ?callable $usrfunc = null)
    {
        $key = $this->key; $value = $this->value;
        
        if ($value === 'null' || !strlen($value)) $value = null; 
        
        else if ($type === self::TYPE_BOOL)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_INT)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_INT)) === false)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FLOAT)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_FLOAT)) === false)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_RANDSTR)
        {
            if (!preg_match("%^[a-zA-Z0-9_]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_ALPHANUM)
        {
            if (!preg_match("%^[a-zA-Z0-9_.]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_NAME)
        {
            if (mb_strlen($value) >= 256 || !preg_match("%^[a-zA-Z0-9_'(). ]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_EMAIL)
        {
            if (mb_strlen($value) >= 256 || !($value = filter_var($value, FILTER_VALIDATE_EMAIL)))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FSNAME)
        {
            $value = $this->GetValue(self::TYPE_TEXT);
            if (mb_strlen($value) >= 256 || preg_match("%[\\/?*:;{}]+%") ||
                basename($value) !== $value || in_array($value, array('.','..'))) 
                    throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FSPATH)
        {
            $value = $this->GetValue(self::TYPE_TEXT);
            if (strlen($value) >= 65536 || preg_match("%[?*:;{}]+%"))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_HOSTNAME)
        {
            $value = $this->GetValue(self::TYPE_TEXT);
            if (mb_strlen($value) >= 256 || 
                !($value = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_TEXT)
        {
            if (strlen($value) >= 65536) 
                throw new SafeParamInvalidException($key, $type);
            
            $value = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        else if ($type === self::TYPE_RAW) 
        { 

        }
        else if ($type === self::TYPE_OBJECT)
        {
            if (!is_array($value)) 
            {
                try { $value = Utilities::JSONDecode($value); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException($key, $type); }
            }
            
            $params = new SafeParams();
            
            foreach ($value as $key => $val)
            {
                $params->AddParam($key, $val);
            }
            
            $value = $params;
        }
        else if ($type >= self::TYPE_ARRAY)
        {
            if (!is_array($value))
            {
                try { $value = Utilities::JSONDecode($value); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException($key, $type); }
            }
            
            $type &= ~self::TYPE_ARRAY;

            $value = array_map(function($value)use($key,$type){ 
                return (new SafeParam($key, $value))->GetValue($type); }, $value);
        }
        else throw new SafeParamUnknownTypeException($type);
        
        if ($usrfunc !== null && !$usrfunc($value)) throw new SafeParamInvalidException($key, null);
        
        return $value;
    }
}


