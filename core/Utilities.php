<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;

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
        return self::$instances[static::class];
    }
    public function __construct()
    {
        if (array_key_exists(static::class, self::$instances))
            throw new DuplicateSingletonException();
            else self::$instances[static::class] = $this;
    }
}

abstract class Utilities
{   
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_";
    
    public static function RandomRange() : int { return strlen(static::$chars); }
    
    public static function Random(?int $length = null) : string
    {
        $length ??= BaseObject::IDLength;
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
}
