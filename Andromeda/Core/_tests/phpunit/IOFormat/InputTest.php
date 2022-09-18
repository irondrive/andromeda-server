<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; require_once("init.php");

use Andromeda\Core\{ApiPackage,Config};
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\Logging\ActionLog;

require_once(ROOT."/Core/IOFormat/Exceptions.php");

class InputTest extends \PHPUnit\Framework\TestCase
{
    public function testAppAction() : void
    {
        $input = new Input($app='testapp', $action='testact');
        $this->assertSame($app, $input->GetApp());
        $this->assertSame($action, $input->GetAction());
    }
    
    public function testSanitizeApp() : void
    {
        $this->expectException(SafeParamInvalidException::class);
        new Input('app%','action');
    }
    
    public function testSanitizeAction() : void
    {
        $this->expectException(SafeParamInvalidException::class);
        new Input('app','action<');
    }
    
    public function testAuth() : void
    {
        $input = new Input('app','action');
        $this->assertSame(null, $input->GetAuth());
        
        $auth = new InputAuth('username','password');
        $input = new Input('app','action', null,null, $auth);
        $this->assertSame($auth, $input->GetAuth());
    }
    
    public function testFiles() : void
    {
        $input = new Input('app','action');
        $this->assertSame(array(), $input->GetFiles());
        
        $this->assertFalse($input->HasFile('test'));
        $this->assertNull($input->TryGetFile('test'));
        $this->assertFalse($input->GetParams()->HasParam('test'));
        
        $stream1 = fopen('php://memory','rb');
        $stream2 = fopen('php://memory','rb');
        assert(is_resource($stream1));
        assert(is_resource($stream2));
        $file1 = new InputStream($stream1);
        $file2 = new InputStream($stream2);
        
        $files = array('file1'=>$file1);
        $input = new Input('app','action',null,$files);
        $this->assertSame($files, $input->GetFiles());

        $this->assertTrue($input->HasFile('file1'));
        $this->assertSame($file1, $input->GetFile('file1'));
        $this->assertSame($file1, $input->TryGetFile('file1'));
        
        $this->assertFalse($input->HasFile('file2'));
        $this->assertNull($input->TryGetFile('file2'));
        
        $input->AddFile('file2', $file2);
        
        $files['file2'] = $file2;
        $this->assertSame($files, $input->GetFiles());
        
        $this->assertTrue($input->HasFile('file2'));
        $this->assertSame($file2, $input->GetFile('file2'));
        $this->assertSame($file2, $input->TryGetFile('file2'));
        
        $this->expectException(InputFileMissingException::class);
        $input->GetFile('file3');
    }
    
    public function testParams() : void
    {
        $input = new Input('app','action');
        $this->assertInstanceOf(SafeParams::class, $input->GetParams());
        $this->assertSame(array(), $input->GetParams()->GetClientObject());
        
        $params = new SafeParams();
        $input = new Input('app','action',$params);
        $this->assertSame($input->GetParams(), $params);
    }
    
    private function getLogger(int $level) : ActionLog
    {        
        $config = $this->createStub(Config::class);
        $config->method('GetRequestLogDetails')->willReturn($level);
        
        $apipack = $this->createStub(ApiPackage::class);
        $apipack->method('GetConfig')->willReturn($config);
        
        $database = $this->createStub(ObjectDatabase::class);
        $database->method('GetApiPackage')->willReturn($apipack);
        
        return new ActionLog($database, array());
    }

    public function testLogger() : void
    {
        $arr = array('test1'=>'abc');
        $params = (new SafeParams())->LoadArray($arr);
        $input = new Input('app','action',$params);

        $input->SetLogger($logger = $this->getLogger(0));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertEmpty($logger->GetInputLogRef());
        $params->GetParam('test1',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        $this->assertEmpty($logger->GetInputLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::RQLOG_DETAILS_BASIC));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertEmpty($logger->GetInputLogRef());
        $params->GetParam('test1',SafeParams::PARAMLOG_ALWAYS)->GetAlphanum();
        $this->assertSame($arr, $logger->GetInputLogRef());
        
        $input->SetLogger($logger = $this->getLogger(Config::RQLOG_DETAILS_FULL));
        $params->GetParam('test1',SafeParams::PARAMLOG_ONLYFULL)->GetAlphanum();
        $this->assertSame($arr, $logger->GetInputLogRef());
    }
}
