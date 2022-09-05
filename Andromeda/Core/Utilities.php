<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions.php");

/** 
 * Abstract class implementing a global singleton 
 * 
 * This should be avoided as globals make unit testing difficult
 */
abstract class Singleton // TODO refactor to get rid of this (only Auth\Local uses it now)
{
    /** @var array<class-string<static>, static> */
    private static $instances = array();
    
    /**
     * Get the global instance of the singleton
     * @throws MissingSingletonException if not yet constructed
     * @return static
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

/** Class for parsing a version string into components */
class VersionInfo
{
    public string $version;
    
    public int $major;
    public int $minor;
    public int $patch;
    public ?string $extra;
    
    public function __construct(string $version)
    {
        $this->version = $version;
        
        $version = explode('-',$version,2);
        $this->extra = $version[1] ?? null;
        
        $version = explode('.',$version[0],3);
        
        foreach ($version as $v) if (!is_numeric($v)) 
            throw new InvalidVersionException();

        if (!isset($version[0]) || !isset($version[1]))
            throw new InvalidVersionException();
        
        $this->major = intval($version[0]);
        $this->minor = intval($version[1]);
        
        if (isset($version[2])) 
            $this->patch = intval($version[2]);
    }
    
    public function __toString() : string { 
        return $this->version; }
    
    /** Returns the Major.Minor compatibility version string */
    public function getCompatVer() : string { 
        return $this->major.'.'.$this->minor; }
    
    /** @see VersionInfo::getCompatVer() */
    public static function toCompatVer(string $version) : string {
        return (new self($version))->getCompatVer(); }
}

/** Abstract with some global static utility functions */
abstract class Utilities
{   
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_"; // 36 (5 bits/char)

    /** 
     * Returns a random string with the given length 
     * @param 0|positive-int $length
     */
    public static function Random(int $length) : string
    {
        $range = strlen(self::$chars)-1; assert($range > 1);
        
        $string = ""; for ($i = 0; $i < $length; $i++)
            $string .= self::$chars[random_int(0, $range)];
        
        return $string;        
    }
    
    /** Returns true iff $data is valid UTF-8 or null */
    public static function isUTF8(?string $data) : bool
    {
        return $data === null || mb_check_encoding($data,'UTF-8');
    }
    
    /**
     * Encodes an array as a JSON string
     * @param array<mixed> $jarr json array
     * @throws JSONException
     * @return string json string
     */
    public static function JSONEncode(array $jarr) : string
    {
        if (!is_string($jstr = json_encode($jarr)))
            throw new JSONException();
        return $jstr;
    }
    
    /**
     * Decodes a JSON string as an array
     * @param string $jstr json string
     * @throws JSONException
     * @return array<mixed> json array
     */
    public static function JSONDecode(string $jstr) : array
    {
        if (!is_array($jarr = json_decode($jstr, true)))
            throw new JSONException();
        return $jarr;
    }
    
    /** Returns the best password_hash algorithm available */
    public static function GetHashAlgo() : string
    {
        if (defined('PASSWORD_ARGON2ID')) return PASSWORD_ARGON2ID;
        if (defined('PASSWORD_ARGON2I')) return PASSWORD_ARGON2I;
        else return PASSWORD_DEFAULT;
    }
    
    /**
     * @template T
     * @param array<?T> $arr array input 
     * @return ?T the last element of an array or null if it's empty 
     */
    public static function array_last(array $arr)
    {
        if (empty($arr)) return null;
        
        return $arr[array_key_last($arr)];
    }
    
    /** 
     * Deletes any of the given value from the given array reference 
     * @template T of array
     * @param T $arr
     * @param mixed $value
     * @return T
     */
    public static function delete_value(array &$arr, $value) : array
    {
        return $arr = array_filter($arr, function($val)use($value){ return $val !== $value; });
    }
    
    /** 
     * Converts all objects in the array to strings and checks UTF-8, to make it printable
     * @template T of array
     * @param T $data
     * @return T
     */
    public static function arrayStrings(array $data) : array
    {
        foreach ($data as &$val)
        {
            if (is_array($val))
            {
                $val = self::arrayStrings($val);
            }
            else if (is_object($val))
            {
                $val = method_exists($val,'__toString')
                    ? (string)$val : get_class($val);
            }
            else 
            {
                $val = strval($val);
                
                if (!Utilities::isUTF8($val))
                    $val = base64_encode($val);
            }
        }
        return $data;
    }
    
    /** Returns a class name with the namespace stripped */
    public static function ShortClassName(string $class) : ?string 
    { 
        return self::array_last(explode("\\",$class));
    }
    
    /** Returns the given string with the first character capitalized */
    public static function FirstUpper(string $str) : string 
    {
        return mb_strtoupper(mb_substr($str,0,1)).mb_substr($str,1); 
    }
    
    /**
     * Returns a size string converted to bytes
     * @param string $val a size string e.g. 32M or 4K
     * @return int formatted as bytes e.g. 33554432 or 4096
     */
    public static function return_bytes(string $val) : int
    {
        $val = trim($val); 
        if (!$val) return 0;
        
        $num = (int)($val);
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
        ob_start(); $func(); $retval = ob_get_contents(); ob_end_clean(); 
        
        // ASSERT: retval must be string when buffering is active
        assert(is_string($retval)); return $retval;
    }
    
    /** 
     * Performs a key-based array map
     * @template T
     * @param callable(string):T $func function to map onto each key
     * @param array<string> $keys array of keys to feed into $func
     * @return array<string, T>
     */
    public static function array_map_keys(callable $func, array $keys) : array
    {
        $retval = array_combine($keys, array_map($func, $keys)); 
        
        // ASSERT: array_combine must be an array when both arrays have the same size
        assert(is_array($retval)); return $retval;
    }
    
    /** 
     * Returns all classes that are a $match type 
     * @template T
     * @param class-string<T> $match
     * @return array<class-string<T>>
     */
    public static function getClassesMatching(string $match) : array
    {
        $retval = array(); foreach (get_declared_classes() as $class)
        {
            if ($class !== $match && is_a($class, $match, true) 
                && !(new \ReflectionClass($class))->isAbstract())
            {
                $retval[] = $class;
            }
        } return $retval;
    }
    
    /** 
     * Runs the given function with no execution timeouts or user aborts 
     * @template T
     * @param callable():T $func function to run
     * @return T returned value from $func
     */
    public static function RunNoTimeout(callable $func)
    {
        $tl = (int)ini_get('max_execution_time'); set_time_limit(0);
        $ua = (bool)ignore_user_abort(); ignore_user_abort(true);
        
        $retval = $func();
        
        set_time_limit($tl); ignore_user_abort($ua);
        
        return $retval;
    }
}

/** A class for overriding static methods for unit testing */
class StaticWrapper
{
    private $overrides = array();
    private string $class;
    
    public function __construct(string $class){ $this->class = $class; }
    
    /**
     * Overrides a static function on the class
     * @param string $fname name of the static function
     * @param callable $func function to replace with
     * @return self
     */
    public function _override(string $fname, callable $func) : self
    {        
        $this->overrides[$fname] = $func; return $this;
    }
    
    public function __call($fname, $args)
    {
        if (array_key_exists($fname, $this->overrides))
            return $this->overrides[$fname](...$args);
        
        if (method_exists($this, $fname))
            return $this->$fname(...$args);
            
        return ($this->class)::$fname(...$args);
    }
}
