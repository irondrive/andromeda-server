<?php namespace Andromeda\Apps\Test; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, UnknownActionException};
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

/**
 * Utility app for the python test framework
 */
class TestApp extends AppBase
{    
    public static function getName() : string { return 'test'; }
    
    public static function getUsage() : array
    {
        $retval = array(
            'random [--length int]'
        );

        return $retval;
    }

    /**
     * {@inheritDoc}
     * @see AppBase::Run()
     */
    public function Run(Input $input)
    {                
        switch ($input->GetAction())
        {
            case 'random':  return $this->Random($input);          
            
            default: throw new UnknownActionException();
        }
    }

    /**
     * Generates a random value
     * @return string random value
     */
    protected function Random(Input $input) : string
    {        
        $length = $input->GetOptParam("length", SafeParam::TYPE_UINT);

        return Utilities::Random($length ?? 16);
    }
}

