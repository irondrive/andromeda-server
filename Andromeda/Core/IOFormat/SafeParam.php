<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Core\JSONDecodingException;

/** Base exception indicating a problem with a client parameter */
abstract class SafeParamException extends Exceptions\ClientErrorException { }

/** Exception indicating that an invalid type was requested */
class SafeParamUnknownTypeException extends SafeParamException { public $message = "SAFEPARAM_TYPE_UNKNOWN"; }

/** Exception indicating that the parameter failed sanitization or validation */
class SafeParamInvalidException extends SafeParamException 
{
    public function __construct(string $key, ?int $type = null) 
    {
        $this->message = "SAFEPARAM_INVALID_VALUE: $key";
        
        if ($type !== null) $this->message .=
            " must be ".SafeParam::GetTypeString($type); 
    } 
}

/** Exception indicating an invalid enum-based paramter */
class SafeParamInvalidEnumException extends SafeParamInvalidException
{
    public function __construct(string $key, array $values)
    {
        $this->message = "SAFEPARAM_INVALID_VALUE: $key must be ".implode('|',$values);
    }
}

/** Exception indicating the int-type setup code is invalid */
class IntTypeErrorException extends Exceptions\ServerException { public $message = "UNKNOWN_INT_TYPE"; }

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
    public const TYPE_INT      = 2; private const TYPE_INT_FIRST = self::TYPE_INT;
    public const TYPE_INT32    = 3;
    public const TYPE_INT16    = 4;
    public const TYPE_INT8     = 5;
    
    /** An integer value, see FILTER_VALIDATE_UINT */
    public const TYPE_UINT     = 6;
    public const TYPE_UINT32   = 7;
    public const TYPE_UINT16   = 8;
    public const TYPE_UINT8    = 9; private const TYPE_INT_LAST = self::TYPE_UINT8;
    
    /** A float value, see FILTER_VALIDATE_FLOAT */
    public const TYPE_FLOAT    = 10;
    
    /** 
     * A value matching the format randomly generated by Andromeda
     * @see Utilities::Random()
     */
    public const TYPE_RANDSTR  = 11;
    
    /** An alphanumeric (and -_.) value */
    public const TYPE_ALPHANUM = 12; 
    
    /** A value representing a name, allows alphanumerics and -_'(). and space, max length 255 */
    public const TYPE_NAME     = 13;
    
    /** An email address value, see FILTER_VALIDATE_EMAIL */
    public const TYPE_EMAIL    = 14;
    
    /** 
     * A file name value, see details
     * 
     * FILTER_FLAG_STRIP_LOW, max length 255, checks for directory traversal, forbids invalid characters \/?*:;{}
     */
    public const TYPE_FSNAME   = 15;
    
    /**
     * A filesystem path, see details
     * 
     * FILTER_FLAG_STRIP_LOW, max length 65535, forbids invalid characters ?*:;{}
     */
    public const TYPE_FSPATH   = 16;
    
    /** A value containing a hostname, see FILTER_FLAG_STRIP_LOW, FILTER_VALIDATE_DOMAIN and FILTER_FLAG_HOSTNAME */
    public const TYPE_HOSTNAME = 17;
    
    /** A value containing a full URL, see FILTER_VALIDATE_URL */
    public const TYPE_URL      = 18;
    
    /** A string value with a max length of 65535, HTML-escapes special characters, see FILTER_SANITIZE_SPECIAL_CHARS */
    public const TYPE_TEXT     = 19;
    
    /** A value that itself is a collection of SafeParams (an associative array) */
    public const TYPE_OBJECT   = 20;

    /** can be combined with any type to indicate an array of that type */
    public const TYPE_ARRAY = 128;

    const TYPE_STRINGS = array(
        self::TYPE_RAW => 'raw',
        self::TYPE_BOOL => 'bool',
        self::TYPE_INT => 'int',
        self::TYPE_INT32 => 'int32',
        self::TYPE_INT16 => 'int16',
        self::TYPE_INT8 => 'int8',
        self::TYPE_UINT => 'uint',
        self::TYPE_UINT32 => 'uint32',
        self::TYPE_UINT16 => 'uint16',
        self::TYPE_UINT8 => 'uint8',
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
    public static function GetTypeString(int $type) : string
    {
        $array = self::TYPE_STRINGS[self::TYPE_ARRAY];
        $suffix = ($type & self::TYPE_ARRAY) ? " $array" : "";
        
        $type &= ~self::TYPE_ARRAY;
        
        if (!array_key_exists($type, self::TYPE_STRINGS))
            throw new SafeParamUnknownTypeException((string)$type);
            
        return self::TYPE_STRINGS[$type].$suffix;
    }
    
    /** Construct a new SafeParam with the given key and value */
    public function __construct(string $key, $value)
    {        
        if ($value === null || $value === "" || $value === "null") $value = null;
        
        if (!preg_match("%^[a-zA-Z0-9_.]+$%", $key))
            throw new SafeParamInvalidException("(key)", SafeParam::TYPE_ALPHANUM);
        
        $this->key = $key; $this->value = $value;
    }
    
    /** Returns the key name of the SafeParam */
    public function GetKey() : string { return $this->key; }
    
    /** Returns the raw value of the SafeParam - caution! */
    public function GetRawValue() { return $this->value; }
    
    /** Returns a function that checks the max length of the value, for use with GetValue */
    public static function MaxLength(int $len) : callable { 
        return function($val)use($len){ return mb_strlen($val) <= $len; }; }

    /**
     * Gets the value of the parameter, doing filtering/validation, and JSON decoding for objects/arrays
     * 
     * If the parameter is an object type, a SafeParams object will be returned
     * If the parameter is an array type, returns an array of SafeParams
     * @param int $type the requested type of the parameter
     * @param ?array $values if not null, the value must be in this array
     * @param callable $valfunc if not null, check the value against this function
     * @throws SafeParamInvalidException if the value fails type validation
     * @throws SafeParamInvalidEnumException if the value is not in the given array
     * @throws SafeParamUnknownTypeException if an unknown type was requested
     * @return NULL|mixed|SafeParam[]|SafeParams the value of the SafeParam
     */
    public function GetValue(int $type, ?array $values = null, ?callable $valfunc = null)
    {
        $key = $this->key; $value = $this->value;

        if ($type !== self::TYPE_RAW && is_string($value)) $value = trim($value);
        
        if ($value === null && $type === self::TYPE_BOOL) $value = true; // flag

        else if ($value === null || $type === self::TYPE_RAW)
        {
            // do nothing for null or raw params
        }
        else if ($type === self::TYPE_BOOL)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) 
                throw new SafeParamInvalidException($key, $type);
            else $value = (bool)$value;
        }
        else if ($type >= self::TYPE_INT_FIRST && $type <= self::TYPE_INT_LAST)
        {
            if (($value = filter_var($value, FILTER_VALIDATE_INT)) === false) 
                throw new SafeParamInvalidException($key, $type);
            else $value = (int)$value;
            
            if ($type === self::TYPE_UINT)
            {
                if ($value < 0) throw new SafeParamInvalidException($key, $type);
            }
            else if ($type !== self::TYPE_INT)
            {
                switch ($type)
                {
                    case self::TYPE_INT32:  $bits = 32; $signed = true; break;
                    case self::TYPE_INT16:  $bits = 16; $signed = true; break;
                    case self::TYPE_INT8:   $bits = 8;  $signed = true; break;
                    case self::TYPE_UINT32: $bits = 32; $signed = false; break;
                    case self::TYPE_UINT16: $bits = 16; $signed = false; break;
                    case self::TYPE_UINT8:  $bits = 8;  $signed = false; break;
                    default: throw new IntTypeErrorException();
                }
                
                if ($signed) { $max = pow(2,$bits-1); $min = -$max; }
                else         { $max = pow(2,$bits);   $min = 0; }
                
                if ($value < $min || $value >= $max)
                    throw new SafeParamInvalidException($key, $type);
            }
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
            if (mb_strlen($value) >= 256 || preg_match("%[\\\\/?*:;{}]+%",$value) ||
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
            if (strlen($value) >= 65536 || !preg_match('//u',$value) /* UTF-8 */ ||
                ($value = filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS)) === false)
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
        else throw new SafeParamUnknownTypeException(static::GetTypeString($type));
        
        if ($value !== null && $valfunc !== null && !$valfunc($value))
            throw new SafeParamInvalidException($key);
        
        if ($value !== null && $values !== null && !in_array($value,$values,true))
            throw new SafeParamInvalidEnumException($key, $values);

        return $value;
    }
}
