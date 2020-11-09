<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Utilities.php");use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php");use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php");use Andromeda\Core\IOFormat\SafeParam;

use Andromeda\Core\UnknownActionException;

class ServerApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public static function getUsage() : array
    {
        return array(
            'random [--length int]',
            'getapps', 'getusage', 'runtests'
        );
    }
    
    public function Run(Input $input)
    {
        switch($input->GetAction())
        {
            case 'random':  return $this->Random($input); break;
            case 'getapps': return $this->GetApps($input); break;
            case 'getusage': return $this->GetUsages($input); break;
            case 'runtests': return $this->RunTests($input); break;
            
            default: throw new UnknownActionException();
        }
    }
    
    protected function Random(Input $input)
    {
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT);   
        
        return Utilities::Random($length);
    }
    
    protected function GetApps(Input $input) 
    {
        return array_map(function($app){ return $app::getVersion(); }, $this->API->GetApps());
    }
    
    protected function GetUsages(Input $input)
    {
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            array_push($output, ...array_map(function($line)use($name){ return "$name $line"; }, $app::getUsage())); 
        }
        return implode("\n", $output);
    }

    protected function RunTests(Input $input)
    {
        set_time_limit(0);
        
        if ($this->API->GetDebugState())
        {
            return array_map(function($app)use($input){ return $app->Test($input); }, $this->API->GetApps());
        }
        else throw new UnknownActionException();
    }
}

