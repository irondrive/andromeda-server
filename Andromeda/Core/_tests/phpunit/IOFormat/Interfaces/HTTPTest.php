<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat\Interfaces; require_once("init.php");

use Andromeda\Core\Utilities;
use Andromeda\Core\IOFormat\Exceptions\EmptyBatchException;
use Andromeda\Core\IOFormat\{Input, InputAuth, Output, OutputHandler, SafeParam, SafeParams};

class HTTPTest extends \PHPUnit\Framework\TestCase
{
    public function testStatics() : void
    {
        $this->assertFalse(HTTP::isPrivileged());
        $this->assertSame(HTTP::OUTPUT_JSON, HTTP::GetDefaultOutmode());
        
        $http = new HTTP();
        $this->assertFalse($http->isDryRun());
        $this->assertNull($http->GetDBConfigFile());
        $this->assertSame(0, $http->GetDebugLevel());
        $this->assertSame(0, $http->GetMetricsLevel());
    }

    public function testMethod() : void
    {
        $this->expectException(Exceptions\MethodNotAllowedException::class);
        (new HTTP())->LoadHTTPInputs([], [], [], ['REQUEST_METHOD'=>'PUT']);
    }
    
    public function testMissingApp() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInputs([], ['_app'=>'myapp'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testMissingAction() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInputs([], ['_act'=>'myact'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testAppActionStrings() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInputs([], ['_app'=>'myapp','_act'=>[]], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testAppActionGet() : void
    {
        $this->expectException(Exceptions\MissingAppActionException::class);
        (new HTTP())->LoadHTTPInputs(['_app'=>'myapp','_act'=>'myact'], [], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testIllegalGetField() : void
    {
        $this->expectException(Exceptions\IllegalGetFieldException::class);
        (new HTTP())->LoadHTTPInputs([], ['_app'=>'myapp','_act'=>'myact','password'=>'test'], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    public function testBadBase64Field() : void
    {
        $this->expectException(Exceptions\Base64DecodeException::class);
        (new HTTP())->LoadHTTPInputs([], ['_app'=>'myapp','_act'=>'myact'], [], ['REQUEST_METHOD'=>'GET',"HTTP_X_ANDROMEDA_XYZ"=>"bad!"]);
    }
    
    public function testBasicInput() : void
    {
        $get = array('_app'=>$app='myapp', '_act'=>$act='myact');
        $req = array('password'=>'test', 'param2'=>['0'=>'0','1'=>'1'], 'obj'=>['0'=>'2','3'=>'4']);
        
        $server = array('REQUEST_TIME'=>0,'HTTP_USER_AGENT'=>"abc","HTTP_X_ANDROMEDA_XYZ"=>base64_encode("123"));
        $inputs = (new HTTP())->LoadHTTPInputs($req+$get, $get, [], $server+['REQUEST_METHOD'=>'POST']);
        $this->assertCount(1, $inputs); $input = $inputs[0];
        
        $this->assertSame($app, $input->GetApp());
        $this->assertSame($act, $input->GetAction());
        
        $params = $input->GetParams();
        $obj = $params->GetParam('obj')->GetObject();
        
        $this->assertSame([0,1], $params->GetParam('param2')->GetArray(
            function(SafeParam $p){ return $p->GetInt(); }));
        
        $this->assertSame(2, $obj->GetParam('0')->GetInt());
        $this->assertSame(4, $obj->GetParam('3')->GetInt());
        
        $this->assertSame(123, $params->GetParam('xyz')->GetInt());
        $this->assertSame($req+["xyz"=>"123"], $params->GetClientObject());
    }
    
    public function testEmptyBatch() : void
    {
        $this->expectException(EmptyBatchException::class);
        (new HTTP())->LoadHTTPInputs(['_bat'=>[]], ['_bat'=>[]], [], ['REQUEST_METHOD'=>'GET']);
    }
    
    /*public function testBatchIsArray1() : void
    {
        $this->expectException(Exceptions\BatchSyntaxInvalidException::class);
        (new HTTP())->LoadHTTPInputs(['_bat'=>[]], ['_bat'=>[]], ['_bat'=>[]], ['REQUEST_METHOD'=>'GET']);
    }*/ // TODO BATCH remove me
    
    /*public function testBatchIsArray2() : void
    {
        $this->expectException(Exceptions\BatchSyntaxInvalidException::class);
        (new HTTP())->LoadHTTPInputs(['_bat'=>[0=>array()]], ['_bat'=>[0=>array()]], ['_bat'=>[0=>5]], ['REQUEST_METHOD'=>'GET']);
    }*/ // TODO BATCH remove me
    
    public function testGetBatchAndAuth() : void
    {
        $get = array('_app'=>'test','_bat'=>array(array('_act'=>'act1','bat1'=>'test1'), array('_act'=>'act2','bat2'=>'test2'), 'third'=>array('_act'=>'act3')));
        $req = $get; $req['greq'] = 8; $req['_bat'][0]['bat1r'] = 'testA'; $req['_bat'][1]['bat2r'] = 'testB'; // batch3 empty
        
        $server = array('REQUEST_METHOD'=>'GET','PHP_AUTH_USER'=>$user='myuser','PHP_AUTH_PW'=>$pass='mypass');
        $inputs = (new HTTP())->LoadHTTPInputs($req, $get, array(), $server); // can't test files...
        $this->assertCount(3, $inputs); $input0 = $inputs[0]; $input1 = $inputs[1]; $input2 = $inputs['third'];
        
        $this->assertSame('test',$input0->GetApp());
        $this->assertSame('test',$input1->GetApp());
        $this->assertSame('test',$input2->GetApp());
        
        $this->assertSame('act1',$input0->GetAction());
        $this->assertSame('act2',$input1->GetAction());
        $this->assertSame('act3',$input2->GetAction());
        
        $this->assertSame(array('bat1'=>'test1','bat1r'=>'testA','greq'=>8), $input0->GetParams()->GetClientObject());
        $this->assertSame(array('bat2'=>'test2','bat2r'=>'testB','greq'=>8), $input1->GetParams()->GetClientObject());
        $this->assertSame(array('greq'=>8), $input2->GetParams()->GetClientObject());
        
        $auth = $input0->GetAuth(); assert($auth instanceof InputAuth);
        $this->assertSame($user,$auth->GetUsername());
        $this->assertSame($pass,$auth->GetPassword());
        $this->assertEquals($auth,$input1->GetAuth());
        $this->assertEquals($auth,$input2->GetAuth());
    }
    
    public function testFiles() : void
    {
        // can't really test file input due to is_uploaded_file() check
        $this->expectException(Exceptions\FileUploadFailException::class);
        
        $files = array('myfile'=>array('tmp_name'=>'test.txt','name'=>'test.txt','error'=>0));
        (new HTTP())->LoadHTTPInputs([], ['_app'=>'myapp','_act'=>'myact'], $files, ['REQUEST_METHOD'=>'GET']);
    }
    
    /** @param ?array<mixed> $arrval */
    protected function testOutput(HTTP $iface, int $mode, ?string $strval, ?array $arrval, string $want) : void
    {
        $iface->SetOutputMode($mode);
        $output = $this->createStub(Output::class);
        if ($strval !== null) $output->method('GetAsString')->willReturn($strval);
        if ($arrval !== null) $output->method('GetAsArray')->willReturn($arrval);
        
        $output = Utilities::CaptureOutput(function()use($iface,$output){
            $iface->FinalOutput($output); });
        $this->assertSame($want,$output);
    }
    
    public function testFinalOutput() : void
    {
        $iface = new HTTP();
        
        $this->testOutput($iface, 0, $str='mystring', null, '');
        $this->testOutput($iface, HTTP::OUTPUT_PLAIN, $str, null, $str);
        $this->testOutput($iface, HTTP::OUTPUT_PLAIN, null, $arr=[1,2,3,4], '[1,2,3,4]'); // json fallback
        $this->testOutput($iface, HTTP::OUTPUT_PRINTR, null, $arr=[1,2,3,4], print_r($arr,true));
        $this->testOutput($iface, HTTP::OUTPUT_JSON, $str='[1,2,3]', $arr=[1,2,3], $str);
        
        // now test the binary JSON multi output version
        $iface->RegisterOutputHandler(new OutputHandler(function(){ return 0; },function(Output $output){ }));
        $this->testOutput($iface, HTTP::OUTPUT_JSON, null, $arr, HTTP::formatSize(strlen($str)).$str);
    }

    public function testRemoteURL() : void
    {
        $params = (new SafeParams())->LoadArray(['test'=>5,'arr'=>['test2'=>7]]);
        $input = new Input('myapp','myact', $params);
        
        $base = "http://test.com/index.php";
        $appact = "_app=myapp&_act=myact";
        $paramstr = "test=5&arr%5Btest2%5D=7";
        
        $this->assertSame("?$appact", 
            HTTP::GetRemoteURL("", $input, false));
        
        $this->assertSame("?$appact&$paramstr",
            HTTP::GetRemoteURL("", $input, true));
        
        $this->assertSame("$base?$appact",
            HTTP::GetRemoteURL($base, $input, false));
        
        $this->assertSame("$base?yo=6&$appact&$paramstr",
            HTTP::GetRemoteURL("$base?yo=6", $input, true));
    }
}
