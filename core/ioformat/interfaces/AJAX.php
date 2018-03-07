<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/ioformat/Input.php"); 
require_once(ROOT."/core/ioformat/Output.php"); 
require_once(ROOT."/core/ioformat/IOInterface.php"); 
require_once(ROOT."/core/ioformat/SafeParam.php"); 
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParams};

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class NoAppActionException extends Exceptions\Client400Exception { public $message = "APP_OR_ACTION_MISSING"; }
class InvalidParamException extends Exceptions\Client400Exception { public $message = "INVALID_PARAMETER_FORMAT"; }

class AJAX extends IOInterface
{    
    public static function GetMode() : int { return IOInterface::MODE_AJAX; }
    
    public const DEBUG_ALLOW_GET = true;
    
    public static function isApplicable() : bool
    {
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? '';
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || self::DEBUG_ALLOW_GET) 
            && ($scheme=='http' || $scheme=='https');
    }
    
    public function GetInput() : Input
    {
        if (empty($_REQUEST['app']) || empty($_REQUEST['action'])) { 
            throw new NoAppActionException(); }
        
        $app = $_REQUEST['app']; $action = $_REQUEST['action'];    
        unset($_REQUEST['app']); unset($_REQUEST['action']); unset($_REQUEST['server']);
        
        $params = new SafeParams();  
        
        foreach (array_keys($_REQUEST) as $key)
        {
            $param = explode('_',$key,2); $data = $_REQUEST[$key];
            
            if (count($param) != 2) { throw new InvalidParamException(); }
            
            $params->AddParam($param[0], $param[1], $_REQUEST[$key]);
        }
        
        return new Input($app, $action, $params);
    }
    
    public function WriteOutput(Output $output)
    {
        if (!headers_sent()) header("Content-type: application/json");
        http_response_code($output->GetResponseCode());
        
        try { echo Utilities::JSONEncode($output->GetData()); }
        catch (\Andromeda\Core\JSONEncodingException $e) { 
            echo Utilities::JSONEncode(Output::ServerException()->GetData()); }
    }
}


