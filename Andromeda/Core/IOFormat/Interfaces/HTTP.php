<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\JSONException;

use Andromeda\Core\IOFormat\{Input,InputAuth,InputPath,IOInterface,Output,SafeParam,SafeParams};

require_once(ROOT."/Core/IOFormat/Exceptions.php"); use Andromeda\Core\IOFormat\EmptyBatchException;
require_once(ROOT."/Core/IOFormat/Interfaces/Exceptions.php");

/** The interface for using Andromeda over a web server */
class HTTP extends IOInterface
{
    public static function isApplicable() : bool
    {
        return isset($_SERVER['HTTP_USER_AGENT']);
    }
    
    /** @return false */
    public static function isPrivileged() : bool { return false; }
    
    /** @return int JSON output by default */
    public static function GetDefaultOutmode() : int { return static::OUTPUT_JSON; }
    
    public function getAddress() : string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    public function getUserAgent() : string
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    /** 
     * Retrieves an array of input objects from the request to run 
     * 
     * Requests can put multiple requests to be run in a single 
     * transaction by using the batch paramter as an array
     */
    protected function subLoadInputs() : array
    {
        return $this->LoadHTTPInputs($_REQUEST, $_GET, $_FILES, $_SERVER);
    }
    
    /**
     * Retries an array of input objects to run
     * @param array<scalar, scalar|array<scalar, scalar|array<scalar, scalar>>> $req
     * @param array<scalar, scalar|array<scalar, scalar|array<scalar, scalar>>> $get
     * @param array<scalar, mixed> $files
     * @param array<string, scalar> $server
     * @return non-empty-array<Input>
     */
    public function LoadHTTPInputs(array $req, array $get, array $files, array $server) : array
    {
        if ($server['REQUEST_METHOD'] !== "GET" && 
            $server['REQUEST_METHOD'] !== "POST")
            throw new MethodNotAllowedException();

        if (isset($req['_bat']))
        {            
            $breq = $req['_bat'];
            $bget = $get['_bat'] ?? array();
            $bfiles = $files['_bat'] ?? array();
            
            if (!is_array($bget) || !is_array($bfiles) || !is_array($breq))
                throw new BatchSyntaxInvalidException('batch not array');

            $global_req = $req; // copy
            unset($global_req['_bat']);
            $global_get = $get; // copy
            unset($global_get['_bat']);
            $global_files = $files; // copy
            unset($global_files['_bat']);
            
            $inputs = array(); foreach($breq as $bkeyI=>$breqI)
            {
                $bgetI = $bget[$bkeyI] ?? array();
                $bfilesI = $bfiles[$bkeyI] ?? array();
                
                if (!is_array($bgetI) || !is_array($bfilesI) || !is_array($breqI))
                    throw new BatchSyntaxInvalidException("batch $bkeyI not array");

                // merge with global params that apply to all
                $breqI += $global_req;
                $bgetI += $global_get;
                $bfilesI += $global_files;

                $inputs[$bkeyI] = self::GetInput($breqI, $bgetI, $bfilesI, $server);
            }
            
            if (!count($inputs)) throw new EmptyBatchException();
            if (count($inputs) > 65535) throw new LargeBatchException();
        }
        else $inputs = array(self::GetInput($req, $get, $files, $server));
        
        return $inputs;
    }

