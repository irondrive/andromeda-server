<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Singleton;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/interfaces/AJAX.php");
require_once(ROOT."/core/ioformat/interfaces/CLI.php");

if (!function_exists('json_encode')) die("PHP JSON Extension Required".PHP_EOL);

/** Exception indicating that an app action does not allow batches */
class BatchNotAllowedException extends Exceptions\ClientErrorException { public $message = "METHOD_DISALLOWS_BATCH"; }

/** Class for custom app output routines */
class OutputHandler
{
    private $getbytes; private $output;
    
    public function __construct(callable $getbytes, callable $output){
        $this->getbytes = $getbytes; $this->output = $output; }
    
    /** Return the number of bytes that will be output */
    public function GetBytes() : ?int { return ($this->getbytes)(); }
    
    /** Do the actual output routine */
    public function DoOutput(Output $output) : void { ($this->output)($output); }
}

/** Describes an abstract PHP I/O interface abstraction */
abstract class IOInterface extends Singleton
{
    /** Constructs and returns a singleton of the appropriate interface type */
    public static function TryGet() : ?self
    {
        if (Interfaces\AJAX::isApplicable()) return new Interfaces\AJAX();
        else if (Interfaces\CLI::isApplicable()) return new Interfaces\CLI();
        else return null;
    }

    /** Returns whether or not the interface is in use for this request */
    abstract public static function isApplicable() : bool;
    
    /** Returns whether or not the interface grants privileged access */
    abstract public static function isPrivileged() : bool;
    
    /** Called during API construction to initialize the interface, e.g. to gather global arguments */
    public function Initialize() : void { }
    
    /** @var array<Input> */
    protected array $inputs;

    /** 
     * Retrieves an array of input objects to run 
     * @return Input[]
     */
    public function GetInputs(?Config $config) : array
    {
        if (isset($this->inputs)) return $this->inputs;
        else return ($this->inputs = $this->subGetInputs($config));
    }
    
    /** @see self::GetInputs() */
    abstract protected function subGetInputs(?Config $config) : array;
    
    /** Asserts that only one output was given */
    public function DisallowBatch() : void
    {
        if (isset($this->inputs) && count($this->inputs) > 1)
            throw new BatchNotAllowedException();
    }
        
    /** Returns the address of the user on the interface */
    abstract public function getAddress() : string;    
    
    /** Returns the user agent string on the interface */
    abstract public function getUserAgent() : string;
    
    /** Returns the debugging level requested by the interface */
    public function GetDebugLevel() : int { return 0; }
    
    /** Returns the perf metrics level requested by the interface */
    public function GetMetricsLevel() : int { return 0; }
    
    /** Returns the path to the DB config file requested by the interface */
    public function GetDBConfigFile() : ?string { return null; }
    
    const OUTPUT_PLAIN = 1; const OUTPUT_JSON = 2; const OUTPUT_PRINTR = 3;

    /** Gets the default output mode for the interface */
    abstract public static function GetDefaultOutmode() : int;
    
    protected ?int $outmode;
    
    public function __construct(){ $this->outmode = static::GetDefaultOutmode(); }

    /** Sets the output mode to the given mode or null (none) - NOT USED in "multi-output" mode! */
    public function SetOutputMode(?int $mode) : self { $this->outmode = $mode; return $this; }
    
    private $retfuncs = array();
    private int $numretfuncs = 0;
    
    /** 
     * Registers a user output handler function to run after the initial commit 
     * 
     * Sets the output mode to null if bytes > 0 and will cause "multi-output" mode 
     * to be used if > 1 functions that return > 0 bytes are defined
     * @see IOInterface::isMultiOutput()
     */
    public function RegisterOutputHandler(OutputHandler $f) : self 
    {
        if ($f->GetBytes() !== null)
        {
            $this->outmode = null; 
            $this->numretfuncs++;
        }
        
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
    
    /** Tells the interface to print its final output */
    public abstract function WriteOutput(Output $output);
}
