<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Core\JSONDecodingException;

class SafeParamInvalidException extends Exceptions\Client400Exception  { public $message = "SAFEPARAM_INVALID_DATA"; }

class SafeParamUnknownException extends Exceptions\Client400Exception  { 
    public function __construct(string $type) { $this->message = "SAFEPARAM_TYPE_UNKNOWN: $type"; } }
    
class SafeParamKeyMissingException extends Exceptions\Client400Exception {
    public function __construct(string $key) { $this->message = "SAFEPARAM_KEY_MISSING: $key"; } }
    
class SafeParamKeyTypeException extends Exceptions\Client400Exception {
    public function __construct(string $key) { $this->message = "SAFEPARAM_TYPE_MISMATCH: $key"; } }

class SafeParams
{
    private $params = array();
    
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
    
    public function TryGetParam(string $key, int $type)
    {
        if (!isset($this->params[$key])) return null;
        if ($this->params[$key]->getType() != $type) return null;
        return $this->params[$key]->getData();
    }
}

class SafeParam
{
    private $data; private $type;
    
    public function getData() { return $this->data; }
    public function getType() : int { return $this->type; }
    
    const TYPE_BOOL     = 1;
    const TYPE_INT      = 2;
    const TYPE_FLOAT    = 3;    
    const TYPE_ALPHANUM = 4; 
    const TYPE_TEXT     = 5;
    const TYPE_RAW      = 6;
    const TYPE_EMAIL    = 7;
    const TYPE_OBJECT   = 8;
    const TYPE_ID = self::TYPE_ALPHANUM;
    
    const TYPE_SINGLE = 0;
    const TYPE_ARRAY = 16;
    
    public function __construct(string $type, $data)
    {   
        if (strlen($type) > 0 && $type[0] == '+') { 
            $this->type = self::TYPE_ARRAY; $type = substr($type,1); }
        else { $this->type = self::TYPE_SINGLE; }
        
        if ($type == 'bool')       $this->type |= self::TYPE_BOOL;
        else if ($type == 'int')        $this->type |= self::TYPE_INT;
        else if ($type == 'float')      $this->type |= self::TYPE_FLOAT;
        else if ($type == 'alphanum')   $this->type |= self::TYPE_ALPHANUM;
        else if ($type == 'text')       $this->type |= self::TYPE_TEXT;
        else if ($type == 'raw')        $this->type |= self::TYPE_RAW;
        else if ($type == 'email')      $this->type |= self::TYPE_EMAIL;
        else if ($type == 'object')     $this->type |= self::TYPE_OBJECT;
        
        else { throw new SafeParamUnknownException($type); }  
        
        $this->data = self::filterData($this->type, $data);       
    }
    
    public static function filterData(int $type, $data)
    {
        if (is_string($data)) $data = trim($data);
        
        if ($type == self::TYPE_BOOL)
        {
            if (($data = filter_var($data, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) != null) {
                throw new SafeParamInvalidException(); }
        }
        else if ($type == self::TYPE_INT)
        {
            if (($data = filter_var($data, FILTER_VALIDATE_INT)) === false) {
                throw new SafeParamInvalidException(); }
        }
        else if ($type == self::TYPE_FLOAT)
        {
            if (($data = filter_var($data, FILTER_VALIDATE_FLOAT)) === false) {
                throw new SafeParamInvalidException(); }
        }
        else if ($type == self::TYPE_ALPHANUM)
        {
            if (!preg_match("%^[a-zA-Z0-9_]+$%",$data)) {
                throw new SafeParamInvalidException(); }
        }
        else if ($type == self::TYPE_TEXT)
        {
            $data = filter_var($data, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);   
        }
        else if ($type == self::TYPE_EMAIL)
        {
            if (!($data = filter_var($data, FILTER_VALIDATE_EMAIL))) {
                throw new SafeParamInvalidException(); }
        }
        else if ($type == self::TYPE_OBJECT)
        {
            if (!is_array($data)) 
            {
                try { $data = Utilities::JSONDecode($data); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException(); }
            }

            $output = new SafeParams();
            
            foreach (array_keys($data) as $key)
            {
                $param = explode('_',$key,2);               
                
                if (count($param) != 2) throw new SafeParamInvalidException();
                
                $output->AddParam($param[0], $param[1], $data[$key]);
            }
            
            return $output;
        }
        else if ($type >= self::TYPE_ARRAY)
        {
            if (!is_array($data))
            {
                try { $data = Utilities::JSONDecode($data); }
                catch (JSONDecodingException $e) { throw new SafeParamInvalidException(); }
            }
            
            $type -= self::TYPE_ARRAY;
            
            for ($i = 0; $i < count($data); $i++)
            {
                $data[$i] = self::filterData($type, $data[$i]);
            }
        }
        
        return $data;
    }   

}
