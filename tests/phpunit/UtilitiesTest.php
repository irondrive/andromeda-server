<?php namespace Andromeda\Core; 

if (!defined('a2test')) define('a2test',true); require_once("a2init.php");

require_once(ROOT."/core/Crypto.php");
require_once(ROOT."/core/Utilities.php");

class MySingleton extends Singleton { }

class UtilitiesTest extends \PHPUnit\Framework\TestCase
{
   public function testInit() : void 
   {
       $this->assertTrue(Andromeda);
   }
   
   public function testSingletonEmpty() : void
   {
       $this->expectException(MissingSingletonException::class);
       
       $this->assertInstanceOf(MySingleton::class, MySingleton::GetInstance());
   }

   /**
    * @depends testSingletonEmpty
    */
   public function testSingletonConstruct() : MySingleton
   {
       $singleton = new MySingleton();
       
       $this->assertInstanceOf(MySingleton::class, $singleton);
       
       return $singleton;
   }
   
   /**
    * @depends testSingletonConstruct
    */
   public function testSingletonFetch(MySingleton $singleton) : void
   {
       $this->assertSame($singleton, MySingleton::GetInstance());
   }
   
   /**
    * @depends testSingletonConstruct
    */
   public function testSingletonDuplicate() : void
   {
       $this->expectException(DuplicateSingletonException::class);
       
       new MySingleton();
   }
   
   public function testVersionInfo() : void
   {
       $version = new VersionInfo("3.2.1-alpha");
       
       $this->assertSame($version->major, 3);
       $this->assertSame($version->minor, 2);
       $this->assertSame($version->patch, 1);
       $this->assertSame($version->extra, 'alpha');
       $this->assertSame((string)$version, "3.2.1-alpha");
   }
   
   public function testBadJSONEncode() : void
   {
       $this->expectException(JSONEncodingException::class);
       
       Utilities::JSONEncode(CryptoSecret::GenerateKey());
   }
   
   public function testBadJSONDecode() : void
   {
       $this->expectException(JSONDecodingException::class);
       
       Utilities::JSONDecode("nothing here!");
   }
   
   public function testArrayLast() : void
   {
       $this->assertSame(Utilities::array_last(null), null);
       $this->assertSame(Utilities::array_last(array()), null);
       $this->assertSame(Utilities::array_last(array(5)), 5);
       $this->assertSame(Utilities::array_last(array(1,2,3)), 3);
   }
   
   public function testShortClassName() : void
   {
       $this->assertSame(Utilities::ShortClassName(Utilities::class),"Utilities");
   }
   
   public function testReturnBytes() : void
   {
       $this->assertSame(Utilities::return_bytes(""), 0);       
       $this->assertSame(Utilities::return_bytes("0"), 0);
       
       $this->assertSame(Utilities::return_bytes(0), 0);
       $this->assertSame(Utilities::return_bytes(75), 75);
       
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
   
   public function testCaptureOutput() : void
   {
       $this->assertSame(Utilities::CaptureOutput(function(){ }), "");
       $this->assertSame(Utilities::CaptureOutput(function(){ echo "test"; }), "test");       
   }
}
