<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Core\JSONDecodingException;

/** Base exception indicating a problem with a client parameter */
abstract class SafeParamException extends Exceptions\ClientErrorException { }

/** Exception indicating that the parameter failed sanitization or validation */
class SafeParamInvalidException extends SafeParamException 
{
    public function __construct(string $key, ?int $type) 
    { 
        $type = SafeParam::GetTypeString($type); 
        $this->message = "SAFEPARAM_INVALID_DATA: $type $key"; 
    } 
}

/** Exception indicating that an invalid type was requested */
class SafeParamUnknownTypeException extends SafeParamException 
{ 
    public function __construct(string $type) 
    { 
        $this->message = "SAFEPARAM_TYPE_UNKNOWN: $type"; 
    } 
}

/**
 * Class representing a client input parameter
 * 
 * Provides a consistent interface for sanitizing and validating input values
 * A value is considered null if it is an empty string or the string "null"
 */
class SafeParam
{
    private $key; private $value;    
    
    /** A raw value that does no sanitizing or validating */
    public const TYPE_RAW      = 0;
    
    /** A boolean value, see FILTER_VALIDATE_BOOLEAN */
    public const TYPE_BOOL     = 1;
    
    /** An integer value, see FILTER_VALIDATE_INT */
    public const TYPE_INT      = 2;
    
    /** An integer value, see FILTER_VALIDATE_UINT */
    public const TYPE_UINT     = 3;
    
    /** A float value, see FILTER_VALIDATE_FLOAT */
    public const TYPE_FLOAT    = 4;
    
    /** 
     * A value matching the format randomly generated by Andromeda
     * @see Utilities::Random()
     */
    public const TYPE_RANDSTR  = 5;
    
    /** An alphanumeric (and -_.) value */
    public const TYPE_ALPHANUM = 6; 
    
    /** A value representing a name, allows alphanumerics and -_'(). and space, max length 255 */
    public const TYPE_NAME     = 7;
    
    /** An email address value, see FILTER_VALIDATE_EMAIL */
    public const TYPE_EMAIL    = 8;
    
    /** 
     * A file name value, see details
     * 
     * FILTER_FLAG_STRIP_LOW, max length 255, checks for directory traversal, forbids invalid characters \/?*:;{}
     */
    public const TYPE_FSNAME   = 9;
    
    /**
     * A filesystem path, see details
     * 
     * FILTER_FLAG_STRIP_LOW, max length 65535, forbids invalid characters ?*:;{}
     */
    public const TYPE_FSPATH   = 10;
    
    /** A value containing a hostname, see FILTER_FLAG_STRIP_LOW, FILTER_VALIDATE_DOMAIN and FILTER_FLAG_HOSTNAME */
    public const TYPE_HOSTNAME = 11;
    
    /** A value containing a full URL, see FILTER_VALIDATE_URL */
    public const TYPE_URL      = 12;
    
    /** A string value with a max length of 65535, HTML-escapes special characters, see FILTER_SANITIZE_SPECIAL_CHARS */
    public const TYPE_TEXT     = 13;
    
    /** A value that itself is a collection of SafeParams (an associative array) */
    public const TYPE_OBJECT   = 14;

    /** can be combined with any type to indicate an array of that type */
    public const TYPE_ARRAY = 128;

    const TYPE_STRINGS = array(
        null => 'custom',
        self::TYPE_RAW => 'raw',
        self::TYPE_BOOL => 'bool',
        self::TYPE_INT => 'int',
        self::TYPE_UINT => 'uint',
        self::TYPE_FLOAT => 'float',
        self::TYPE_RANDSTR => 'randstr',
        self::TYPE_ALPHANUM => 'alphanum',
        self::TYPE_NAME => 'name',
        self::TYPE_EMAIL => 'email',
        self::TYPE_FSNAME => 'fsname',
        self::TYPE_FSPATH => 'fspath',
        self::TYPE_HOSTNAME => 'hostname',
        self::TYPE_URL => 'url',
        self::TYPE_TEXT => 'text',
        self::TYPE_OBJECT => 'object',
        self::TYPE_ARRAY => 'array'
    );

