<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/SafeParam.php");
use Andromeda\Core\IOFormat\{Input,Output,Address,IOInterface,SafeParam,SafeParams};
use Andromeda\Core\IOFormat\InvalidOutputException;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/apps/server/serverApp.php"); use Andromeda\Apps\Server\ServerApp;

class IncorrectCLIUsageException extends Exceptions\Client400Exception { public $message = 'usage: php index.php --$flag $app $action [--app_$type_$key $data]'; }

class UnknownBatchFileException extends Exceptions\Client400Exception { public $message = "UNKNOWN_BATCH_FILE"; }
class BatchFileParseException extends Exceptions\Client400Exception { public $message = "BATCH_FILE_PARSE_ERROR"; }
class InvalidFileException extends Exceptions\Client400Exception { public $message = "INACCESSIBLE_FILE"; }

class CLI extends IOInterface
{
    public static function GetMode() : int { return IOInterface::MODE_CLI; }
    
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    public function getAddress() : string
    {
        return "CLI ".($_SERVER['COMPUTERNAME']??'').':'.($_SERVER['USERNAME']??'');
    }
    
    public function getUserAgent() : string
    {
        return "CLI ".($_SERVER['OS']??'');
    }
    
    const OUTPUT_PLAIN = 0; const OUTPUT_PRINTR = 1; const OUTPUT_JSON = 2;
    
    private $outmode = self::OUTPUT_PLAIN;
    
    public function GetInputs(Config $config) : array
    {
        global $argv; $time = microtime(true);
        
        for ($i = 1; $i < count($argv); $i++)
        {
            switch($argv[$i])
            {
                case '--json': $this->outmode = self::OUTPUT_JSON; break;
                case '--printr': $this->outmode = self::OUTPUT_PRINTR; break;
                
                case '--debug':
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    $config->SetDebugLogLevel((new SafeParam('int',$argv[$i+1]))->getData()); $i++; break;   
                    
                case '--dryrun': $config->SetReadOnly(Config::RUN_DRYRUN); break;
                
                case 'version': die("Andromeda ".implode(".",ServerApp::getVersion())."\n"); break;
                
                case 'batch':     
                    if (!isset($argv[$i+1])) throw new IncorrectCLIUsageException();
                    return self::GetBatch($argv[$i+1]); break;
                    
                case 'exec': case 'run': $i++; 
                default: return array(self::GetInput(array_slice($argv, $i))); break;                    
            }
        }
        
        throw new IncorrectCLIUsageException();
    }
    
    private function GetBatch(string $file) : array
    {
        global $argv;
        
        try { $lines = explode("\n", file_get_contents($file)); }
        catch (Exceptions\PHPException $e) { throw new UnknownBatchFileException(); }
        
        require_once(ROOT."/core/libraries/php-arguments/src/functions.php");
        
        $line2input = function($line) use ($argv)
        {
            try { $args = \Clue\Arguments\split($line); }
            catch (\InvalidArgumentException $e) { throw new BatchFileParseException(); }
            
            return self::GetInput($args);
        };
        
        return array_map($line2input, $lines);
    }
    
    private function GetInput(array $argv) : Input
    {
        if (count($argv) < 2) { throw new IncorrectCLIUsageException(); }
        
        $app = $argv[0]; $action = $argv[1]; $params = new SafeParams(); $files = array();
        
        for ($i = 2; $i < count($argv); $i++)
        {
            if (substr($argv[$i],0,2) !== "--") { throw new IncorrectCLIUsageException(); }
            
            $param = explode('_',substr($argv[$i],2),3);
            
            if ($param[0] == 'app')
            {
                if (count($param) != 3 || !isset($argv[$i+1])) { throw new IncorrectCLIUsageException(); }
                $params->AddParam($param[1], $param[2], $argv[$i+1]); $i++;
            }
            
            else if ($param[0] == 'file')
            {
                while (isset($argv[$i+1]) && substr($argv[$i+1],0,2) !== "--")
                {
                    if (!is_readable($argv[$i+1])) throw new InvalidFileException();
                    array_push($files, $argv[$i+1]); $i++;
                }
            }
        }

        foreach (array_keys($_SERVER) as $key)
        {
            $value = $_SERVER[$key];
            $key = explode('_',$key,3);
            
            if ($key[0] == 'andromeda' && count($key) == 3)
                $params->AddParam($key[1], $key[2], $value);
        }
        
        $addr = "CLI ".($_SERVER['COMPUTERNAME']??'').':'.($_SERVER['USERNAME']??'');
        $agent = "CLI ".($_SERVER['OS']??'');
        $addrobj = new Address($addr, $agent);
        
        return new Input($app, $action, $params, $addrobj, $files);
    }
    
    private $output_json = false;
    
    public function WriteOutput(Output $output)
    {
        $outdata = $output->GetAsArray();
        if ($this->outmode == self::OUTPUT_PLAIN)
        {
            try { echo $output->GetAsString(); } catch (InvalidOutputException $e) { $this->outmode = self::OUTPUT_PRINTR; }
        }
        if ($this->outmode == self::OUTPUT_PRINTR) echo print_r($outdata, true)."\n";
        if ($this->outmode == self::OUTPUT_JSON)   echo Utilities::JSONEncode($outdata)."\n";

        $response = $output->GetHTTPCode();
        if ($response != 200) exit(1); else exit(0);
    }
}
