<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, JSONDecodingException};

require_once(ROOT."/core/ioformat/Input.php"); 
require_once(ROOT."/core/ioformat/Output.php"); 
require_once(ROOT."/core/ioformat/IOInterface.php"); 
require_once(ROOT."/core/ioformat/SafeParam.php"); 
use Andromeda\Core\IOFormat\{Input,Output,IOInterface,SafeParams};

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class NoAppActionException extends Exceptions\Client400Exception { public $message = "APP_OR_ACTION_MISSING"; }
class InvalidBatchException extends Exceptions\Client400Exception { public $message = "INVALID_BATCH_FORMAT"; }
class InvalidParamException extends Exceptions\Client400Exception { public $message = "INVALID_PARAMETER_FORMAT"; }
class RemoteInvalidException extends Exceptions\ServerException { public $message = "INVALID_REMOTE_RESPONSE"; }

class AJAX extends IOInterface
{    
    public static function GetMode() : int { return IOInterface::MODE_AJAX; }
    
    public const DEBUG_ALLOW_GET = true;

    public static function isApplicable() : bool
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || self::DEBUG_ALLOW_GET);
    }
    
    public function getAddress() : string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function getUserAgent() : string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }
  
    public function GetInputs(Config $config) : array
    {
        if (isset($_GET['batch']) && is_array($_GET['batch']))
        {
            $global_req = $_REQUEST; unset($global_req['batch']);
            $global_get = $_GET; unset($global_get['batch']);
            
            $inputs = array(); foreach(array_keys($_REQUEST['batch']) as $i)
            {
                $req = is_array($_REQUEST['batch'][$i] ?? null) ? $_REQUEST['batch'][$i] : array();
                $get = is_array($_GET['batch'][$i] ?? null) ? $_GET['batch'][$i] : array();

                $req = array_merge($global_req, $req);
                $get = array_merge($global_get, $get);
                
                $inputs[$i] = self::GetInput($get, $req);
            }
            return $inputs;
        }
        else return array(self::GetInput($_GET, $_REQUEST));
    }
    
    private function GetInput(array $get, array $request) : Input
    {
        foreach (array('app','action') as $key)
        {
            if (empty($get[$key])) throw new NoAppActionException();
            $$key = $get[$key]; unset($request[$key]);
        }

        $params = new SafeParams();
        
        foreach (array_keys($request) as $key)
        {
            $param = explode('_',$key,2);
            
            if (count($param) != 2) throw new InvalidParamException(implode('_',$param));
            
            $params->AddParam($param[0], $param[1], $request[$key]);
        }
        
        $files = array_map(function($f){ return $f['tmp_name']; }, $_FILES);
        
        return new Input($app, $action, $params, $files);
    }
    
    public function WriteOutput(Output $output)
    {
        if (!headers_sent()) header("Content-type: application/json");
        http_response_code($output->GetHTTPCode());
        
        echo Utilities::JSONEncode($output->GetAsArray());
    }

    public static function RemoteRequest(string $url, Input $input) : array
    {
        $get = array('app'=>$input->GetApp(), 'action'=>$input->GetAction());

        $data = self::HTTPPost($url, $get, self::EncodeParams($input->GetParams()));
        if ($data === null) throw new RemoteInvalidException();

        try { return Utilities::JSONDecode($data); }
        catch (JSONDecodingException $e) { throw new RemoteInvalidException(); }
    }

    public static function EncodeParams(SafeParams $params) : array
    {
        $params = $params->GetParamsArray();

        $output = array(); foreach (array_keys($params) as $key)
        {
            $param = $params[$key];

            $key = $param->GetTypeString()."_".$key;
            $value = $param->GetData();

            $output[$key] = $value;
        }

        return $output;
    }

    public static function HTTPPost(string $url, array $get, array $post) : ?string
    {
        $url .= ((strpos($url,'?')<0)?'?':'&').http_build_query($get);

        $options = array('http'=>array(
            'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            'method' => 'POST', 'content' => http_build_query($post)
        ));

        $result = file_get_contents($url, false, stream_context_create($options));

        if ($result === false) return null; else return $result;
    }
}