    /** Returns the string representing the given param type for printing */
    public static function GetTypeString(?int $type) : string
    {
        $array = self::TYPE_STRINGS[self::TYPE_ARRAY];
        $suffix = ($type & self::TYPE_ARRAY) ? " $array" : "";
        
        if ($type !== null) $type &= ~self::TYPE_ARRAY;
        
        if (!array_key_exists($type, self::TYPE_STRINGS))
            throw new SafeParamUnknownTypeException($type);
            
        return self::TYPE_STRINGS[$type].$suffix;
    }
    
    /** Construct a new SafeParam with the given key and value */
    public function __construct(string $key, $value)
    {
        if (is_string($value)) $value = trim($value);
        
        if ($value === null || $value === "" || $value === "null") $value = null;
        
        if (!preg_match("%^[a-zA-Z0-9_.]+$%", $key))
            throw new SafeParamInvalidException("(key)", SafeParam::TYPE_ALPHANUM);
        
        $this->key = $key; $this->value = $value;
    }
    
    /** Returns the key name of the SafeParam */
    public function GetKey() : string { return $this->key; }
    
    /** Returns a function that checks the max length of the value, for use with GetValue */
    public static function MaxLength(int $len) : callable { 
        return function($val)use($len){ return mb_strlen($val) <= $len; }; }
        
    /** Returns the raw value of the SafeParam */
    public function GetRawValue() { return $this->value; }
    
    /**
     * Gets the value of the parameter, doing filtering/validation, and JSON decoding for objects/arrays
     * 
     * If the parameter is an object type, a SafeParams object will be returned
     * If the parameter is an array type, returns an array of SafeParams
     * @param int $type the requested type of the parameter
     * @param callable $valfunc if not null, check the value against this custom function
     * @throws SafeParamInvalidException if the value fails validation
     * @throws SafeParamUnknownTypeException if an unknown type was requested
     * @return NULL|mixed|SafeParam[]|SafeParams the value of the SafeParam
     */
    public function GetValue(int $type, callable ...$valfuncs)
    {
        $key = $this->key; $value = $this->value;
        
        if ($value === 'null' || !strlen($value)) $value = null;        
        
        if ($type === self::TYPE_RAW)
        {
            // don't do any validation for raw params
        }
        else if ($type === self::TYPE_BOOL)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null)
                throw new SafeParamInvalidException($key, $type);
            else $value = (bool)$value;
        }
        else if ($type === self::TYPE_INT || $type === self::TYPE_UINT)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_INT)) === false)
                throw new SafeParamInvalidException($key, $type);
            else $value = (int)$value;
            
            if ($type === self::TYPE_UINT && $value < 0)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FLOAT)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_FLOAT)) === false)
                throw new SafeParamInvalidException($key, $type);
            else $value = (float)$value;
        }
        else if ($type === self::TYPE_RANDSTR)
        {
            if (!preg_match("%^[a-zA-Z0-9_]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_ALPHANUM)
        {
            if (!preg_match("%^[a-zA-Z0-9\-_.]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_NAME)
        {
            if (mb_strlen($value) >= 256 || !preg_match("%^[a-zA-Z0-9\-_'(). ]+$%",$value))
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_EMAIL)
        {
            if (mb_strlen($value) >= 128 || ($value = filter_var($value, FILTER_VALIDATE_EMAIL)) === false)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FSNAME)
        {
            if (mb_strlen($value) >= 256 || preg_match("%[\\/?*:;{}]+%",$value) ||
                basename($value) !== $value || in_array($value, array('.','..')) ||
               ($value = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)) === false)
                    throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_FSPATH)
        {
            if (strlen($value) >= 65536 || preg_match("%[?*:;{}]+%",$value) ||
                ($value = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW)) === false)
                    throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_HOSTNAME)
        {
            if (mb_strlen($value) >= 256 || 
                ($value = filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) === false)
                    throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_URL)
        {
            if (mb_strlen($value) >= 65536 ||
                ($value = filter_var($value, FILTER_VALIDATE_URL)) === false)
                throw new SafeParamInvalidException($key, $type);
        }
        else if ($type === self::TYPE_TEXT)
        {
            if (strlen($value) >= 65536) 
                throw new SafeParamInvalidException($key, $type);
            
            if (($value = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS)) === false)
                throw new SafeParamInvalidException($key, $type);
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
        
        if ($value !== null) foreach ($valfuncs as $valfunc)
            if (!$valfunc($value)) throw new SafeParamInvalidException($key, null);
        
        return $value;
    }
}


