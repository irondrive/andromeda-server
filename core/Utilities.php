<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php");

/** Exception indicating that a duplicate singleton was constructed */
class DuplicateSingletonException extends Exceptions\ServerException { public $message = "DUPLICATE_SINGLETON"; }

/** Exception indicating that GetInstance() was called on a singleton that has not been constructed */
class MissingSingletonException extends Exceptions\ServerException { public $message = "SINGLETON_NOT_CONSTRUCTED"; }

/** Abstract class implementing a singleton */
abstract class Singleton
{
    private static $instances = array();
    
    /**
     * Get the instance of the singleton
     * @throws MissingSingletonException if not yet constructed
     */
    public static function GetInstance() : self
    {
        $class = static::class;
        if (!array_key_exists($class, self::$instances))
            throw new MissingSingletonException($class);
        return self::$instances[$class];
    }
    
    /**
     * Construct the singleton for use. 
     * 
     * This can be overriden if it requires more arguments
     * @throws DuplicateSingletonException if already constructed
     */
    public function __construct()
    {
        $class = static::class;
        if (array_key_exists($class, self::$instances))
            throw new DuplicateSingletonException($class);
        else self::$instances[$class] = $this;
    }
}

/** Converts a JSON failure into an exception */
class JSONException extends Exceptions\ServerException
{
    public function __construct() 
    {
        $this->code = json_last_error();
        $this->message = json_last_error_msg(); 
    } 
}

/** Exception indicating that JSON encoding failed */
class JSONEncodingException extends JSONException { public $message = "JSON_ENCODE_FAIL"; }

/** Exception indicating that JSON decoding failed */
class JSONDecodingException extends JSONException { public $message = "JSON_DECODE_FAIL"; }

/** Simple interface with rollBack() and commit() */
interface Transactions { public function rollBack(); public function commit(); }

/** Abstract with some global static utility functions */
abstract class Utilities
{   
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_";
    
    /** Returns the number of possible characters for a digit in Random */
    public static function RandomRange() : int { return strlen(static::$chars); }
    
    /** Returns a random string with the given length */
    public static function Random(int $length) : string
    {
        $string = ""; $range = static::RandomRange() - 1;
        for ($i = 0; $i < $length; $i++)
            $string .= static::$chars[random_int(0, $range)];
        return $string;        
    }
    

    /**
     * Encodes the data as JSON
     * @throws JSONEncodingException
     */
    public static function JSONEncode($data) : string
    {
        if (!($data = json_encode($data, JSON_NUMERIC_CHECK))) {
            throw new JSONEncodingException(); };
        return $data;
    }
    
    /**
     * Decodes the JSON data as an array
     * @throws JSONDecodingException
     */
    public static function JSONDecode(string $data)
    {
        if (($data = json_decode($data, true)) === null) {
            throw new JSONDecodingException(); };
        return $data;
    }
    
    /**
     * Strips not printable characters (00-1F and 7F) from the given ASCII string
     * @param string $str an ASCII string
     */
    public static function MakePrintable(string $str) : string
    {
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $str); 
    }
    
    /** Returns the best password_hash algorithm available */
    public static function GetHashAlgo()
    {
        if (defined('PASSWORD_ARGON2ID')) return PASSWORD_ARGON2ID;
        if (defined('PASSWORD_ARGON2I')) return PASSWORD_ARGON2I;
        else return PASSWORD_DEFAULT;
    }
    
    /** Returns the last element of an array of null if it's empty */
    public static function array_last(?array $arr)
    {
        if ($arr === null) return null;
        
        $size = count($arr); return $size ? $arr[$size-1] : null;
    }
    
    /** Returns a class name with the namespace stripped */
    public static function ShortClassName(?string $class) : ?string { 
        return $class ? self::array_last(explode("\\",$class)) : null; }
    
    /**
     * Returns a size string converted to bytes
     * @param string $val a size string e.g. 32M or 4K
     * @return int formatted as bytes e.g. 33554432 or 4096
     */
    public static function return_bytes(string $val) : int
    {
        $val = trim($val); if (!$val) return 0; 
        
        $num = substr($val, 0, -1);
        
        switch (substr($val, -1)) {
            case 'T': $num *= 1024;
            case 'G': $num *= 1024;
            case 'M': $num *= 1024;
            case 'K': $num *= 1024;
        }; return $num;
    } 
}
