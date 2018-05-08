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
        else if ($action == 'getapps')  return $this->GetApps($input);
       
        else throw new UnknownActionException();
    }
    
    protected function Random(Input $input)
    {
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT);
        
        return Utilities::Random($length);
    }
    
    protected function GetApps(Input $input) { return $this->API->GetConfig()->GetApps(); }
}

