<?php namespace Andromeda\Apps\TestUtil; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/BaseApp.php"); use Andromeda\Core\{BaseApp, UnknownActionException};

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\OutputHandler;
require_once(ROOT."/Core/IOFormat/InputFile.php"); use Andromeda\Core\IOFormat\InputStream;

/**
 * Utility app for the python test framework
 */
class TestUtilApp extends BaseApp
{
    public static function getName() : string { return 'testutil'; }
    
    public static function getUsage() : array
    {
        $retval = array(
            'random [--length uint]',
            'getinput',
            'exception',
            'check-dryrun',
            'binoutput --data raw [--times uint]'
        );

        return $retval;
    }
    
    private ObjectDatabase $database;
    
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
            
            case 'exception': $this->ServerException(); return;
            
            case 'check-dryrun': return $this->CheckDryRun();
            case 'binoutput': $this->BinaryOutput($input); return;
            
            default: throw new UnknownActionException();
        }
    }

    protected function Random(Input $input) : string
    {
        $params = $input->GetParams();
        
        $length = $params->GetOptParam('length',16)->GetUint();

        return Utilities::Random($length);
    }
    
    protected function GetInput(Input $input) : array
    {
        $params = $input->GetParams()->GetClientObject();
        
        $files = array_map(function(InputStream $file){ return $file->GetData(); }, $input->GetFiles());
        
        return array('params'=>$params, 'files'=>$files);
    }
    
    protected function ServerException() : void
    {
        throw new Exceptions\ServerException('TEST_MESSAGE', 'some details', 5000);
    }    
    
    protected function CheckDryRun() : bool
    {
        return Config::GetInstance($this->database)->isDryRun();
    }
    
    protected function BinaryOutput(Input $input) : void
    {
        $this->API->GetInterface()->SetOutputMode(0);
        
        $params = $input->GetParams();
        
        $data = $params->GetParam('data')->GetRawString();
        $times = $params->GetOptParam('times',0)->GetUint();
        
        for ($i = 0; $i < $times; $i++)
        {
            $this->API->GetInterface()->RegisterOutputHandler(new OutputHandler(
                function()use($data,$i){ return strlen($data)*$i; },
                function(Output $output)use($data,$i){ echo str_repeat($data,$i); }
            ));
        }
    }
}

