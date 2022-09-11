<?php declare(strict_types=1); namespace Andromeda\Apps\TestUtil; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseApp, Utilities};
require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\UnknownActionException;

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;
use Andromeda\Core\IOFormat\{Input, InputFile, Output, OutputHandler, SafeParams};

/**
 * Utility app for the python test framework
 */
class TestUtilApp extends BaseApp
{
    public function getName() : string { return 'testutil'; }
    
    public function getVersion() : string { return andromeda_version; }
    
    public function getUsage() : array
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
        
        $files = array_map(function(InputFile $file){ 
            return $file->GetData(); }, $input->GetFiles());
        
        return array('params'=>$params, 'files'=>$files);
    }
    
    protected function ServerException() : void
    {
        throw new Exceptions\ServerException('TEST_MESSAGE', 'some details', 5000);
    }    
    
    protected function CheckDryRun() : bool
    {
        return $this->API->GetInterface()->isDryRun();
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

