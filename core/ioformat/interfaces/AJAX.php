<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, JSONDecodingException};

require_once(ROOT."/core/ioformat/Input.php"); 
require_once(ROOT."/core/ioformat/Output.php"); 
require_once(ROOT."/core/ioformat/IOInterface.php"); 
require_once(ROOT."/core/ioformat/SafeParam.php"); 
require_once(ROOT."/core/ioformat/SafeParams.php"); 
use Andromeda\Core\IOFormat\{Input,InputAuth,Output,IOInterface,SafeParam,SafeParams,InputFile};

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the app or action parameters are missing */
class NoAppActionException extends Exceptions\ClientErrorException { public $message = "APP_OR_ACTION_MISSING"; }

/** Exception indicating that the parameter cannot be part of $_GET */
class IllegalGetFieldException extends Exceptions\ClientErrorException { public $message = "ILLEGAL_GET_FIELD"; }

/** Exception indicating the given batch sequence has too many actions */
class LargeBatchException extends Exceptions\ClientErrorException { public $message = "BATCH_TOO_LARGE"; }

/** Exception indicating that the remote response is invalid */
class RemoteInvalidException extends Exceptions\ServerException { public $message = "INVALID_REMOTE_RESPONSE"; }

/** The interface for using Andromeda over a web server */
class AJAX extends IOInterface
{
    public static function isApplicable() : bool
    {
        return isset($_SERVER['HTTP_USER_AGENT']);
    }
    
    /** @return false */
    public static function isPrivileged() : bool { return false; }
    
    public function getAddress() : string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function getUserAgent() : string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }
  
    /** @return int JSON output by default */
    public static function GetDefaultOutmode() : int { return static::OUTPUT_JSON; }
    
    /** 
     * Retrieves an array of input objects from the request to run 
     * 
     * Requests can put multiple requests to be run in a single 
     * transaction by using the batch paramter as an array
     */
    protected function subGetInputs(?Config $config) : array
    {
        if (isset($_GET['batch']) && is_array($_GET['batch']))
        {            
            $global_get = $_GET; unset($global_get['batch']);
            $global_files = $_FILES; unset($global_files['batch']);
            $global_request = $_REQUEST; unset($global_request['batch']);
            
            $inputs = array(); foreach(array_keys($_REQUEST['batch']) as $i)
            {
                $get = is_array($_GET['batch'][$i] ?? null) ? $_GET['batch'][$i] : array();
                $files = is_array($_FILES['batch'][$i] ?? null) ? $_FILES['batch'][$i] : array();
                $request = is_array($_REQUEST['batch'][$i] ?? null) ? $_REQUEST['batch'][$i] : array();

                $get = array_merge($global_get, $get);
                $files = array_merge($global_files, $files);
                $request = array_merge($global_request, $request);
                
                $inputs[$i] = static::GetInput($get, $files, $request);
            }
            
            if (count($inputs) > 65535) throw new LargeBatchException();
        }
        else $inputs = array(static::GetInput($_GET, $_FILES, $_REQUEST));
        
        return $inputs;
    }
    
    /** 
     * Fetches an input object from the HTTP request 
     * 
     * App and Action must be part of $_GET, everything else
     * can be interchangeably in $_GET or $_POST - except
     * 'password' and 'auth_' which cannot be in $_GET
     */
    private function GetInput(array $get, array $files, array $request) : Input
    {
        if (empty($get['app'])) throw new NoAppActionException();
        $app = $get['app']; unset($request['app']);
        
        if (empty($get['action'])) throw new NoAppActionException();
        $action = $get['action']; unset($request['action']);
        
        foreach (array_keys($get) as $key)
        {
            if (strpos($key,'password') !== false || strpos($key,'auth_') === 0)
                throw new IllegalGetFieldException($key);
        }
        
        $params = new SafeParams();
        
        foreach (array_keys($request) as $key)
        {
            $params->AddParam($key, $request[$key]);
        }
        
        $pfiles = array(); foreach ($files as $key=>$file)
        {
            if (!is_uploaded_file($file['tmp_name']) || $file['error']) continue;
            
            $fname = (new SafeParam('name',$file['name']))->GetValue(SafeParam::TYPE_FSNAME);
            
            $pfiles[$key] = new InputFile($file['tmp_name'], $fname); 
        }
        
        $user = $_SERVER['PHP_AUTH_USER'] ?? null;
        $pass = $_SERVER['PHP_AUTH_PW'] ?? null;
        
        $auth = ($user !== null && $pass !== null) ? (new InputAuth($user, $pass)) : null;

        return new Input($app, $action, $params, $pfiles, $auth);
    }
    
    public function UserOutput(Output $output) : bool
    {
        // need to send the HTTP response code before any output
        if (!headers_sent()) http_response_code($output->GetHTTPCode());
        
        return parent::UserOutput($output);
    }
    
    public function WriteOutput(Output $output)
    {
        $multi = $this->isMultiOutput();
        
        if (!$multi && $this->outmode === self::OUTPUT_PLAIN)
        {
            if (!headers_sent())
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
            }
            
            // try echoing as a string, switch to json if it fails
            $outstr = $output->GetAsString();
            if ($outstr !== null) echo $outstr;
            else $this->outmode = self::OUTPUT_JSON;
        }        
        
        if (!$multi && $this->outmode === self::OUTPUT_PRINTR) 
        {
            if (!headers_sent())
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
            }
            
            $outdata = $output->GetAsArray();
            echo print_r($outdata, true);
        }        
        
        if ($multi || $this->outmode === self::OUTPUT_JSON)
        {
            if (!headers_sent()) 
            {
                mb_http_output('UTF-8');
                header("Content-Type: application/json");
            }
            
            $outdata = $output->GetAsArray();
            
            $outdata = Utilities::JSONEncode($outdata);
            
            if ($multi) echo static::formatSize(strlen($outdata));
            
            echo $outdata;
        }
    }
    
    /** 
     * Build a remote Andromeda request URL
     * @param string $url the base URL of the API
     * @param Input $input the input object describing the request
     * @param bool $params if true, add all params from the $input to the URL
     * @return string the compiled URL string
     */
    public static function GetRemoteURL(string $url, Input $input, bool $params = true)
    {
        $get = array('app'=>$input->GetApp(), 'action'=>$input->GetAction());
        if ($params) $get = array_merge($get, static::EncodeParams($input->GetParams()));
        return $url.(strpos($url,'?') === false ?'?':'&').http_build_query($get);
    }

    /**
     * Send a request to a remote Andromeda API
     * @param string $url the base URL of the API
     * @param Input $input the input describing the request
     * @throws RemoteInvalidException if decoding the response fails
     * @return array the decoded remote response
     */
    public static function RemoteRequest(string $url, Input $input) : array
    {
        $url = static::GetRemoteURL($url, $input, false);

        $data = static::HTTPPost($url, $input->GetParams()->GetClientObject());
        if ($data === null) throw new RemoteInvalidException();

        try { return Utilities::JSONDecode($data); }
        catch (JSONDecodingException $e) { throw new RemoteInvalidException(); }
    }

    /**
     * Helper function to send an HTTP post request
     * @param string $url the URL of the request
     * @param array $post array of data to place in the POST body
     * @return string|NULL the remote response
     */
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


