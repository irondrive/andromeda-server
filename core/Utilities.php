<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class DuplicateSingletonException extends Exceptions\ServerException { public $message = "DUPLICATE_SINGLETON"; }
class MissingSingletonException extends Exceptions\ServerException { public $message = "SINGLETON_NOT_CONSTRUCTED"; }

class JSONException extends Exceptions\ServerException {
    public function __construct() {
        $this->code = json_last_error();
        $this->details = json_last_error_msg(); } }
        
class JSONEncodingException extends JSONException { public $message = "JSON_ENCODE_FAIL"; }
class JSONDecodingException extends JSONException { public $message = "JSON_DECODE_FAIL"; }

abstract class Singleton
{
    private static $instances = array();
    
    public static function GetInstance() : self
    {
        $class = static::class;
        if (!array_key_exists($class, self::$instances))
            throw new MissingSingletonException($class);
        return self::$instances[$class];
    }
    
    public function __construct()
    {
        $class = static::class;
        if (array_key_exists($class, self::$instances))
            throw new DuplicateSingletonException($class);
        else self::$instances[$class] = $this;
    }
}

abstract class Utilities
{   
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_";
    
    public static function RandomRange() : int { return strlen(static::$chars); }
    
    public static function Random(?int $length) : string
    {
        $string = ""; $range = static::RandomRange() - 1;
        for ($i = 0; $i < $length; $i++)
            $string .= static::$chars[random_int(0, $range)];
        return $string;        
    }
    
    public static function JSONEncode($data) : string
    {
        if (!($data = json_encode($data, JSON_NUMERIC_CHECK))) {
            throw new JSONEncodingException(); };
        return $data;
    }
    
    public static function JSONDecode(string $data)
    {
        if (!($data = json_decode($data, true))) {
            throw new JSONDecodingException(); };
        return $data;
    }
    
    public static function GetHashAlgo()
    {
        if (defined('PASSWORD_ARGON2ID')) return PASSWORD_ARGON2ID;
        if (defined('PASSWORD_ARGON2I')) return PASSWORD_ARGON2I;
        else return PASSWORD_DEFAULT;
    }
    
    public static function array_last(array $arr)
    {
        $size = count($arr); return $size ? $arr[count($arr)-1] : null;
    }
    
    public static function return_bytes($val) 
    {
        if (!$val) return 0; if (is_numeric($val)) return $val;
        $val = trim($val); $num = substr($val, 0, -1);
        switch (substr($val, -1)) {
            case 'T': $num *= 1024;
            case 'G': $num *= 1024;
            case 'M': $num *= 1024;
            case 'K': $num *= 1024;
        }; return $num;
    } 
}
