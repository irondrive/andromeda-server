<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/SafeParam.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParams};

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class IncorrectCLIUsageException extends Exceptions\Client400Exception { public $message = "usage: php index.php app action [--app:param:type:data]"; }

class CLI extends IOInterface
{
    public static function GetMode() : int { return IOInterface::MODE_CLI; }
    
    public static function isApplicable() : bool
    {
        global $argv; return php_sapi_name() === "cli" && isset($argv);
    }
    
    public function GetInput() : Input
    {
        global $argv; $time = microtime(true);
        
        if (count($argv) < 3) { throw new IncorrectCLIUsageException(); }
        
        $app = $argv[1]; $action = $argv[2]; $params = new SafeParams();
        
        for ($i = 3; $i < count($argv); $i++)
        {
            if (substr($argv[$i],0,2) != "--") { throw new IncorrectCLIUsageException(); }
            
            $param = explode('_',substr($argv[$i],2),3);
            
            if ($param[0] == 'json') $this->output_json = true;
            
            else if ($param[0] == 'app')
            {
                if (count($param) != 3 || !isset($argv[$i+1])) { throw new IncorrectCLIUsageException(); }
                $params->AddParam($param[1], $param[2], $argv[$i+1]); $i++;
            }
        }
        
        foreach (array_keys($_SERVER) as $key)
        {           
            $value = $_SERVER[$key];
            $key = explode('_',$key,3);  
            
            if ($key[0] == 'andromeda' && count($key) == 3)
                $params->AddParam($key[1], $key[2], $value);
        }
        
        return new Input($app, $action, $params);
    }
    
    private $output_json = false;
    
    public function WriteOutput(Output $output)
    {
        if ($this->output_json)
        {
            try { echo Utilities::JSONEncode($output->GetData())."\n"; }
            catch (\Andromeda\Core\JSONEncodingException $e) {
                echo Utilities::JSONEncode(Output::ServerException()->GetData())."\n"; }
        }
        else echo print_r($output->GetData(), true)."\n";
        
        $response = $output->GetResponseCode();
        if ($response != 200) exit(-1); else exit(0);
    }
}
