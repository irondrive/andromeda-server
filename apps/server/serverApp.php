<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Utilities.php");use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php");use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php");use Andromeda\Core\IOFormat\SafeParam;

use Andromeda\Core\UnknownActionException;

class ServerApp extends AppBase
{
    public function Run(Input $input)
    {
        $action = $input->GetAction();
        
        if ($action == 'random')        return $this->Random($input);
        else if ($action == 'version')  return $this->Version($input);
        else if ($action == 'getapps')  return $this->GetApps($input);
        else if ($action == 'dumpinput') return $this->DumpInput($input);
        
        else throw new UnknownActionException();
    }
    
    public function Random(Input $input) : array
    {
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT) ?? Utilities::IDLength;
        
        $random = Utilities::Random($length);
        
        return array('random'=>$random);
    }
    
    public function Version(Input $input) { return VERSION; }
    public function GetApps(Input $input) { return $this->API->GetServer()->GetApps(); }
    public function DumpInput(Input $input) { return print_r($input, true); }
}

