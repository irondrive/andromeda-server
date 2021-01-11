<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, MissingSingletonException};

require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/SafeParam.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParam,SafeParams};
use Andromeda\Core\IOFormat\InvalidOutputException;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/apps/server/serverApp.php"); use Andromeda\Apps\Server\ServerApp;

class IncorrectCLIUsageException extends Exceptions\ClientErrorException { 
    public $message = "general usage:   php index.php [--json|--printr] [--debug int] [--dryrun] [--dbconf text] app action [--file name] [--\$param data]\n".
                      "batch/version:   php index.php [version | batch myfile.txt]\n".
                      "get all actions: php index.php server usage"; }

class UnknownBatchFileException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_BATCH_FILE"; }
class BatchFileParseException extends Exceptions\ClientErrorException { public $message = "BATCH_FILE_PARSE_ERROR"; }
class InvalidFileException extends Exceptions\ClientErrorException { public $message = "INACCESSIBLE_FILE"; }

class CLI extends IOInterface
{
    public static function GetMode() : int { return IOInterface::MODE_CLI; }
    
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    public function __construct()
    {
        parent::__construct();
        
        if (function_exists('pcntl_signal'))
        {
            pcntl_signal(SIGTERM, function()
            {
                try { Main::GetInstance()->rollback(false); }
                catch (MissingSingletonException $e) { }
            });
        }
    }
    
    public function Initialize() : void
    {        
        global $argv;
        
        // pre-process params that may be needed before $config is available        
        for ($i = 1; $i < count($argv); $i++)
        {
            if (substr($argv[$i],0,2) !== "--") break;

            switch ($argv[$i])
            {
                case '--dryrun': break;
                
                case '--json': $this->outmode = static::OUTPUT_JSON; break;
                case '--printr': $this->outmode = static::OUTPUT_PRINTR; break;
                
                case '--debug':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $this->debug = (new SafeParam('debug',$argv[++$i]))->GetValue(SafeParam::TYPE_INT);
                    break;
                    
                case '--dbconf':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $this->dbconf = (new SafeParam('dbfconf',$argv[++$i]))->GetValue(SafeParam::TYPE_TEXT);
                    break;

                default: throw new IncorrectCLIUsageException();
            }
        }
    }
    
    public function getAddress() : string
    {
        return "CLI ".($_SERVER['COMPUTERNAME']??'').':'.($_SERVER['USERNAME']??'');
    }
    
    public function getUserAgent() : string
    {
        return "CLI ".($_SERVER['OS']??'');
    }
    
    private ?int $debug = null;
    public function GetDebugLevel() : int { return $this->debug ?? Config::LOG_ERRORS; }
    
    private ?string $dbconf = null;
    public function GetDBConfigFile() : ?string { return $this->dbconf; }
    
    public static function GetDefaultOutmode() : int { return static::OUTPUT_PLAIN; }
    
    public function GetInputs(?Config $config) : array
    {
        if ($config && $this->debug !== null)
            $config->SetDebugLogLevel($this->debug, true);
        
        global $argv;
        
        // process flags that are relevant for $config
        $i = 1; for (; $i < count($argv); $i++)
        {
            if (substr($argv[$i],0,2) !== "--") break;

            switch($argv[$i])
            {
                case '--json': break;
                case '--printr': break;           
                case '--debug': $i++; break;        
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
                case 'version': die("Andromeda ".implode(".",ServerApp::getVersion())."\n"); break;
                
                case 'batch':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    return static::GetBatch($argv[$i+1]); break;
                    
                default: return array(static::GetInput(array_slice($argv, $i))); break;
            }
        }
        
        throw new IncorrectCLIUsageException();
    }
    
    private function GetBatch(string $file) : array
    {       
        try { $lines = explode("\n", file_get_contents($file)); }
        catch (Exceptions\PHPError $e) { throw new UnknownBatchFileException(); }
        
        require_once(ROOT."/core/libraries/php-arguments/src/functions.php");
        
        global $argv; $line2input = function($line) use ($argv)
        {
            try { $args = \Clue\Arguments\split($line); }
            catch (\InvalidArgumentException $e) { throw new BatchFileParseException(); }
            
            return static::GetInput($args);
        };
        
        return array_map($line2input, $lines);
    }
    
    private $tmpfiles = array();
    
    private function GetInput(array $argv) : Input
    {
        if (count($argv) < 2) throw new IncorrectCLIUsageException();
        
        $app = $argv[0]; $action = $argv[1]; $params = new SafeParams(); $files = array();
        
        for ($i = 2; $i < count($argv); $i++)
        {
            if (substr($argv[$i],0,2) !== "--") throw new IncorrectCLIUsageException();
            if (!isset($argv[$i+1]) || substr($argv[$i+1],0,2) === "--") throw new IncorrectCLIUsageException();
            
            $param = substr($argv[$i],2); $val = $argv[$i+1];
            
            if (in_array($param, array('file','move-file','copy-file')))
            {
                if (!is_readable($val)) throw new InvalidFileException();   
                
                $tmpfile = tempnam(sys_get_temp_dir(),'a2_');
                
                if ($param === 'move-file') 
                    rename($val, $tmpfile); 
                else copy($val, $tmpfile);
                
                $filename = $val;
                if (isset($argv[$i+2]) && substr($argv[$i+2],0,2) !== "--")
                    { $filename = $argv[$i+2]; $i++; }
                
                $filename = (new SafeParam('name',$filename))->GetValue(SafeParam::TYPE_FSNAME);

                array_push($this->tmpfiles, $tmpfile);
                $files[$filename] = $tmpfile; $i++;
            }
            else { $params->AddParam($param, $val); $i++; }
        }

        foreach (array_keys($_SERVER) as $key)
        {
            $value = $_SERVER[$key];
            $key = explode('_',$key,2);
            
            if ($key[0] == 'andromeda' && count($key) == 2)
                $params->AddParam($key[1], $value);
        }
        
        return new Input($app, $action, $params, $files);
    }
    
    public function __destruct()
    {
        foreach ($this->tmpfiles as $file) try { unlink($file); } 
            catch (\Throwable $e) { ErrorManager::GetInstance()->Log($e); }
    }
    
    private $output_json = false;
    
    public function FinalOutput(Output $output)
    {        
        if ($this->outmode === self::OUTPUT_PLAIN)
        {
            try { echo $output->GetAsString(boolval($this->debug))."\n"; } 
            catch (InvalidOutputException $e) { $this->outmode = self::OUTPUT_PRINTR; }
        }

        if ($this->outmode === self::OUTPUT_PRINTR)
        {
            $outdata = $output->GetAsArray(boolval($this->debug));
            echo print_r($outdata, true)."\n";
        }        
        else if ($this->outmode === self::OUTPUT_JSON)
        {
            $outdata = $output->GetAsArray(boolval($this->debug));
            echo Utilities::JSONEncode($outdata)."\n";
        }

        exit($output->GetOK() ? 0 : 1);
    }
}
