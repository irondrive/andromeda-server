<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class InvalidParseException extends Exceptions\ServerException { public $message = "PARSE_OUTPUT_INVALID"; }

class Output
{
    private $data;
    
    public function GetResponseCode() : int { return $this->data['code']; }
    
    public function SetMetrics(array $metrics) { $this->data['metrics'] = $metrics; }
    
    public function GetData() : array { return $this->data; }
    
    public static function Success($data) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>true,'code'=>200,'appdata'=>$data,'version'=>VERSION);
        
        return $output;
    }
    
    public static function ClientException(\Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>false,'code'=>$e->getCode(),'message'=>$e->getMessage(),'version'=>VERSION);
        
        if (isset($debug)) $output->data['debug'] = $debug;
        
        return $output;
    }
    
    public static function ServerException(?array $debug = null) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>false,'code'=>500,'message'=>'SERVER_ERROR','version'=>VERSION);
        
        if (isset($debug)) $output->data['debug'] = $debug;
        
        return $output;
    }

    public static function Parse(array $data)
    {
        if (!array_key_exists('ok',$data) || !array_key_exists('code',$data))
            throw new InvalidParseException();

        $ok = $data['ok']; $code = $data['code'];

        if ($ok)
        {
            if (!array_key_exists('appdata',$data)) throw new InvalidParseException();

            return $data['appdata'];
        }
        else
        {
            if (!array_key_exists('message',$data)) throw new InvalidParseException();

            if ($code >= 500)       throw new Exceptions\ServerException($data['message']);
            else if ($code == 404)  throw new Exceptions\Client404Exception($data['message']);
            else if ($code == 403)  throw new Exceptions\CLient403Exception($data['message']);
            else if ($code == 400)  throw new Exceptions\Client400Exception($data['message']);
            else throw new InvalidParseException();
        }
    }
    
}



