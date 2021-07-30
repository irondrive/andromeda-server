<?php namespace Andromeda\Core\IOFormat; 

if (!defined('a2test')) define('a2test',true); require_once("a2init.php");

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;

require_once(ROOT."/core/ioformat/SafeParam.php");

class SafeParamTest extends \PHPUnit\Framework\TestCase
{
    public function testNoKey() : void
    {
        $this->expectException(SafeParamInvalidException::class);
        
        new SafeParam("", null);
    }
    
    public function testBadKey() : void
    {
        $this->expectException(SafeParamInvalidException::class);
        
        new SafeParam("test\0", null);
    }
    
    public function testGetKey() : void
    {
        $this->assertSame((new SafeParam("mykey",null))->GetKey(), "mykey");       
    }
    
    public function testRawValue() : void
    {
        $val = CryptoSecret::GenerateKey();
        
        $this->assertSame((new SafeParam("key", $val))->GetRawValue(), $val);
    }
    
    public function testMaxLength() : void
    {
        $val = "123456789"; $param = new SafeParam("key", $val);
        
        $this->assertSame($param->GetValue(SafeParam::TYPE_RAW, SafeParam::MaxLength(99)), $val);
        
        $this->expectException(SafeParamInvalidException::class);
        
        $param->GetValue(SafeParam::TYPE_RAW, SafeParam::MaxLength(5));
    }
    
    protected function testGood($value, int $type) : void
    {
        $this->testGoodMatch($value, $type, $value);
    }
    
    protected function testGoodMatch($value, int $type, $want) : void
    {        
        $this->assertSame((new SafeParam("key",$value))->GetValue($type), $want);
    }
    
    protected function testBad($value, int $type) : void
    {
        $rval = false; try { (new SafeParam("key", $value))->GetValue($type); }
            catch (SafeParamInvalidException $e){ $rval = true; }
        
        $this->assertTrue($rval);
    }
    
    public function testNull() : void
    {
        $this->testGoodMatch("", SafeParam::TYPE_BOOL, null);
        $this->testGoodMatch("\0", SafeParam::TYPE_INT, null);
        
        $this->testGoodMatch("null", SafeParam::TYPE_ALPHANUM, null);
    }
    
    public function testSpacing() : void
    {
        $this->testGoodMatch("  true", SafeParam::TYPE_BOOL, true);
        $this->testGoodMatch("true  ", SafeParam::TYPE_BOOL, true);
        $this->testGoodMatch(" true ", SafeParam::TYPE_BOOL, true);
    }
    
    public function testUnknown() : void
    {
        $this->expectException(SafeParamUnknownTypeException::class);
        
        (new SafeParam("key", "value"))->GetValue(9999);
    }
    
    public function testBool() : void
    {
        $t = SafeParam::TYPE_BOOL;
        
        foreach (array("true", "1", "yes") as $val) 
            $this->testGoodMatch($val, $t, true);
        
        foreach (array("false", "0", "no") as $val)
            $this->testGoodMatch($val, $t, false);
        
        $this->testBad("75", $t);
        $this->testBad("badvalue", $t);
        $this->testBad("anything else?", $t);
    }

    protected function testNumeric(int $t) : void
    {
        $this->testBad("text", $t);
        $this->testBad("47t", $t);
        $this->testBad("t47", $t);
    }
    
    public function testInt() : void
    {
        $t = SafeParam::TYPE_INT;
        
        $this->testNumeric($t);
    
        $this->testGoodMatch("0", $t, 0);
        $this->testGoodMatch("123", $t, 123);
        $this->testGoodMatch("-123", $t, -123);
    }
    
    public function testUint() : void
    {
        $t = SafeParam::TYPE_UINT;
        
        $this->testNumeric($t);
        
        $this->testGoodMatch("0", $t, 0);
        $this->testGoodMatch("123", $t, 123);
        
        $this->testBad("-123", $t);        
    }
    
