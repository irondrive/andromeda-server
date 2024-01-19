<?php declare(strict_types=1); namespace Andromeda\Apps\Testutil; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseApp, Utilities};
use Andromeda\Core\Errors\BaseExceptions;
use Andromeda\Core\Exceptions\UnknownActionException;
use Andromeda\Core\IOFormat\{Input, InputFile, Output, OutputHandler, SafeParams};

/**
 * Utility app for the python test framework
 */
class TestutilApp extends BaseApp
{
    public function getName() : string { return 'testutil'; }
    
    public function getVersion() : string { return andromeda_version; }
    
    public function getUsage() : array
    {
        $retval = array(
            'random [--length uint]',
            'getinput',
            'exception',
            'check-dryrun'
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
            
            default: throw new UnknownActionException($input->GetAction());
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
        
        $files = array_map(function(InputFile $file) 
        { 
            $data = $file->GetData();
            if (Utilities::isUTF8($data)) return $data;
            else return strlen($data);
        }, $input->GetFiles());
        
        return array('params'=>$params, 'files'=>$files);
    }
    
    protected function ServerException() : void
    {
        throw new BaseExceptions\ServerException('TEST_MESSAGE', 'some details', 5000);
    }    
    
    protected function CheckDryRun() : bool
    {
        return $this->API->GetInterface()->isDryRun();
    }
}