    /** 
     * Fetches an input object from the HTTP request 
     * 
     * App and Action must be part of $_GET, everything else
     * can be interchangeably in $_GET or $_POST except
     * 'password' and 'auth_' which cannot be in $_GET
     * @param array<scalar, scalar|array<scalar, scalar|array<scalar, scalar>>> $req
     * @param array<scalar, scalar|array<scalar, scalar|array<scalar, scalar>>> $get
     * @param array<scalar, mixed> $files
     * @param array<string, scalar> $server
     */
    private function GetInput(array $req, array $get, array $files, array $server) : Input
    {
        if (empty($get['_app']) || empty($get['_act']))
            throw new MissingAppActionException('missing');
        
        $app = $get['_app']; unset($req['_app']); // app
        $act = $get['_act']; unset($req['_act']); // action
        
        if (!is_string($app) || !is_string($act))
            throw new MissingAppActionException('not strings');
        
        foreach ($get as $key=>$val)
        {
            $key = (string)$key;
            if (strpos($key,'password') !== false 
                || strpos($key,'auth_') === 0)
                throw new IllegalGetFieldException($key);
        }
        
        $params = new SafeParams();
        $params->LoadArray($req);

        $pfiles = array(); foreach ($files as $key=>$file)
        {
            if (!is_array($file)
                || !array_key_exists('tmp_name',$file)
                || !array_key_exists('name',$file)
                || !array_key_exists('error',$file))
                throw new FileUploadFormatException();
            
            $fpath = (string)$file['tmp_name'];
            $fname = (string)$file['name'];
            $ferror = (int)$file['error'];
            // https://www.php.net/manual/en/features.file-upload.errors.php
                
            if ($ferror || !is_uploaded_file($fpath))
                throw new FileUploadFailException((string)$ferror);
            
            $fname = (new SafeParam('name',$fname))->GetFSName();
            $pfiles[(string)$key] = new InputPath($fpath, $fname, true); 
        }
        
        $user = $server['PHP_AUTH_USER'] ?? null;
        $pass = $server['PHP_AUTH_PW'] ?? null;
        
        $auth = ($user !== null && $pass !== null)
            ? new InputAuth((string)$user, (string)$pass) : null;

        return new Input($app, $act, $params, $pfiles, $auth);
    }
    
    /**
     * Sends the no-cache header and, if UserOutput, the HTTP code
     * @param Output $output output object
     */
    private function InitOutput(Output $output) : void
    {
        if (!headers_sent())
        {
            if (!$this->outmode)
                http_response_code($output->GetCode());
            
            header("Cache-Control: no-cache");
            
            if ($this->isMultiOutput())
            {
                header("Content-Type: application/octet-stream");
                header('Content-Transfer-Encoding: binary');
            }
        }
    }
    
    public function UserOutput(Output $output) : bool
    {
        $this->InitOutput($output);
        
        return parent::UserOutput($output);
    }
    
    public function FinalOutput(Output $output) : void
    {
        $this->InitOutput($output);

        if ($this->outmode === self::OUTPUT_PLAIN)
        {
            if (!headers_sent())
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
                http_response_code($output->GetCode());
            }
            
            // try echoing as a string, switch to json if it fails
            $outstr = $output->GetAsString();
            if ($outstr !== null) echo $outstr;
            else $this->outmode = self::OUTPUT_JSON;
        }        
        
        if ($this->outmode === self::OUTPUT_PRINTR) 
        {
            if (!headers_sent())
            {
                mb_http_output('UTF-8');
                header("Content-Type: text/plain");
            }
            
            $outdata = $output->GetAsArray();
            echo print_r($outdata, true);
        }
        
        if ($this->outmode === self::OUTPUT_JSON)
        {
            $multi = $this->isMultiOutput();
            
            if (!$multi && !headers_sent()) 
            {
                mb_http_output('UTF-8');
                header("Content-Type: application/json");
            }
            
            $outdata = Utilities::JSONEncode($output->GetAsArray());
            
            if ($multi) echo parent::formatSize(strlen($outdata));
            
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
        $get = array('_app'=>$input->GetApp(), '_act'=>$input->GetAction());
        if ($params) $get = array_merge($get, $input->GetParams()->GetClientObject());
        return $url.(strpos($url,'?') === false ?'?':'&').http_build_query($get);
    }

    /**
     * Send a request to a remote Andromeda API
     * @param string $url the base URL of the API
     * @param Input $input the input describing the request
     * @throws RemoteInvalidException if decoding the response fails
     * @return array<mixed> the decoded remote response
     */
    public static function RemoteRequest(string $url, Input $input) : array
    {
        $url = static::GetRemoteURL($url, $input, false);

        $data = static::HTTPPost($url, $input->GetParams()->GetClientObject());
        if ($data === null) throw new RemoteInvalidException();

        try { return Utilities::JSONDecode($data); }
        catch (JSONException $e) { throw new RemoteInvalidException(); }
    }

    /**
     * Helper function to send an HTTP post request
     * @param string $url the URL of the request
     * @param array<string, mixed> $post array of data to place in the POST body
     * @return ?string the remote response
     */
    public static function HTTPPost(string $url, array $post) : ?string
    {
        foreach ($post as &$val) $val ??= "null";
        
        $options = array('http'=>array(
            'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            'method' => 'POST', 'content' => http_build_query($post)
        ));

        $result = file_get_contents($url, false, stream_context_create($options));

        if ($result === false) return null; else return $result;
    }
}


