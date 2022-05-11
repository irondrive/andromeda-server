<?php namespace Andromeda\Apps\TestUtil; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/BaseApp.php"); use Andromeda\Core\{BaseApp, UnknownActionException};

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
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
        $params = $input->GetParams();
        
        switch ($input->GetAction())
        {
            case 'random':  return $this->Random($params);  
            case 'getinput': return $this->GetInput($input);
            
            case 'exception': $this->ServerException(); return;
            
            case 'check-dryrun': return $this->CheckDryRun();
            case 'binoutput': $this->BinaryOutput($params); return;
            
            default: throw new UnknownActionException();
        }
    }

    protected function Random(SafeParams $params) : string
    {
        $length = $params->GetOptParam('length',16)->GetUint();

        return Utilities::Random($length);
    }
    
    /** @return array<mixed> */
    protected function GetInput(Input $input) : array
    {
        $params = $input->GetParams()->GetClientObject();
        
        $files = array_map(function(InputStream $file){ 
            return $file->GetData(); }, $input->GetFiles());
        
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
    
    protected function BinaryOutput(SafeParams $params) : void
    {
        $this->API->GetInterface()->SetOutputMode(0);
        
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

