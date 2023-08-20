<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, Utilities};
use Andromeda\Core\IOFormat\Exceptions\EmptyBatchException;
use Andromeda\Core\IOFormat\{Input,InputPath,InputStream,Output,IOInterface,SafeParam,SafeParams};
use Andromeda\Core\IOFormat\Interfaces\Exceptions\IncorrectCLIUsageException;

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
    public static function GetDefaultOutmode() : int { return self::OUTPUT_PLAIN; }
    
    /** Strips -- off the given string and returns (or null if not found) */
    private static function getKey(string $str) : ?string
    {
        if (mb_substr($str,0,2) !== "--") return null; else return mb_substr($str,2);
    }
    
    /** 
     * Returns the next args value (or null if not found) and increments $i
     * @param array<int,string> $args
     */
    private static function getNextValue(array $args, int &$i) : ?string
    {
        return (isset($args[$i+1]) && self::getKey($args[$i+1]) === null) ? $args[++$i] : null;
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
    
    private ?string $outprop = null;
    
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

    protected function subLoadInputs() : array
    {
        global $argv; return $this->LoadCLIInputs($argv, $_SERVER, STDIN);
    }
    
    /**
     * Retries an array of input objects to run
     * @param array<string> $argv
     * @param array<string, scalar> $server
     * @param resource $stdin
     * @return non-empty-array<Input>
     */
    public function LoadCLIInputs(array $argv, array $server, $stdin) : array
    {
        $argIdx = 1;
        
        // global params for outmode, debug, config file, etc. come first
        for (; $argIdx < count($argv); $argIdx++)
        {
            $key = self::getKey($argv[$argIdx]);
            
            if ($key === null) break; else switch ($key)
            {
                case 'dryrun': $this->dryRun = true; break;
                
                case 'outprop':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no outprop value');
                    $outprop = (new SafeParam('outprop',$val))->GetUTF8String();
                    $this->outprop = $outprop;
                    break;
                }
                case 'outmode':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no outmode value');
                    $outmode = (new SafeParam('outmode',$val))->FromWhitelist(array_keys(self::OUTPUT_TYPES));
                    $this->outmode = self::OUTPUT_TYPES[$outmode];
                    break;
                }
                case 'debug':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no debug value');
                    $debug = (new SafeParam('debug',$val))->FromWhitelist(array_keys(Config::DEBUG_TYPES));
                    $this->debug = Config::DEBUG_TYPES[$debug];
                    break;
                }
                case 'metrics':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no metrics value');
                    $metrics = (new SafeParam('metrics',$val))->FromWhitelist(array_keys(Config::METRICS_TYPES));
                    $this->metrics = Config::METRICS_TYPES[$metrics];
                    break;
                }
                case 'dbconf':
                {
                    if (($val = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no dbconf path');
                    $this->dbconf = (new SafeParam('dbconf',$val))->GetFSPath();
                    break;
                }
                default: throw new IncorrectCLIUsageException('invalid global arg');
            }
        }
        
        // now process the actual app/action command(s)
        for (; $argIdx < count($argv); $argIdx++)
        {
            switch ($argv[$argIdx])
            {
                case 'version': die("Andromeda ".andromeda_version.PHP_EOL);

                case 'batch@':
                {
                    if (($fname = self::getNextValue($argv,$argIdx)) === null)
                        throw new IncorrectCLIUsageException('no batch path');
                    $lines = self::GetBatchFile($fname, $server,$stdin);
                    return $this->GetBatch($lines, $server, $stdin);
                }
                
                case 'batch': return self::GetBatch(array_slice($argv,$argIdx+1), $server,$stdin);
                default: return array(self::GetInput(array_slice($argv,$argIdx), $server,$stdin));
            }
        }

        throw new IncorrectCLIUsageException('missing app/action');
    }

    /** 
     * Reads an array of lines from a batch file
     * @param array<string, scalar> $server
     * @param resource $stdin
     * @return array<string> lines from the file
     * @throws Exceptions\UnknownBatchFileException if the filename is invalid
      */
    private function GetBatchFile(string $file, array $server, $stdin) : array
    {
        if (!is_file($file) || ($fdata = file_get_contents($file)) === false)
            throw new Exceptions\UnknownBatchFileException($file);
        
        return array_filter(explode("\n", $fdata));
    }
    
    /**
     * Fetches an Input object by reading it from the command line
     * @param array<int,string> $lines
     * @param array<string, scalar> $server
     * @param resource $stdin
     * @return non-empty-array<Input>
     * @throws EmptyBatchException if argv is empty
     * @throws Exceptions\BatchParseException if any line is invalid
     */
    private function GetBatch(array $lines, array $server, $stdin) : array
    {
        if (count($lines) === 0) throw new EmptyBatchException();
        
        return array_map(function($line)use($server,$stdin)
        {
            try { return self::GetInput(\Clue\Arguments\split($line),$server,$stdin); }
            catch (\InvalidArgumentException $e) { throw new Exceptions\BatchParseException($e); }
        }, $lines);
    }

    /** 
     * Fetches an Input object by reading it from the command line 
     * @param array<int,string> $argv
     * @param array<string, scalar> $server
     * @param resource $stdin
     */
    private function GetInput(array $argv, array $server, $stdin) : Input
    {
        if (count($argv) < 2) 
            throw new IncorrectCLIUsageException('missing app/action');
        $app = $argv[0]; $action = $argv[1];

        // add environment variables to argv
        foreach ($server as $key=>$value)
        { 
            $key = explode('_',$key,2); // TODO would be more efficient to check if it starts with andromeda first
            
            if (count($key) === 2 && $key[0] === 'andromeda')
            {
                if ($value === false) $value = "false";
                array_push($argv, "--".$key[1], (string)$value);
                // TODO these should be prepended so CLI takes precedence
            }
        };
        
        $params = new SafeParams(); $files = array();
 
        for ($i = 2; $i < count($argv); $i++)
        {
            $param = self::getKey($argv[$i]); 
            if ($param === null) throw new IncorrectCLIUsageException(
                "expected key at action arg $i");
            else if ($param === "") throw new IncorrectCLIUsageException(
                "empty key at action arg $i");
            
            $special = mb_substr($param, -1);

            // optionally load a param value from a file instead
            if ($special === '@')
            {
                $param = mb_substr($param,0,-1); 
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty @ key at action arg $i");
                
                $val = self::getNextValue($argv,$i);
                if ($val === null) throw new IncorrectCLIUsageException(
                    "expected @ value at action arg $i");
                
                if (!is_file($val) || ($fdat = file_get_contents($val)) === false) 
                    throw new Exceptions\InvalidFileException($val);
                
                $params->AddParam($param, trim($fdat)); // TODO do not trim here
            }
            // optionally get a param value interactively
            else if ($special === '!')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty ! key at action arg $i");
                
                echo "enter $param...".PHP_EOL; $inp = fgets($stdin);
                $val = ($inp === false) ? null : trim($inp, PHP_EOL);
                
                $params->AddParam($param, $val);
            }
            // optionally send the app a path/name of a file instead
            else if ($special === '%')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty % key at action arg $i");
                
                $val = self::getNextValue($argv,$i);
                if ($val === null) throw new IncorrectCLIUsageException(
                    "expected % value at action arg $i");
                
                if (!is_file($val)) throw new Exceptions\InvalidFileException($val);
                
                $filename = self::getNextValue($argv,$i) ?? $val;
                $filename = (new SafeParam('name',$filename))->GetFSName(); // TODO this seems pointless, remove?
                
                $files[$param] = new InputPath($val, $filename, false);
            }
            // optionally attach stdin to a file instead
            else if ($special === '-')
            {
                $param = mb_substr($param,0,-1);
                if ($param === "") throw new IncorrectCLIUsageException(
                    "empty - key at action arg $i");
                
                $files[$param] = new InputStream($stdin);
            }
            else // plain argument
            {
                $val = self::getNextValue($argv,$i);
                $params->AddParam($param, $val);
            }
        }
        
        return new Input($app, $action, $params, $files);
    }
    
    // TODO when I do a command that gives array input it's no longer removing ok/code and just showing appdata, it did on api-old...

    /** @param bool $exit if true, exit() with the proper code */
    public function FinalOutput(Output $output, bool $exit = true) : void
    {
        if ($this->outmode === self::OUTPUT_PLAIN)
        {
            // try echoing as a string, switch to printr if it fails
            $outstr = $output->GetAsString($this->outprop);
            if ($outstr !== null) echo $outstr.PHP_EOL; 
            else $this->outmode = self::OUTPUT_PRINTR;
        }
        
        if ($this->outmode === self::OUTPUT_PRINTR)
        {
            $outdata = $output->GetAsArray($this->outprop);
            echo print_r($outdata, true).PHP_EOL;
        }
        
        if ($this->outmode === self::OUTPUT_JSON)
        {
            $outdata = Utilities::JSONEncode(
                $output->GetAsArray($this->outprop));
            
            if ($multi = $this->isMultiOutput()) 
                echo static::formatSize(strlen($outdata));
        
            echo $outdata; if (!$multi) echo PHP_EOL;
        }
        
        if ($exit) exit($output->isOK() ? 0 : 1); // TODO go back to exiting with code like before? C++ CLIRunner needs to know if it's an error it can retry
        
        // TODO triple check that when download a file (outmode none) that an error just results in blank stdout output NOT error json
    }
}
