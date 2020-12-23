<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, JSONDecodingException};

require_once(ROOT."/core/ioformat/Input.php"); 
require_once(ROOT."/core/ioformat/Output.php"); 
require_once(ROOT."/core/ioformat/IOInterface.php"); 
require_once(ROOT."/core/ioformat/SafeParam.php"); 
use Andromeda\Core\IOFormat\{Input,InputAuth,Output,IOInterface,SafeParam,SafeParams};
use Andromeda\Core\IOFormat\InvalidOutputException;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class NoAppActionException extends Exceptions\ClientErrorException { public $message = "APP_OR_ACTION_MISSING"; }
class InvalidBatchException extends Exceptions\ClientErrorException { public $message = "INVALID_BATCH_FORMAT"; }
class InvalidParamException extends Exceptions\ClientErrorException { public $message = "INVALID_PARAMETER_FORMAT"; }
class RemoteInvalidException extends Exceptions\ServerException { public $message = "INVALID_REMOTE_RESPONSE"; }

class AJAX extends IOInterface
{    
    public static function GetMode() : int { return IOInterface::MODE_AJAX; }
    
    public const DEBUG_ALLOW_GET = true;

    public static function isApplicable() : bool
    {
        return isset($_SERVER['HTTP_USER_AGENT']);
    }
    
    public function getAddress() : string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function getUserAgent() : string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }
  
    public static function GetDefaultOutmode() : int { return static::OUTPUT_JSON; }
    
    public function GetInputs(?Config $config) : array
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
                
                $inputs[$i] = static::GetInput($get, $req);
            }
            return $inputs;
        }
        else return array(static::GetInput($_GET, $_REQUEST));
    }
    
    private function GetInput(array $get, array $request) : Input
    {
        
        if (empty($get['app'])) throw new NoAppActionException();
        $app = $get['app']; unset($request['app']);
        
        if (empty($get['action'])) throw new NoAppActionException();
        $action = $get['action']; unset($request['action']);

        $params = new SafeParams();
        
        foreach (array_keys($request) as $key)
        {
            $params->AddParam($key, $request[$key]);
        }
        
        $files = array(); foreach($_FILES as $file)
        {
            if (!is_uploaded_file($file['tmp_name']) || $file['error']) continue;
            $fname = (new SafeParam('name',$file['name']))->GetValue(SafeParam::TYPE_FSNAME);
            $files[$fname] = $file['tmp_name']; 
        }
        
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
        $auth = ($user !== null && $pass !== null) ? (new InputAuth($user, $pass)) : null;

        return new Input($app, $action, $params, $files, $auth);
    }
    
    public function UserOutput(Output $output)
    {
        if (!headers_sent()) http_response_code($output->GetHTTPCode());
        
        parent::UserOutput($output);
    }
    
    public function FinalOutput(Output $output)
    {
        if ($this->outmode === self::OUTPUT_PLAIN)
        {
            try { echo $output->GetAsString(); } 
            catch (InvalidOutputException $e) { $this->outmode = self::OUTPUT_JSON; }
        }        
        
        if ($this->outmode === self::OUTPUT_PRINTR) 
        {
            $outdata = $output->GetAsArray();
            echo print_r($outdata, true);
        }        
        else if ($this->outmode === self::OUTPUT_JSON)
        {
            if (!headers_sent()) header("Content-type: application/json");
            $outdata = $output->GetAsArray();
            echo Utilities::JSONEncode($outdata);
        }
    }
    
    public static function GetRemoteURL(string $url, Input $input, bool $params = true)
    {
        $get = array('app'=>$input->GetApp(), 'action'=>$input->GetAction());
        if ($params) $get = array_merge($get, static::EncodeParams($input->GetParams()));
        return $url.(strpos($url,'?') === false ?'?':'&').http_build_query($get);
    }

    public static function RemoteRequest(string $url, Input $input) : array
    {
        $url = static::GetRemoteURL($url, $input, false);

        $data = static::HTTPPost($url, static::EncodeParams($input->GetParams()));
        if ($data === null) throw new RemoteInvalidException();

        try { return Utilities::JSONDecode($data); }
        catch (JSONDecodingException $e) { throw new RemoteInvalidException(); }
    }

    public static function EncodeParams(SafeParams $params) : array
    {
        $params = $params->GetParamsArray();

        $output = array(); foreach (array_keys($params) as $key)
        {
            $output[$key] = $params[$key]->GetValue(SafeParam::TYPE_RAW);
        }

        return $output;
    }

    public static function HTTPPost(string $url, array $post) : ?string
    {
        $options = array('http'=>array(
            'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            'method' => 'POST', 'content' => http_build_query($post)
        ));

        $result = file_get_contents($url, false, stream_context_create($options));

        if ($result === false) return null; else return $result;
    }
}


