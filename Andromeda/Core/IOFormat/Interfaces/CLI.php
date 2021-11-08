<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\{Utilities, MissingSingletonException};

require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/Output.php");
require_once(ROOT."/Core/IOFormat/IOInterface.php");
require_once(ROOT."/Core/IOFormat/InputFile.php");
require_once(ROOT."/Core/IOFormat/SafeParam.php");
require_once(ROOT."/Core/IOFormat/SafeParams.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParam,SafeParams,InputPath,InputStream};

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the command line usage is incorrect */
class IncorrectCLIUsageException extends Exceptions\ClientErrorException { 
    public $message = "general usage: php index.php [--json|--printr] [--debug int] [--metrics int] [--dryrun] [--dbconf fspath] app action ".
                          "[--\$param value] [--\$param@ file] [--\$param!] [--\$param% file [name]] [--\$param-]".PHP_EOL.
                          "\t param@ puts the content of the file in the parameter".PHP_EOL.
                          "\t param! will prompt interactively or read stdin for the parameter value".PHP_EOL.
                          "\t param% gives the file path as a direct file input (optionally with a new name)".PHP_EOL.
                          "\t param- will attach the stdin stream as a direct file input".PHP_EOL.
                          PHP_EOL.
                      "batch usage:   php index.php batch myfile.txt".PHP_EOL.
                      "get version:   php index.php version".PHP_EOL.
                      "get actions:   php index.php server usage"; }

/** Exception indicating that the given batch file is not valid */
class UnknownBatchFileException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_BATCH_FILE"; }

/** Exception indicating that the given batch file's syntax is not valid */
class BatchFileParseException extends Exceptions\ClientErrorException { public $message = "BATCH_FILE_PARSE_ERROR"; }

/** Exception indicating that the given file is not valid */
class InvalidFileException extends Exceptions\ClientErrorException { public $message = "INACCESSIBLE_FILE"; }

/** The interface for using Andromeda via local console */
class CLI extends IOInterface
{
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    /** @return true */
    public static function isPrivileged() : bool { return true; }
    
    public function __construct()
    {
        parent::__construct();
        
        if (function_exists('pcntl_signal'))
        {
            pcntl_signal(SIGTERM, function()
            {
                try { Main::GetInstance()->rollback(); }
                catch (MissingSingletonException $e) { }
            });
        }
    }  
    
    /** Strips -- off the given string and returns (or false if not found) */
    private static function getKey(string $str)
    {
        if (mb_substr($str,0,2) !== "--") return false; else return mb_substr($str,2);
    }
    
    /** Returns the next args value (or null if not found) and increments $i */
    private static function getNextValue(array $args, int &$i) : ?string
    {
        return (isset($args[$i+1]) && !static::getKey($args[$i+1])) ? $args[++$i] : null;
    }

    /** 
     * Initializes CLI by fetching some global params from $argv
     * 
     * Options such as output mode, debug level and DB config file should be
     * fetched here before the actual GetInputs() is run later. These options
     * are global, not specific to a single Input instance
     */
    public function Initialize() : void
    {        
        global $argv;
        
        // pre-process params that may be needed before $config is available        
        for ($i = 1; $i < count($argv); $i++)
        {
            $key = static::getKey($argv[$i]);

            if (!$key) break; else switch ($key)
            {
                case 'dryrun': break;
                
                case 'json': $this->outmode = static::OUTPUT_JSON; break;
                case 'printr': $this->outmode = static::OUTPUT_PRINTR; break;
                
                case 'debug':
                    if (($val = static::getNextValue($argv,$i)) === null) throw new IncorrectCLIUsageException();
                    $this->debug = (new SafeParam('debug',$val))->GetValue(SafeParam::TYPE_UINT);
                    break;
                    
                case 'metrics':
                    if (($val = static::getNextValue($argv,$i)) === null) throw new IncorrectCLIUsageException();
                    $this->metrics = (new SafeParam('metrics',$val))->GetValue(SafeParam::TYPE_UINT);
                    break;
                    
                case 'dbconf':
                    if (($val = static::getNextValue($argv,$i)) === null) throw new IncorrectCLIUsageException();
                    $this->dbconf = (new SafeParam('dbfconf',$val))->GetValue(SafeParam::TYPE_FSPATH);
                    break;

                default: throw new IncorrectCLIUsageException();
            }
        }
    }
    
    public function getAddress() : string
    {
        return implode(" ",array_filter(array("CLI", $_SERVER['COMPUTERNAME']??null, $_SERVER['USERNAME']??null)));
    }
    
    public function getUserAgent() : string
    {
        return implode(" ",array_filter(array("CLI", $_SERVER['OS']??null)));
    }
    
    private ?int $debug = null;
    public function GetDebugLevel() : int { return $this->debug ?? Config::ERRLOG_ERRORS; }
    
    private ?int $metrics = null;
    public function GetMetricsLevel() : int { return $this->metrics ?? 0; }
    
    private ?string $dbconf = null;
    public function GetDBConfigFile() : ?string { return $this->dbconf; }
    
    /** @return int plain text output by default */
    public static function GetDefaultOutmode() : int { return static::OUTPUT_PLAIN; }
    
    protected function subGetInputs(?Config $config) : array
    {
        if ($config)
        {
            if ($this->debug !== null && in_array($this->debug, Config::DEBUG_TYPES, true))
                $config->SetDebugLevel($this->debug, true);
            
            if ($this->metrics !== null && in_array($this->metrics, Config::METRICS_TYPES, true))
                $config->SetMetricsLevel($this->metrics, true);
        }
        
        global $argv;
        
        // process flags that are relevant for $config
        $i = 1; for (; $i < count($argv); $i++)
        {
            $key = static::getKey($argv[$i]);
            
            if (!$key) break; else switch ($key)
            {
                case 'json': break;
                case 'printr': break;
                case 'debug': $i++; break;
                case 'metrics': $i++; break;
                case 'dbconf': $i++; break;
                    
                case 'dryrun': if ($config) $config->overrideReadOnly(Config::RUN_DRYRUN); break;

                default: throw new IncorrectCLIUsageException();
            }
        }
        
        // build an Input command from the rest of the command line
        for (; $i < count($argv); $i++)
        {
            switch($argv[$i])
            {                
                case 'version': die("Andromeda ".andromeda_version.PHP_EOL); break;
                
                case 'batch':
                    if (($val = static::getNextValue($argv,$i)) === null) 
                        throw new IncorrectCLIUsageException();
                    return static::GetBatch($val); break;
                    
                default: return array(static::GetInput(array_slice($argv, $i))); break;
            }
        }
        
        throw new IncorrectCLIUsageException();
    }
    
    /** Reads an array of Input objects from a batch file */
    private function GetBatch(string $file) : array
    {       
        try { $lines = array_filter(explode("\n", file_get_contents($file))); }
        catch (Exceptions\PHPError $e) { throw new UnknownBatchFileException(); }
        
        global $argv; return array_map(function($line)use($argv)
        {
            try { $args = \Clue\Arguments\split($line); }
            catch (\InvalidArgumentException $e) { throw new BatchFileParseException(); }
            
            return static::GetInput($args);
        }, $lines);
    }
    
    /** Fetches an Input object by reading it from the command line */
    private function GetInput(array $argv) : Input
    {
        if (count($argv) < 2) throw new IncorrectCLIUsageException();
        
        $app = $argv[0]; $action = $argv[1]; 
        $argv = array_splice($argv, 2);
        
        $params = new SafeParams(); $files = array();
        
        // add environment variables to argv
        $envargs = array(); foreach ($_SERVER as $key=>$value)
        { 
            $key = explode('_',$key,2);
            
            if ($key[0] == 'andromeda' && count($key) == 2)
            {
                array_push($envargs, "--".$key[1], $value);
            }
        }; $argv = array_merge($envargs, $argv);
        
        for ($i = 0; $i < count($argv); $i++)
        {
            $param = static::getKey($argv[$i]); 
            if (!$param) throw new IncorrectCLIUsageException();
            
            $special = mb_substr($param, -1);
            
            // optionally load a param value from a file instead
            if ($special === '@')
            {
                $param = mb_substr($param,0,-1); 
                if (!$param) throw new IncorrectCLIUsageException();
                
                $val = static::getNextValue($argv,$i);
                if ($val === null) throw new IncorrectCLIUsageException();
                
                if (!is_readable($val)) throw new InvalidFileException();
                
                $val = trim(file_get_contents($val));
            }
            // optionally get a param value interactively
            else if ($special === '!')
            {
                $param = mb_substr($param,0,-1);
                if (!$param) throw new IncorrectCLIUsageException();
                
                echo "enter $param...".PHP_EOL;
                $val = trim(fgets(STDIN), PHP_EOL);
            }
            else $val = static::getNextValue($argv,$i);

            // optionally send the app a path/name of a file instead
            if ($special === '%')
            {
                $param = mb_substr($param,0,-1);
                if (!$param) throw new IncorrectCLIUsageException();
                
                if (!is_readable($val)) throw new InvalidFileException($val);
                
                $filename = basename(static::getNextValue($argv,$i) ?? $val);
                $filename = (new SafeParam('name',$filename))->GetValue(SafeParam::TYPE_FSNAME);
                
                $files[$param] = new InputPath($val, $filename, false);
            }
            // optionally attach stdin to a file instead
            else if ($special === '-')
            {
                $param = mb_substr($param,0,-1);
                if (!$param) throw new IncorrectCLIUsageException();
                
                $files[$param] = new InputStream(STDIN);
            }
            else $params->AddParam($param, $val);
        }
        
        return new Input($app, $action, $params, $files);
    }
    
    private $output_json = false;
    
    public function WriteOutput(Output $output)
    {
        $multi = $this->isMultiOutput();
        
        if (!$multi && $this->outmode === self::OUTPUT_PLAIN)
        {
            // try echoing as a string, switch to printr if it fails
            $outstr = $output->GetAsString();
            if ($outstr !== null) echo $outstr.PHP_EOL;
            else $this->outmode = self::OUTPUT_PRINTR;
        }

        if (!$multi && $this->outmode === self::OUTPUT_PRINTR)
        {
            $outdata = $output->GetAsArray();
            echo print_r($outdata, true).PHP_EOL;
        }        
        
        if ($multi || $this->outmode === self::OUTPUT_JSON)
        {
            $outdata = $output->GetAsArray();
            
            $outdata = Utilities::JSONEncode($outdata).PHP_EOL;
            
            if ($multi) echo static::formatSize(strlen($outdata));
            
            echo $outdata;
        }

        exit($output->isOK() ? 0 : $output->GetHTTPCode());
    }
}
