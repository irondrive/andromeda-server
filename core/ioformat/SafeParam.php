<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Core\JSONDecodingException;

class SafeParamException extends Exceptions\Client400Exception { }

class SafeParamInvalidException extends SafeParamException {
    public function __construct(string $type) { $this->message = "SAFEPARAM_INVALID_DATA: $type"; } }

class SafeParamUnknownTypeException extends SafeParamException{ 
    public function __construct(string $type) { $this->message = "SAFEPARAM_TYPE_UNKNOWN: $type"; } }
    
class SafeParamKeyMissingException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_KEY_MISSING: $key"; } }
    
class SafeParamKeyTypeException extends SafeParamException {
    public function __construct(string $key) { $this->message = "SAFEPARAM_TYPE_MISMATCH: $key"; } }

class SafeParams
{
    private $params = array();
    
    public function HasParam(string $key)
    {
        return isset($this->params[$key]);
    }
    
    public function AddParam(string $type, string $key, $data) 
    { 
        $key = filter_var($key, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
        $this->params[$key] = new SafeParam($type, $data); 
    }
    
    public function GetParam(string $key, int $type)
    {
        if (!isset($this->params[$key])) throw new SafeParamKeyMissingException($key);
        
        if ($this->params[$key]->getType() != $type)
            throw new SafeParamKeyTypeException("$key was ".$this->params[$key]->getType()." expected $type");
            
        return $this->params[$key]->getData();
    }

    public function GetParamsArray() { return $this->params; }
    
    public function TryGetParam(string $key, int $type)
    {
        if (!isset($this->params[$key])) return null;
        if ($this->params[$key]->getType() !== $type) return null;
        return $this->params[$key]->getData();
    }
}

class SafeParam
{
    private $data; private $type;
    
    public function getData() { return $this->data; }
    public function getType() : int { return $this->type; }
    
    const TYPE_ID       = 1;
    const TYPE_BOOL     = 2;
    const TYPE_INT      = 3;
    const TYPE_FLOAT    = 4;    
    const TYPE_ALPHANUM = 5; 
    const TYPE_ALNUMEXT = 6;
    const TYPE_EMAIL    = 7;
    const TYPE_TEXT     = 8;
    const TYPE_RAW      = 9;
    const TYPE_OBJECT   = 10;

    const TYPE_SINGLE = 0;
    const TYPE_ARRAY = 16;

    const TYPE_STRINGS = array(
        'id'        => self::TYPE_ID,
        'bool'      => self::TYPE_BOOL,
        'int'       => self::TYPE_INT,
        'float'     => self::TYPE_FLOAT,
        'alphanum'  => self::TYPE_ALPHANUM,
        'alnumext'  => self::TYPE_ALNUMEXT,
        'email'     => self::TYPE_EMAIL,
        'text'      => self::TYPE_TEXT,
        'raw'       => self::TYPE_RAW,
        'object'    => self::TYPE_OBJECT,
    );

    public function GetTypeString() : string
    {
        $str = ($this->type >= self::TYPE_ARRAY) ? "+" : "";
        return $str.array_flip(self::TYPE_STRINGS)[$this->type % self::TYPE_ARRAY];
    }
    
    public function __construct(string $typestr, $data)
    {
        if (strlen($typestr) > 0 && $typestr[0] == '+') {
            $this->type = self::TYPE_ARRAY; $typestr = substr($typestr,1); }
        else { $this->type = self::TYPE_SINGLE; }
        
        if (array_key_exists($typestr, self::TYPE_STRINGS))
        {
            $this->type |= self::TYPE_STRINGS[$typestr];
        }
        else throw new SafeParamUnknownTypeException($typestr);
            
        if (is_string($data)) $data = trim($data);
 
        if ($this->type === self::TYPE_BOOL)
        {
            if (($this->data = filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null)
                throw new SafeParamInvalidException($this->GetTypeString());
        }
        else if ($this->type === self::TYPE_INT)
        {
            if (($this->data = filter_var($data, FILTER_VALIDATE_INT)) === false)
                throw new SafeParamInvalidException($this->GetTypeString());
        }
        else if ($this->type === self::TYPE_FLOAT)
        {
            if (($this->data = filter_var($data, FILTER_VALIDATE_FLOAT)) === false)
                throw new SafeParamInvalidException($this->GetTypeString());
        }
        else if ($this->type === self::TYPE_ALPHANUM || $this->type === self::TYPE_ID)
        {
            if (!preg_match("%^[a-zA-Z0-9_]+$%",$data) || strlen($data) > 255)
                throw new SafeParamInvalidException($this->GetTypeString());
            $this->data = $data;
        }
        else if ($this->type === self::TYPE_ALNUMEXT)
        {
            if (!preg_match("%^[a-zA-Z0-9_'() ]+$%",$data) || strlen($data) > 255)
                throw new SafeParamInvalidException($this->GetTypeString());
            $this->data = $data;
        }
        else if ($this->type === self::TYPE_EMAIL)
        {
            if (!($this->data = filter_var($data, FILTER_VALIDATE_EMAIL)) || strlen($data) > 255)
                throw new SafeParamInvalidException($this->GetTypeString());
        }
        else if ($this->type === self::TYPE_TEXT)
        {
            $this->data = filter_var($data, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);  
        }
        else if ($this->type === self::TYPE_OBJECT)
        {
            if (!is_array($data)) 
            {
                try { $data = Utilities::JSONDecode($data); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException($this->GetTypeString()); }
            }
            
            $output = new SafeParams();
            
            foreach (array_keys($data) as $key)
            {
                $param = explode('_',$key,2);               
                
                if (count($param) != 2) throw new SafeParamInvalidException($this->GetTypeString()); 
                
                $output->AddParam($param[0], $param[1], $data[$key]);
            }
            
            $this->data = $output;
        }
        else if ($this->type >= self::TYPE_ARRAY)
        {
            if (!is_array($data))
            {
                try { $data = Utilities::JSONDecode($data); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException($this->GetTypeString()); }
            }

            $this->data = array_map(function($value) use ($typestr){ return (new SafeParam($typestr, $value))->getData(); }, $data);
        }
        else { $this->data = $data; }
    }   
}


