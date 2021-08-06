<?php namespace Andromeda\Apps\Test; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, UnknownActionException};

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\{IOInterface, OutputHandler};

class TestServerException extends Exceptions\ServerException { public $message = "TEST_SERVER_EXCEPTION"; }

/**
 * Utility app for the python test framework
 */
class TestApp extends AppBase
{    
    public static function getName() : string { return 'test'; }
    
    public static function getUsage() : array
    {
        $retval = array(
            'random [--length int]',
            'getinput',
            'exception',
            'check-dryrun',
            'binoutput --data raw [--times int]'
        );

        return $retval;
    }
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        
        $this->database = $api->GetDatabase();
    }

    public function Run(Input $input)
    {
        switch ($input->GetAction())
        {
            case 'random':  return $this->Random($input);  
            case 'getinput': return $this->GetInput($input);
            
            case 'exception': return $this->ServerException();
            
            case 'check-dryrun': return $this->CheckDryRun();
            case 'binoutput': return $this->BinaryOutput($input);
            
            default: throw new UnknownActionException();
        }
    }

    protected function Random(Input $input) : string
    {        
        $length = $input->GetOptParam("length", SafeParam::TYPE_UINT);

        return Utilities::Random($length ?? 16);
    }
    
    protected function GetInput(Input $input) : array
    {
        return $input->GetParams()->GetClientObject();
    }
    
    protected function ServerException() : void
    {
        throw new TestServerException();
    }    
    
    protected function CheckDryRun() : int
    {
        return Config::GetInstance($this->database)->isDryRun();
    }
    
    protected function BinaryOutput(Input $input) : void
    {
        $this->API->GetInterface()->SetOutputMode(0);
        
        $data = $input->GetParam('data',SafeParam::TYPE_RAW);

        for ($i = 0; $i < ($input->GetOptParam('times',SafeParam::TYPE_INT) ?? 0); $i++)
        {
            $this->API->GetInterface()->RegisterOutputHandler(new OutputHandler(
                function()use($data,$i){ return strlen($data)*$i; },
                function(Output $output)use($data,$i){ echo str_repeat($data,$i); }
            ));
        }
    }
}

