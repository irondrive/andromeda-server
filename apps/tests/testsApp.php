<?php namespace Andromeda\Apps\Tests; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Utilities.php");use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php");use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php");use Andromeda\Core\IOFormat\SafeParam;

use Andromeda\Core\UnknownActionException;

class TestsApp extends AppBase
{
    public function Run(Input $input)
    {
        $action = $input->GetAction();
        
        if ($action == 'crash')        return $this->Crash($input);
        else if ($action == 'testjson')     return $this->TestJSON($input);
        else if ($action == 'dumpinput')    return $this->DumpInput($input);

        else throw new UnknownActionException();
    }    
    
    public function TestJSON(Input $input) : array
    {
        $test1 = $input->GetParam("test1", SafeParam::TYPE_INT | SafeParam::TYPE_ARRAY);
        $test2 = $input->GetParam("test2", SafeParam::TYPE_OBJECT)->GetParam("test5", SafeParam::TYPE_TEXT);
        $test3 = $input->GetParam("test2", SafeParam::TYPE_OBJECT)->GetParam("test3", SafeParam::TYPE_OBJECT)->GetParam("test4", SafeParam::TYPE_INT);
        
        return array($test1, $test2, $test3);
    }
    
    public function DumpInput(Input $input) { return print_r($input, true); }
}



