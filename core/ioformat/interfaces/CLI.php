<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, MissingSingletonException};

require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/SafeParam.php");
require_once(ROOT."/core/ioformat/SafeParams.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParam,SafeParams,InputFile};

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/apps/server/serverApp.php"); use Andromeda\Apps\Server\ServerApp;

/** Exception indicating that the command line usage is incorrect */
class IncorrectCLIUsageException extends Exceptions\ClientErrorException { 
    public $message = "general usage: php index.php [--json|--printr] [--debug int] [--metrics int] [--dryrun] [--dbconf fspath] app action [--\$param value] [--\$param@ file] [--\$param% file [name]]\n".
                      " - param@ puts the content of the file in the parameter, param% uploads the file as a file (optionally with a new name)\n\n".
                      "batch usage:   php index.php batch myfile.txt\n".
                      "get version:   php index.php version\n".
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
            if (mb_substr($argv[$i],0,2) !== "--") break;

            switch ($argv[$i])
            {
                case '--dryrun': break;
                
                case '--json': $this->outmode = static::OUTPUT_JSON; break;
                case '--printr': $this->outmode = static::OUTPUT_PRINTR; break;
                
                case '--debug':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $this->debug = (new SafeParam('debug',$argv[++$i]))->GetValue(SafeParam::TYPE_UINT);
                    break;
                    
                case '--metrics':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $this->metrics = (new SafeParam('metrics',$argv[++$i]))->GetValue(SafeParam::TYPE_UINT);
                    break;
                    
                case '--dbconf':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $this->dbconf = (new SafeParam('dbfconf',$argv[++$i]))->GetValue(SafeParam::TYPE_FSPATH);
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
            if ($this->debug !== null)
                $config->SetDebugLevel($this->debug, true);
            
            if ($this->metrics !== null)
                $config->SetMetricsLevel($this->metrics, true);
        }
        
        global $argv;
        
        // process flags that are relevant for $config
        $i = 1; for (; $i < count($argv); $i++)
        {
            if (mb_substr($argv[$i],0,2) !== "--") break;

            switch($argv[$i])
            {
                case '--json': break;
                case '--printr': break;
                case '--debug': $i++; break;
                case '--metrics': $i++; break;
                case '--dbconf': $i++; break;
                    
                case '--dryrun': if ($config) $config->overrideReadOnly(Config::RUN_DRYRUN); break;

                default: throw new IncorrectCLIUsageException();
            }
        }
        
        // build an Input command from the rest of the command line
        for (; $i < count($argv); $i++)
        {
            switch($argv[$i])
            {                
                case 'version': die("Andromeda ".andromeda_version."\n"); break;
                
                case 'batch':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    return static::GetBatch($argv[$i+1]); break;
                    
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
    
    private $tmpfiles = array();
    
    /** Strips -- off the given string and returns (or false if not found) */
    private static function getKey(string $str)
    { 
        if (mb_substr($str,0,2) !== "--") return false; else return mb_substr($str,2);
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
            
            $val = (isset($argv[$i+1]) && !static::getKey($argv[$i+1])) ? $argv[++$i] : true;
            
            // optionally load a param value from a file instead
            if (mb_substr($param,-1) === '@')
            {
                $param = mb_substr($param,0,-1); 
                if (!$param) throw new IncorrectCLIUsageException();
                
                if (!is_readable($val)) throw new InvalidFileException();
                
                $val = trim(file_get_contents($val));
            }
            // optionally send the app a path/name of a file instead
            if (mb_substr($param,-1) === '%')
            {
                $param = mb_substr($param,0,-1);
                if (!$param) throw new IncorrectCLIUsageException();
                
                if (!is_readable($val)) throw new InvalidFileException();
                
                $tmpfile = tempnam(sys_get_temp_dir(),'andromeda_'); copy($val, $tmpfile);
                
                $filename = (isset($argv[$i+1]) && !static::getKey($argv[$i+1])) ? $argv[++$i] : $val;       
                
                $filename = (new SafeParam('name',basename($filename)))->GetValue(SafeParam::TYPE_FSNAME);

                $this->tmpfiles[] = $tmpfile;  
                
                $files[$param] = new InputFile($tmpfile, $filename);
            }
            else $params->AddParam($param, $val);
        }
        
        return new Input($app, $action, $params, $files);
    }
    
    public function __destruct()
    {
        foreach ($this->tmpfiles as $path) 
        {
            try { if (is_file($path)) unlink($path); }
            catch (\Throwable $e) { ErrorManager::GetInstance()->LogException($e); }
        }            
    }
    
    private $output_json = false;
    
    public function WriteOutput(Output $output)
    {
        $multi = $this->isMultiOutput();
        
        if (!$multi && $this->outmode === self::OUTPUT_PLAIN)
        {
            // try echoing as a string, switch to printr if it fails
            $outstr = $output->GetAsString();
            if ($outstr !== null) echo "$outstr\n";
            else $this->outmode = self::OUTPUT_PRINTR;
        }

        if (!$multi && $this->outmode === self::OUTPUT_PRINTR)
        {
            $outdata = $output->GetAsArray();
            echo print_r($outdata, true)."\n";
        }        
        
        if ($multi || $this->outmode === self::OUTPUT_JSON)
        {
            $outdata = $output->GetAsArray();
            
            $outdata = Utilities::JSONEncode($outdata)."\n";
            
            if ($multi) echo static::formatSize(strlen($outdata));
            
            echo $outdata;
        }

        exit($output->isOK() ? 0 : $output->GetHTTPCode());
    }
}
