<?php namespace Andromeda\Core\IOFormat; 

require_once("init.php");

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/Core/IOFormat/SafeParams.php");

class SafeParamsTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic() : void
    {
        $obj = new SafeParams();
        
        $this->assertFalse($obj->HasParam('test'));
        
        $obj->AddParam('test','value');
        
        $this->assertTrue($obj->HasParam('test'));
    }    
    
    public function testBadType() : void
    {
        $obj = (new SafeParams())->AddParam('test','value');
        
        $this->expectException(SafeParamInvalidException::class);
        
        $obj->GetParam('test', SafeParam::TYPE_INT);
    }
    
    public function testGetParamSuccess() : void
    {
        $obj = (new SafeParams())->AddParam('test','value');
        
        $this->assertSame($obj->GetParam('test', SafeParam::TYPE_RAW), 'value');
        $this->assertSame($obj->GetOptParam('test', SafeParam::TYPE_RAW), 'value');
        $this->assertSame($obj->GetNullParam('test', SafeParam::TYPE_RAW), 'value');
        $this->assertSame($obj->GetOptNullParam('test', SafeParam::TYPE_RAW), 'value');
    }
    
    public function testGetParamMissingGood() : void
    {
        $obj = (new SafeParams())->AddParam('test','value');
        
        $this->assertSame($obj->GetOptParam('test2', SafeParam::TYPE_RAW), null);
        $this->assertSame($obj->GetOptNullParam('test2', SafeParam::TYPE_RAW), null);
    }
    
    public function testGetParamMissingBad1() : void
    {
        $obj = (new SafeParams())->AddParam('test','value');
        
        $this->expectException(SafeParamKeyMissingException::class);
        
        $obj->GetParam('test2', SafeParam::TYPE_RAW);
    }
    
    public function testGetParamMissingBad2() : void
    {
        $obj = (new SafeParams())->AddParam('test','value');
        
        $this->expectException(SafeParamKeyMissingException::class);
        
        $obj->GetNullParam('test2', SafeParam::TYPE_RAW);
    }    
    
    public function testGetParamNullGood() : void
    {
        $obj = (new SafeParams())->AddParam('test',null);
        
        $this->assertSame($obj->GetNullParam('test', SafeParam::TYPE_RAW), null);
        $this->assertSame($obj->GetOptNullParam('test', SafeParam::TYPE_RAW), null);
    }    
    
    public function testGetParamNullBad1() : void
    {
        $obj = (new SafeParams())->AddParam('test',null);
        
        $this->expectException(SafeParamNullValueException::class);
        
        $obj->GetParam('test', SafeParam::TYPE_RAW);
    }
    
    public function testGetParamNullBad2() : void
    {
        $obj = (new SafeParams())->AddParam('test',null);
        
        $this->expectException(SafeParamNullValueException::class);
        
        $obj->GetOptParam('test', SafeParam::TYPE_RAW);
    }
    
    public function testGetClientObject() : void
    {       
        $obj = (new SafeParams())->AddParam('test1',75)->AddParam('test2',99);
        
        $this->assertSame(array('test1'=>75, 'test2'=>99), $obj->GetClientObject());
    }
    
    protected function isLogged(int $level, int $minlog, $type = SafeParam::TYPE_INT) : bool
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, $level);
        
        $obj->AddParam('test',55)->GetParam('test', $type, $minlog);
        
        return array_key_exists('test', $log) && $log['test'] === 55;
    }
    
    public function testLogging() : void
    {
        $this->assertFalse($this->isLogged(Config::RQLOG_DETAILS_FULL, SafeParams::PARAMLOG_ALWAYS, SafeParam::TYPE_RAW));
        
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
        
        $obj->AddParam('obj','{"test":75}')->GetParam('obj',SafeParam::TYPE_OBJECT)->GetParam('test',SafeParam::TYPE_INT);
        
        $this->assertSame(array('obj'=>array('test'=>75)), $log);
    }
    
    public function testArrayLog() : void
    {
        $log = array(); $obj = (new SafeParams())->SetLogRef($log, Config::RQLOG_DETAILS_FULL);
        
        $obj->AddParam('arr','[2,4,6,8]')->GetParam('arr',SafeParam::TYPE_ARRAY | SafeParam::TYPE_INT);
        
        $this->assertSame(array('arr'=>array(2,4,6,8)), $log);
    }
}
