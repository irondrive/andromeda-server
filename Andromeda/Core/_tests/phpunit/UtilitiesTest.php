<?php declare(strict_types=1); namespace Andromeda\Core; require_once("init.php");

class TestClass1 { public function __toString() : string { return "TestClass1..."; } }
class TestClass2 { }
class TestClass3 { }

class UtilitiesTest extends \PHPUnit\Framework\TestCase
{
   public function testInit() : void 
   {
       $this->assertTrue(Andromeda);
       $this->assertIsString(andromeda_version);
   }
   
   public function testJSON() : void
   {
       $this->assertSame('{"test":55}',Utilities::JSONEncode(array('test'=>55)));
       $this->assertSame(array('test'=>55),Utilities::JSONDecode('{"test":55}'));
   }
   
   public function testBadJSONEncode() : void
   {
       $this->expectException(Exceptions\JSONException::class);
       Utilities::JSONEncode(array(Crypto::GenerateSecretKey()));
   }
   
   public function testBadJSONDecode() : void
   {
       $this->expectException(Exceptions\JSONException::class);
       Utilities::JSONDecode("nothing here!");
   }
   
   public function testIsUTF8() : void
   {
       $this->assertTrue(Utilities::isUTF8(""));
       $this->assertTrue(Utilities::isUTF8("test"));
       $this->assertTrue(Utilities::isUTF8("\u{9999}"));
       
       $this->assertFalse(Utilities::isUTF8(strval(hex2bin("deadbeef"))));
   }
   
   public function testArrayLast() : void
   {
       $this->assertSame(Utilities::array_last(array()), null);
       $this->assertSame(Utilities::array_last(array(5)), 5);
       $this->assertSame(Utilities::array_last(array(1,2,3)), 3);
       $this->assertSame(Utilities::array_last(array(4=>'test',7=>'test2',5=>'test3')), 'test3');
       $this->assertSame(Utilities::array_last(array('b'=>5,'a'=>4)), 4);
   }
   
   public function testDeleteValue() : void
   {
       $arr = array(); $this->assertEquals(array(), Utilities::delete_value($arr, 0));
       
       $arr = array(0); $this->assertEquals(array(),Utilities::delete_value($arr, 0)); // not found
       $arr = array(0); $this->assertEquals(array(0), Utilities::delete_value($arr, 1)); // not found
       
       $arr = array(1,2); $this->assertEquals(array(1), array_values(Utilities::delete_value($arr, 2))); // delete last
       $arr = array(1,2); $this->assertEquals(array(2), array_values(Utilities::delete_value($arr, 1))); // delete first
       $arr = array(1,2,3); $this->assertEquals(array(1,3), array_values(Utilities::delete_value($arr, 2))); // delete middle
       
       $arr = array(1,2,3); $this->assertEquals(array(0=>1, 2=>3), Utilities::delete_value($arr, 2)); // preserve keys
       
       $arr = array('a'=>1, 'b'=>2, 'c'=>3, 'd'=>2); 
       $this->assertEquals(array('a'=>1, 'c'=>3), 
           Utilities::delete_value($arr, 2)); // associative
       
       $arr = array(2,1,2,3,2,3,2,2,2,3,4,5,2,7,2); 
       $this->assertEquals(array(1,3,3,3,4,5,7), 
           array_values(Utilities::delete_value($arr, 2))); // delete many
       
       // test doing by reference
       $arr = array(5,6,7,8);
       Utilities::delete_value($arr, 7);
       $this->assertSame(array_values($arr), array(5,6,8));
   }
   
   public function testShortClassName() : void
   {
       $this->assertSame("Utilities",Utilities::ShortClassName(Utilities::class));
   }
   
   public function testFirstUpper() : void
   {
       $this->assertSame("", Utilities::FirstUpper(""));
       $this->assertSame("R", Utilities::FirstUpper("r"));
       $this->assertSame("R", Utilities::FirstUpper("R"));
       $this->assertSame("ArAaBbC", Utilities::FirstUpper("arAaBbC"));
   }
   
   public function testReturnBytes() : void
   {
       $this->assertSame(Utilities::return_bytes(""), 0);       
       $this->assertSame(Utilities::return_bytes("0"), 0);
       
       $this->assertSame(Utilities::return_bytes("0"), 0);
       $this->assertSame(Utilities::return_bytes("75"), 75);
       
       $this->assertSame(Utilities::return_bytes("0B"), 0);
       $this->assertSame(Utilities::return_bytes("0 B"), 0);
       
       $this->assertSame(Utilities::return_bytes("27"), 27);
       
       $this->assertSame(Utilities::return_bytes("1K"), 1024);
       $this->assertSame(Utilities::return_bytes("1 K"), 1024);
       
       $this->assertSame(Utilities::return_bytes("10G"), 10*1024*1024*1024);
       $this->assertSame(Utilities::return_bytes("10 G"), 10*1024*1024*1024);
   }
   
   public function testReplaceFirst() : void
   {
       $this->assertSame(Utilities::replace_first("","",""), "");
       $this->assertSame(Utilities::replace_first("test","test2",""), "");
       $this->assertSame(Utilities::replace_first("test","test2","test"), "test2");
       $this->assertSame(Utilities::replace_first("test","test2","3testtest5"), "3test2test5");      
   }
   
   public function testArrayMapKeys() : void
   {
       $func = function(string $p){ return $p.'5'; };
       
       $this->assertSame(Utilities::array_map_keys($func, array()), array());
       
       $this->assertSame(Utilities::array_map_keys($func, 
           array('a','b','c')), array('a'=>'a5','b'=>'b5','c'=>'c5'));
   }
   
   public function testIsPlainArray() : void
   {
       $this->assertTrue(Utilities::is_plain_array(array()));
       $this->assertTrue(Utilities::is_plain_array(array(0=>false)));
       $this->assertTrue(Utilities::is_plain_array(array(0=>0,1=>'a',2=>3.14)));
       $this->assertTrue(Utilities::is_plain_array([1,2,3,4,5]));
       $this->assertTrue(Utilities::is_plain_array(['a','b','c']));
       
       $this->assertFalse(Utilities::is_plain_array(array(1=>1)));
       $this->assertFalse(Utilities::is_plain_array(array(0=>1,2=>1)));
       $this->assertFalse(Utilities::is_plain_array(array(0=>1,'test'=>false)));
       $this->assertFalse(Utilities::is_plain_array(array(1.0=>3)));
       $this->assertFalse(Utilities::is_plain_array(array('test'=>5)));
       $this->assertFalse(Utilities::is_plain_array(array(0=>5,1=>array(1,2))));
   }
   
   public function testCaptureOutput() : void
   {
       $this->assertSame(Utilities::CaptureOutput(function(){ }), "");
       $this->assertSame(Utilities::CaptureOutput(function(){ echo "test"; }), "test");       
   }

   public function testArrayStrings() : void
   {
       $in = array(
           'a' => 5,
           'b' => 'mytest',
           'c' => array(
               'c0' => 6,
               'c1' => array(
                  'test',
                   new TestClass3(),
                   hex2bin("deadbeef") // not UTF-8
               ),
               'c2' => new TestClass2()
           ),
           'd' => new TestClass1()
       );
       
       $out = array(
           'a' => '5',
           'b' => 'mytest',
           'c' => array(
               'c0' => '6',
               'c1' => array(
                   'test',
                   'Andromeda\Core\TestClass3',
                   '3q2+7w==' // base64
               ),
               'c2' => 'Andromeda\Core\TestClass2'
           ),
           'd' => 'TestClass1...'
       );
       
       $this->assertSame($out, Utilities::arrayStrings($in));
   }
}
