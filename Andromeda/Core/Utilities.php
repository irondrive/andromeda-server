<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Exceptions.php");

if (!function_exists('json_encode')) 
    die("PHP JSON Extension Required".PHP_EOL);

/** 
 * Abstract class implementing a global singleton 
 * 
 * This should be avoided as globals make unit testing difficult
 */
abstract class Singleton // TODO refactor to get rid of this
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

/** Abstract with some global static utility functions */
abstract class Utilities
{
    /** @var non-empty-string&literal-string */
    private static string $chars = "0123456789abcdefghijkmnopqrstuvwxyz_"; // 36 (5 bits/char)

    /** 
     * Returns a random string with the given length 
     * @param int<0,max> $length
     */
    public static function Random(int $length) : string
    {
        $range = strlen(self::$chars)-1;
        
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
     * @param array<scalar, mixed> $jarr json array
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
     * @return array<scalar, NULL|scalar|array<scalar, NULL|scalar|array<scalar, mixed>>> json array
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
                $val = (string)$val;
                
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
        {
            return mb_substr($subject, 0, $pos).$replace.
                mb_substr($subject, $pos+strlen($search));
        }
        else return $subject;
    }
    
    /** 
     * Captures and returns any echoes or prints in the given function 
     * @throws OutputBufferException if the PHP ob functions fail
     */
    public static function CaptureOutput(callable $func) : string
    {
        if (ob_start() === false) 
            throw new OutputBufferException("ob_start fail"); 
        
        $func(); $retval = ob_get_clean();
        
        if ($retval === false || !is_string($retval))
            throw new OutputBufferException("ob_get_clean fail");
        
        return $retval;
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
     * Returns true if the given array has 0..N keys and scalar values
     * @param array<mixed> $arr
     * @return bool
     */
    public static function is_plain_array(array $arr) : bool
    {
        if (empty($arr)) return true;
        if (!isset($arr[0])) return false; // shortcut
        if (!is_scalar($arr[0])) return false; // shortcut
        
        if (array_keys($arr) !== range(0, count($arr)-1)) return false;
        foreach ($arr as $val) if (!is_scalar($val)) return false;
        return true;
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
