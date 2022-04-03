<?php namespace Andromeda\Core\IOFormat; 

require_once("init.php");

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/Core/IOFormat/SafeParams.php");

class SafeParamsTest extends \PHPUnit\Framework\TestCase
{
    public function testHasParam() : void
    {
        $obj = new SafeParams();
        $obj->AddParam('test','value');
        
        $this->assertTrue($obj->HasParam('test'));
        $this->assertSame('value',$obj->GetParam('test')->GetRawString());
        $this->assertSame('value',$obj->GetOptParam('test','def')->GetRawString());
    }
    
    public function testNotHasParam() : void
    {
        $obj = new SafeParams();
        
        $this->assertFalse($obj->HasParam('test'));

        $this->assertSame(null,$obj->GetOptParam('test',null)->GetRawString());
        
        $this->assertSame(0,$obj->GetOptParam('test',0)->GetInt());
        $this->assertSame(1,$obj->GetOptParam('test',1)->GetInt());
        
        $this->assertSame(false,$obj->GetOptParam('test',false)->GetBool());
        $this->assertSame(false,$obj->GetOptParam('test',false)->GetNullBool());
        $this->assertSame(true,$obj->GetOptParam('test',true)->GetBool());
        $this->assertSame(true,$obj->GetOptParam('test',true)->GetNullBool());
        
        $this->assertSame('test',$obj->GetOptParam('test','test')->GetRawString());
        
        $this->expectException(SafeParamKeyMissingException::class);
        $obj->GetParam('test');
    }

    public function testGetClientObject() : void
    {       
        $obj = (new SafeParams())->AddParam('test1','75')->AddParam('test2','99');
        
        $this->assertSame(array('test1'=>'75', 'test2'=>'99'), $obj->GetClientObject());
    }
    
    protected function isLogged(int $level, int $minlog) : bool
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, $level);
        $obj->AddParam('test','55')->GetParam('test',$minlog)->GetInt();
        $log1 = array_key_exists('test', $log) && $log['test'] === 55;
        
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, $level);
        $obj->AddParam('test','55')->GetOptParam('test',false,$minlog)->GetInt();
        $log2 = array_key_exists('test', $log) && $log['test'] === 55;
        
        $this->assertSame($log1, $log2); return $log1;
    }
    
    public function testLogging() : void
    {
        $this->assertTrue($this->isLogged(Config::RQLOG_DETAILS_FULL, SafeParams::PARAMLOG_ALWAYS));
        $this->assertTrue($this->isLogged(Config::RQLOG_DETAILS_FULL, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(Config::RQLOG_DETAILS_FULL, SafeParams::PARAMLOG_NEVER));
        
        $this->assertTrue($this->isLogged(Config::RQLOG_DETAILS_BASIC, SafeParams::PARAMLOG_ALWAYS));
        $this->assertFalse($this->isLogged(Config::RQLOG_DETAILS_BASIC, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(Config::RQLOG_DETAILS_BASIC, SafeParams::PARAMLOG_NEVER));
        
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_ALWAYS));
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_ONLYFULL));
        $this->assertFalse($this->isLogged(0, SafeParams::PARAMLOG_NEVER));
    }
    
    public function testNestedLog() : void
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, Config::RQLOG_DETAILS_FULL);
        
        $obj->AddParam('obj','{"test":75}')->GetParam('obj')->GetObject()->GetParam('test')->GetInt();
        
        $this->assertSame(array('obj'=>array('test'=>75)), $log);
    }
}
