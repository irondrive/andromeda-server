<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/Output.php");
require_once(ROOT."/Core/IOFormat/IOInterface.php");
require_once(ROOT."/Core/IOFormat/InputFile.php");
require_once(ROOT."/Core/IOFormat/SafeParam.php");
require_once(ROOT."/Core/IOFormat/SafeParams.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParam,SafeParams,InputPath,InputStream};

require_once(ROOT."/Core/IOFormat/Exceptions.php"); use Andromeda\Core\IOFormat\EmptyBatchException;

require_once(ROOT."/Core/IOFormat/Interfaces/Exceptions.php");

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** The interface for using Andromeda via local console */
class CLI extends IOInterface
{
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    /** @return true */
    public static function isPrivileged() : bool { return true; }
    
    /** @return int plain text output by default */
    public static function GetDefaultOutmode() : int { return static::OUTPUT_PLAIN; }
    
    /** Strips -- off the given string and returns (or null if not found) */
    private static function getKey(string $str) : ?string
    {
        if (mb_substr($str,0,2) !== "--") return null; else return mb_substr($str,2);
    }
    
    /** Returns the next args value (or null if not found) and increments $i */
    private static function getNextValue(array $args, int &$i) : ?string
    {
        return (isset($args[$i+1]) && !self::getKey($args[$i+1])) ? $args[++$i] : null;
    }

    public function getAddress() : string
    {
        $retval = "CLI";
        
        if (array_key_exists("COMPUTERNAME",$_SERVER)) 
            $retval .= " ".$_SERVER["COMPUTERNAME"];
        if (array_key_exists("USERNAME",$_SERVER))
            $retval .= " ".$_SERVER["USERNAME"];
        
        return $retval;
    }
    
    public function getUserAgent() : string
    {
        $retval = "CLI";
        
        if (array_key_exists("OS",$_SERVER))
            $retval .= " ".$_SERVER["OS"];

        return $retval;
    }
    
    private bool $dryRun = false;
    public function isDryRun() : bool { return $this->dryRun; }
    
    private ?int $debug = null;
    public function GetDebugLevel() : int { return $this->debug ?? Config::ERRLOG_ERRORS; }
    
    private ?int $metrics = null;
    public function GetMetricsLevel() : int { return $this->metrics ?? 0; }
    
    private ?string $dbconf = null;
    public function GetDBConfigFile() : ?string { return $this->dbconf; }
    
    public function AdjustConfig(Config $config) : self
    {
        if ($this->debug !== null && in_array($this->debug, Config::DEBUG_TYPES, true))
            $config->SetDebugLevel($this->debug, true);
        
        if ($this->metrics !== null && in_array($this->metrics, Config::METRICS_TYPES, true))
            $config->SetMetricsLevel($this->metrics, true);
        
        return $this;
    }

    protected function subGetInputs() : array
    {
        global $argv; $argIdx = 1;
        
        // global params for outmode, debug, config file, etc. come first
        for (; $argIdx < count($argv); $argIdx++)
        {
            $key = self::getKey($argv[$argIdx]);
            
            if ($key === null) break; else switch ($key)
            {
                case 'dryrun': $this->dryRun = true; break;
                case 'json': $this->outmode = static::OUTPUT_JSON; break;
                case 'printr': $this->outmode = static::OUTPUT_PRINTR; break;
                
                case 'debug':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException();
                    $debug = (new SafeParam('debug',$val))->FromWhitelist(array_keys(Config::DEBUG_TYPES));
                    $this->debug = Config::DEBUG_TYPES[$debug];
                    break;
                }
                case 'metrics':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException();
                    $metrics = (new SafeParam('metrics',$val))->FromWhitelist(array_keys(Config::METRICS_TYPES));
                    $this->metrics = Config::METRICS_TYPES[$metrics];
                    break;
                }
                case 'dbconf':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException();
                    $this->dbconf = (new SafeParam('dbconf',$val))->GetFSPath();
                    break;
                }
                default: throw new IncorrectCLIUsageException();
            }
        }
        
        // now process the actual app/action command(s)
        for (; $argIdx < count($argv); $argIdx++)
        {
            $key = self::getKey($argv[$argIdx]);
            
            if ($key !== null) 
                throw new IncorrectCLIUsageException();
            
            else switch ($argv[$argIdx])
            {
                case 'version': die("Andromeda ".andromeda_version.PHP_EOL);
                
                case 'batch':
                {
                    $fname = self::getNextValue($argv,$argIdx);
                    if ($fname === null)
                        throw new IncorrectCLIUsageException();
                    else return self::GetBatch($fname);
                }
                
                default: return array(self::GetInput(array_slice($argv, $argIdx)));
            }
        }

        throw new IncorrectCLIUsageException();
    }

    /** 
     * Reads an array of Input objects from a batch file
     * @return non-empty-array<Input>
      */
    private function GetBatch(string $file) : array
    {       
        try { $lines = array_filter(explode("\n", file_get_contents($file))); }
        catch (Exceptions\PHPError $e) { throw new UnknownBatchFileException(); }
        
        if (!count($lines)) throw new EmptyBatchException();
        
        return array_map(function($line)
        {
            try { return self::GetInput(\Clue\Arguments\split($line)); }
            catch (\InvalidArgumentException $e) { throw new BatchFileParseException(); }
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
            
            if (count($key) === 2 && $key[0] === 'andromeda')
            {
                array_push($envargs, "--".$key[1], $value);
            }
        }; $argv = array_merge($envargs, $argv);
        
        for ($i = 0; $i < count($argv); $i++)
        {
            $param = self::getKey($argv[$i]); 
            if (!$param) throw new IncorrectCLIUsageException();
            
            $special = mb_substr($param, -1);
            
            // optionally load a param value from a file instead
            if ($special === '@')
            {
                $param = mb_substr($param,0,-1); 
                if (!$param) throw new IncorrectCLIUsageException();
                
                $val = self::getNextValue($argv,$i);
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
            else $val = self::getNextValue($argv,$i);

            // optionally send the app a path/name of a file instead
            if ($special === '%')
            {
                $param = mb_substr($param,0,-1);
                if (!$param) throw new IncorrectCLIUsageException();
                
                if (!is_readable($val)) throw new InvalidFileException($val);
                
                $filename = basename(self::getNextValue($argv,$i) ?? $val);
                $filename = (new SafeParam('name',$filename))->GetFSName();
                
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
    
    public function FinalOutput(Output $output)
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

        exit($output->isOK() ? 0 : 1);
    }
}
