<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;

require_once(ROOT."/Core/IOFormat/Exceptions.php");

/** Describes an abstract PHP I/O interface abstraction */
abstract class IOInterface
{
    /** Constructs and returns a singleton of the appropriate interface type */
    final public static function TryGet() : ?self
    {
        if (Interfaces\HTTP::isApplicable()) return new Interfaces\HTTP();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }

    /** Returns whether or not the interface is in use for this request */
    abstract public static function isApplicable() : bool;
    
    /** Returns whether or not the interface grants privileged access */
    abstract public static function isPrivileged() : bool;

    /** @var non-empty-array<Input> */
    protected array $inputs;

    /** 
     * Retrieves an array of input objects to run (if not cached)
     * 
     * Also modifies internal state related to the input (debug, dryrun, etc.)
     * which can be transferred to config via AdjustConfig()
     * @return non-empty-array<Input>
     */
    public function LoadInputs() : array
    {
        if (!isset($this->inputs))
            $this->inputs = $this->subLoadInputs(); // cache
        return $this->inputs;
    }
    
    /** Sets temporary config parameters requested by the interface */
    public function AdjustConfig(Config $config) : self { return $this; }
    
    /**
     * Retries an array of input objects to run
     * @return non-empty-array<Input>
     */
    abstract protected function subLoadInputs() : array;
    
    /** 
     * Asserts that only one output was given 
     * @return $this
     */
    public function DisallowBatch() : self
    {
        if (!isset($this->inputs)) 
            $this->LoadInputs(); // populate
        
        if (count($this->inputs) > 1)
            throw new BatchNotAllowedException();
        
        return $this;
    }
        
    /** Returns the address of the user on the interface */
    abstract public function getAddress() : string;    
    
    /** Returns the user agent string on the interface */
    abstract public function getUserAgent() : string;
    
    /** Returns true if the interface requested a dry-run */
    public function isDryRun() : bool { return false; }
    
    /** Returns the debugging level requested by the interface */
    public function GetDebugLevel() : int { return 0; }
    
    /** Returns the perf metrics level requested by the interface */
    public function GetMetricsLevel() : int { return 0; }
    
    /** Returns the path to the DB config file requested by the interface */
    public function GetDBConfigFile() : ?string { return null; }
    
    public const OUTPUT_TYPES = array(
        'none'=>0, 
        'plain'=>self::OUTPUT_PLAIN, 
        'json'=>self::OUTPUT_JSON, 
        'printr'=>self::OUTPUT_PRINTR);
    
    public const OUTPUT_PLAIN = 1; 
    public const OUTPUT_JSON = 2; 
    public const OUTPUT_PRINTR = 3;

    /** Gets the default output mode for the interface */
    abstract public static function GetDefaultOutmode() : int;
    
    protected int $outmode;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }
    
    /** Returns the output mode currently selected */
    public function GetOutputMode() : int { return $this->outmode; }

    /**
     * Sets the output mode to the given mode or 0 for (none)
     * @throws MultiOutputJSONException if > 1 user output function and mode is not JSON
     */
    public function SetOutputMode(int $mode) : self 
    {
        if ($this->numretfuncs > 1 && $mode !== self::OUTPUT_JSON)
            throw new MultiOutputJSONException($mode);
        $this->outmode = $mode; return $this; 
    }
    
    /** @var array<OutputHandler> */
    private $retfuncs = array();
    private int $numretfuncs = 0;
    
    /** 
     * Registers a user output handler function to run after the initial commit 
     * 
     * Sets the output mode to null if bytes !== null, or will cause "multi-output" 
     * mode to be used (always JSON) if > 1 functions that return non-null bytes are defined
     * @see IOInterface::isMultiOutput()
     */
    public function RegisterOutputHandler(OutputHandler $f) : self 
    {
        if ($f->GetBytes() !== null)
        {
            $this->outmode = 0; 
            $this->numretfuncs++;
        }
        
        if ($this->isMultiOutput()) 
            $this->outmode = self::OUTPUT_JSON;
        
        $this->retfuncs[] = $f; return $this; 
    }

    /** 
     * Returns true if the output is using multi-output mode 
     * 
     * Multi-output mode is used when more than one user output function with non-null GetBytes wants to run,
     * or there when there is more then zero such user output function and our outmode is not null.
     * To accomodate this, each section will be prefaced with the number of bytes written.
     * In multi-output mode, the requested global output mode is ignored and the request
     * is always finished with JSON output (also with the # of bytes prefixed)
     * @see IOInterface::formatSize()
     */
    public function isMultiOutput() : bool 
    {
        return $this->numretfuncs > ($this->outmode ? 0 : 1);
    }
    
    /** 
     * Returns the binary-packed version of the given integer size 
     * 
     * unsigned long long (always 64 bit, big-endian byte order)
     * @param int<0,max> $size
     */
    public static function formatSize(int $size) : string { return pack("J", $size); }
    
    /** 
     * Tells the interface to run the custom user output functions
     * @return bool true if a custom function was run (regardless of output bytes)
     */
    public function UserOutput(Output $output) : bool
    {
        $multi = $this->isMultiOutput();
        
        foreach ($this->retfuncs as $handler) 
        {
            if ($multi && ($bytes = $handler->GetBytes()) !== null) 
                echo static::formatSize($bytes);
            
            $handler->DoOutput($output); flush();
        }
        
        return count($this->retfuncs) > 0;
    }
    
    /** Tells the interface to print its final output and exit */
    public abstract function FinalOutput(Output $output) : void;
}
