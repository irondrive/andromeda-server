<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/Core/IOFormat/Exceptions.php");
require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/Output.php");
require_once(ROOT."/Core/IOFormat/OutputHandler.php");
require_once(ROOT."/Core/IOFormat/Interfaces/HTTP.php");
require_once(ROOT."/Core/IOFormat/Interfaces/CLI.php");

if (!function_exists('json_encode')) die("PHP JSON Extension Required".PHP_EOL);

/** Describes an abstract PHP I/O interface abstraction */
abstract class IOInterface
{
    /** Constructs and returns a singleton of the appropriate interface type */
    public static function TryGet() : ?self
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
     * @return non-empty-array<Input>
     */
    public function GetInputs() : array
    {
        if (isset($this->inputs)) return $this->inputs; // cache
        else return ($this->inputs = $this->subGetInputs());
    }
    
    /** Sets temporary config parameters requested by the interface */
    public function AdjustConfig(Config $config) : self { return $this; }
    
    /**
     * Retries an array of input objects to run
     * @return non-empty-array<Input>
     */
    abstract protected function subGetInputs() : array;
    
    /** 
     * Asserts that only one output was given 
     * @return $this
     */
    public function DisallowBatch() : self
    {
        if (isset($this->inputs) && count($this->inputs) > 1)
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
    
    public const OUTPUT_PLAIN = 1; 
    public const OUTPUT_JSON = 2; 
    public const OUTPUT_PRINTR = 3;

    /** Gets the default output mode for the interface */
    abstract public static function GetDefaultOutmode() : int;
    
    protected ?int $outmode;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }

    /** Sets the output mode to the given mode or null (none) - NOT USED in "multi-output" mode! */
    public function SetOutputMode(?int $mode) : self { $this->outmode = $mode; return $this; }
    
    /** @var array<OutputHandler> */
    private $retfuncs = array();
    private int $numretfuncs = 0;
    
    /** 
     * Registers a user output handler function to run after the initial commit 
     * 
     * Sets the output mode to null if bytes !== null and will cause "multi-output" 
     * mode to be used if > 1 functions that return > 0 bytes are defined
     * @see IOInterface::isMultiOutput()
     */
    public function RegisterOutputHandler(OutputHandler $f) : self 
    {
        if ($f->GetBytes() !== null)
        {
            $this->outmode = null; 
            $this->numretfuncs++;
        }
        
        if ($this->isMultiOutput()) 
            $this->outmode = self::OUTPUT_JSON;
        
        $this->retfuncs[] = $f; return $this; 
    }
    
    /** 
     * Returns true if the output is using multi-output mode 
     * 
     * Multi-output mode is used when more than one user output function wants to run.
     * To accomodate this, each section will be prefaced with the number of bytes written.
     * In multi-output mode, the requested global output mode is ignored and the request
     * is always finished with JSON output (also with the # of bytes prefixed)
     * @see IOInterface::formatSize()
     */
    protected function isMultiOutput() : bool { return $this->numretfuncs > 1; }
    
    /** 
     * Returns the binary-packed version of the given integer size 
     * 
     * unsigned long long (always 64 bit, big-endian byte order)
     */
    protected static function formatSize(int $size) : string { return pack("J", $size); }
    
    /** 
     * Tells the interface to run the custom user output functions
     * @return bool true if a custom function was run
     */
    public function UserOutput(Output $output) : bool
    {
        $multi = $this->isMultiOutput();
        
        foreach ($this->retfuncs as $handler) 
        {
            if ($multi) echo static::formatSize($handler->GetBytes());
            
            $handler->DoOutput($output); flush();
        }
        
        return count($this->retfuncs) > 0;
    }
    
    /** Tells the interface to print its final output and exit */
    public abstract function FinalOutput(Output $output) : void;
}
