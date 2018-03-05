<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

use \Throwable;

class Output
{
    private $data;
    
    public function GetResponseCode() : int { return $this->data['code']; }
    
    public function SetMetrics(array $metrics) { $this->data['metrics'] = $metrics; }
    
    public function GetData() : array { return $this->data; }
    
    public static function Success($data) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>true,'code'=>200,'appdata'=>$data);
        
        return $output;
    }
    
    public static function ClientException(Throwable $e, ?array $debug = null) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>false,'code'=>$e->getCode(),'message'=>$e->getMessage());
        
        if (isset($debug)) $output->data['debug'] = $debug;
        
        return $output;
    }
    
    public static function ServerException(?array $debug = null) : Output
    {
        $output = new Output();
        
        $output->data = array('ok'=>false,'code'=>500,'message'=>'SERVER_ERROR');
        
        if (isset($debug)) $output->data['debug'] = $debug;
        
        return $output;
    }
    
}



