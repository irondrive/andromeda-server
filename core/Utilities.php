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

/** 
 * Simple interface with rollback() and commit() 
 * 
 * The Andromeda transaction model is that there is always a global commit
 * or rollback at the end of the request. A rollback may follow a bad commit.
 * There will NEVER be a rollback followed by a commit. There may be > 1 commit.
 */
interface Transactions { public function rollback(); public function commit(); }

/** Class for parsing a version string into components */
class VersionInfo
{
    public int $major; public int $minor; public int $patch; 
    public string $version; public string $extra;
    
    public function __construct(string $version = andromeda_version)
    {
        $this->version = $version;
        
        $version = explode('-',$version,2);
        $this->extra = $version[1] ?? null;
        
        $version = explode('.',$version[0],3);
        
        $this->major = $version[0];
        $this->minor = $version[1] ?? null;
        $this->patch = $version[2] ?? null;
    }
    
    public function __toString(){ return $this->version; }
}

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
        if (!($data = json_encode($data))) {
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
    
    /** Returns the best password_hash algorithm available */
    public static function GetHashAlgo()
    {
        if (defined('PASSWORD_ARGON2ID')) return PASSWORD_ARGON2ID;
        if (defined('PASSWORD_ARGON2I')) return PASSWORD_ARGON2I;
        else return PASSWORD_DEFAULT;
    }
    
    /** Returns the last element of an array or null if it's empty */
    public static function array_last(?array $arr)
    {
        if ($arr === null) return null;
        
        $size = count($arr); return $size ? $arr[$size-1] : null;
    }
    
    /** Deletes any of the given value from the given array reference */
    public static function delete_value(array &$arr, $value) : array
    {
        return $arr = array_filter($arr, function($val)use($value){ return $val !== $value; });
    }
    
    /** Returns a class name with the namespace stripped */
    public static function ShortClassName(?string $class) : ?string { 
        return $class ? self::array_last(explode("\\",$class)) : null; }
        
    /** Returns the given string with the first character capitalized */
    public static function FirstUpper(string $str) : string {
        return mb_strtoupper(mb_substr($str,0,1)).mb_substr($str,1); }
    
    /**
     * Returns a size string converted to bytes
     * @param string $val a size string e.g. 32M or 4K
     * @return int formatted as bytes e.g. 33554432 or 4096
     */
    public static function return_bytes(string $val) : int
    {
        $val = trim($val); 
        if (!$val) return 0;
        
        $num = intval($val);        
        switch (substr($val, -1)) {
            case 'T': $num *= 1024;
            case 'G': $num *= 1024;
            case 'M': $num *= 1024;
            case 'K': $num *= 1024;
        }; return $num;
    }
    
    /** Equivalent to str_replace but only does one replacement */
    public static function replace_first(string $search, string $replace, string $subject) : string
    {
        if (!$search || !$subject) return $subject;
        
        if (($pos = mb_strpos($subject, $search)) !== false)
            $subject = mb_substr($subject, 0, $pos).$replace.
                mb_substr($subject, $pos+strlen($search));
        
        return $subject;
    }
    
    /** Captures and returns any echoes or prints in the given function */
    public static function CaptureOutput(callable $func) : string
    {
        ob_start(); $func(); $retval = ob_get_contents(); ob_end_clean(); return $retval;
    }
}

/** A class for overriding static methods for unit testing */
class StaticWrapper
{
    private $overrides = array();
    
    public function __construct(string $class){ $this->class = $class; }
    
    public function _override(string $fname, callable $func) : self
    {        
        $this->overrides[$fname] = $func; return $this;
    }
    
    public function __call($fname, $args)
    {        
        if (method_exists($this, $fname)) return $this->$fname(...$args);
        
        if (array_key_exists($fname, $this->overrides))
            return $this->overrides[$fname](...$args);
        
        return ($this->class)::$fname(...$args);
    }
}