    public function testFloat() : void
    {
        $t = SafeParam::TYPE_FLOAT;
        
        $this->testNumeric($t);
        
        $this->testGoodMatch("0", $t, 0.0);
        $this->testGoodMatch("123", $t, 123.0);
        $this->testGoodMatch("-123", $t, -123.0);
        
        $this->testGoodMatch("123.45", $t, 123.45);
        $this->testGoodMatch("-123.45", $t, -123.45);
    }
    
    public function testRandstr() : void
    {
        $t = SafeParam::TYPE_RANDSTR;
        
        $this->testGood("9a8s7dn8_s7n", $t);
        
        $this->TestBad("23 45", $t);
        $this->testBad("234a<", $t);
        $this->testBad("234a'", $t);
        $this->testBad("234a\"", $t);
    }
    
    public function testAlphanum() : void
    {
        $t = SafeParam::TYPE_ALPHANUM;
        
        $this->testGood("987n927_83..n4-928", $t);
        
        $this->TestBad("23 45", $t);
        $this->testBad("234a<", $t);
        $this->testBad("234a'", $t);
        $this->testBad("234a\"", $t);
    }
    
    public function testName() : void
    {
        $t = SafeParam::TYPE_NAME;
        
        $this->testGood("(my'te_st..) test", $t);
        
        $this->testBad("234a<", $t);
        $this->testBad("234a\"", $t);
        $this->testBad("234a;", $t);
    }
    
    public function testEmail() : void
    {
        $t = SafeParam::TYPE_EMAIL;
        
        $this->testGood("mytest123_123@test.mytest.edu", $t);
        
        $this->testBad("0", $t);
        $this->testBad("yoooo", $t);
        $this->testBad("nothing@", $t);
        $this->testBad("mytest@serv", $t);
        $this->testBad("test<12@test.com", $t);
    }
    
    public function testFSName() : void
    {
        $t = SafeParam::TYPE_FSNAME;
        
        $this->testGood("my %file.txt", $t);
        
        $this->testBad("./test", $t);
        $this->testBad("../test", $t);
        $this->testBad("test/test", $t);
        $this->testBad("test/../test", $t);
        $this->testBad("test\\test", $t);
        
        $this->testBad("test:", $t);
        $this->testBad("test?", $t);
    }
    
    public function testFSPath() : void
    {
        $t = SafeParam::TYPE_FSPATH;
        
        $this->testGood("my \$file.txt", $t);
        
        $this->testGood("./test", $t);
        $this->testGood("../test", $t);
        $this->testGood("test/test", $t);
        $this->testGood("test/../test", $t);
        $this->testGood("test\\test", $t);
        
        $this->testBad("test:", $t);
        $this->testBad("test?", $t);
    }
    
    public function testText() : void
    {
        $t = SafeParam::TYPE_TEXT;
        
        $this->testGood("hello! this is some text.", $t);
        
        $this->testGoodMatch("stripping <tags>? &", $t, "stripping &#60;tags&#62;? &#38;");
    }
    
    public function testObject() : void
    {
        $t = SafeParam::TYPE_OBJECT;
        $val = '{"key1":75,"key2":"val1"}';
        
        $obj = (new SafeParam('key',$val))->GetValue($t);
        
        $this->assertIsObject($obj);
        
        $this->assertSame($obj->GetParam('key1',SafeParam::TYPE_INT), 75);
        $this->assertSame($obj->GetParam('key2',SafeParam::TYPE_ALPHANUM), 'val1');
        
        $this->testBad("string", $t);
        $this->testBad("{test:10}", $t);
        $this->testBad("{'single':10}", $t);
    }
    
    public function testArray() : void
    {
        $t = SafeParam::TYPE_ARRAY | SafeParam::TYPE_INT;
        
        $arr = (new SafeParam('key',"[5,7,9,27]"))->GetValue($t);
        
        $this->assertIsArray($arr);
        $this->assertCount(4, $arr);
        $this->assertSame($arr[0], 5);
        $this->assertSame($arr[3], 27);
        
        $this->testBad('a string', $t);
        $this->testBad('[57,"mixed"]', $t);
    }
}
