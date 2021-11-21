<?php namespace Andromeda\Core\IOFormat; 

if (!defined('a2test')) define('a2test',true); require_once("a2init.php");

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Core/IOFormat/Output.php");

class TestClientException extends Exceptions\ClientErrorException { public $message = "TEST_EXCEPTION"; }

class OutputTest extends \PHPUnit\Framework\TestCase
{
    public function testSuccess() : void
    {
        $output = Output::Success(array('myretval'));
        
        $this->assertTrue($output->isOK());
        $this->assertSame($output->GetCode(), 200);
        $this->assertSame($output->GetAppdata(), 'myretval');
        
        $this->assertSame(array('ok'=>true,'code'=>200,
            'appdata'=>'myretval'), $output->GetAsArray());
    }
    
    public function testMultiSuccess() : void
    {
        $output = Output::Success(array('myretval','myretval2'));
        
        $this->assertTrue($output->isOK());
        $this->assertSame($output->GetCode(), 200);
        
        $this->assertSame($output->GetAppdata(), array('myretval','myretval2'));
        
        $this->assertSame(array('ok'=>true,'code'=>200,
            'appdata'=>array('myretval','myretval2')), $output->GetAsArray());
    }
    
    public function testClientException() : void
    {
        $output = Output::ClientException(new TestClientException(), array('mydebug'))->SetMetrics(array('mymetrics'));
        
        $this->assertFalse($output->isOK());
        
        $this->assertSame($output->GetCode(), 400);
        $this->assertSame($output->GetMessage(), 'TEST_EXCEPTION');
        
        $this->assertSame(array('ok'=>false,'code'=>400,'message'=>'TEST_EXCEPTION',
            'metrics'=>array('mymetrics'), 'debug'=>array('mydebug')), $output->GetAsArray());
    }
    
    public function testServerException() : void
    {
        $output = Output::Exception(array('mydebug'))->SetMetrics(array('mymetrics'));
        
        $this->assertFalse($output->isOK());
        
        $this->assertSame($output->GetCode(), 500);
        $this->assertSame($output->GetMessage(), 'SERVER_ERROR');
        
        $this->assertSame(array('ok'=>false,'code'=>500,'message'=>'SERVER_ERROR',
            'metrics'=>array('mymetrics'), 'debug'=>array('mydebug')), $output->GetAsArray());
    }
    
    public function testGetAsString() : void
    {
        $this->assertSame(Output::Success(array(null))->GetAsString(), 'SUCCESS');
        $this->assertSame(Output::Success(array(false))->GetAsString(), 'FALSE');
        $this->assertSame(Output::Success(array(true))->GetAsString(), 'TRUE');
        $this->assertSame(Output::Success(array('myretval'))->GetAsString(), 'myretval');
        
        $v = array('mykey'=>'myval'); $this->assertSame(Output::Success(array($v))->GetAsString(), print_r($v,true));
        $this->assertSame(Output::Success(array('myretval'))->SetMetrics(array('mymetrics'))->GetAsString(), null);
        
        $this->assertSame(Output::Exception()->GetAsString(), 'SERVER_ERROR');
        $this->assertSame(Output::Exception(array('mydebug'))->GetAsString(), null);
    }
    
    public function testParseArrayGood() : void
    {
        $data = array('ok'=>true,'code'=>200,'appdata'=>'myretval');
        
        $output = Output::ParseArray($data);
        
        $this->assertTrue($output->isOK());
        $this->assertSame($output->GetCode(), 200);
        $this->assertSame($output->GetAppdata(), $data['appdata']);        
        $this->assertSame($data, $output->GetAsArray());
        
        $data = array('ok'=>true,'code'=>200,'appdata'=>array('mykey',array('key'=>'val')));
        
        $output = Output::ParseArray($data);
        
        $this->assertTrue($output->isOK());
        $this->assertSame($output->GetCode(), 200);
        $this->assertSame($output->GetAppdata(), $data['appdata']);
        $this->assertSame($data, $output->GetAsArray());
        
        $data = array('ok'=>false,'code'=>400,'message'=>'EXCEPTION!');
        
        $this->expectException(Exceptions\CustomClientException::class);
        $this->expectExceptionCode($data['code']); 
        $this->expectExceptionMessage($data['message']);
        
        $output = Output::ParseArray($data);
    }
    
    public function testParseArrayBad1() : void
    {
        $this->expectException(InvalidParseException::class);
        
        Output::ParseArray(array());
    }
    
    public function testParseArrayBad2() : void
    {
        $this->expectException(InvalidParseException::class);
        
        Output::ParseArray(array('ok'=>true,'code'=>200));
    }
    
    public function testParseArrayBad3() : void
    {
        $this->expectException(InvalidParseException::class);

        Output::ParseArray(array('ok'=>false,'code'=>200,'appdata'=>true));
    }
    
    public function testParseArrayBad4() : void
    {
        $this->expectException(InvalidParseException::class);
        
        Output::ParseArray(array('ok'=>true,'code'=>400,'message'=>'exception'));
    }
}
