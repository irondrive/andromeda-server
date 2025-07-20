<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, TableTypes};

class AuthObjectTest_AuthObject extends BaseObject
{
    use AuthObjectFull, TableTypes\TableNoChildren;
    
    protected static function GetFullKeyPrefix() : string { return "test"; }
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        
        parent::CreateFields();
    }        
    
    public function pubGetAuthHash() : ?string {
        return $this->authkey->TryGetValue(); }

    public function pubTryGetAuthKey() : ?string {
        return $this->TryGetAuthKey(); }

    public function pubInitAuthKey() : string {
        return $this->InitAuthKey(); }
    
    /** @return $this */
    public function pubSetAuthKey(?string $key) : self {
        return $this->SetAuthKey($key); }
}

class AuthObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testEmpty() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);

        $this->assertNull($obj->pubTryGetAuthKey());
        $this->assertNull($obj->pubGetAuthHash());
        
        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeyMatch("test"));
    }
    
    public function testBasic() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);
        $obj->pubInitAuthKey();

        $key = $obj->pubTryGetAuthKey();
        $this->assertIsString($key);
        
        $this->assertNotEquals($key, $obj->pubGetAuthHash());
        
        $this->assertTrue($obj->CheckKeyMatch($key));

        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeyMatch('0'));
        $this->assertFalse($obj->CheckKeyMatch("test"));
        $this->assertFalse($obj->CheckKeyMatch($key.'0'));
        $this->assertFalse($obj->CheckKeyMatch('0'.$key));
        $this->assertFalse($obj->CheckKeyMatch(strtoupper($key)) && $obj->CheckKeyMatch(strtolower($key)));
    }
    
    public function testSetKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, [], false);
        
        $key = "mytest123"; $obj->pubSetAuthKey($key);
        $this->assertSame(base64_encode($key), $obj->pubTryGetAuthKey());
        $this->assertTrue($obj->CheckKeyMatch(base64_encode($key)));
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->pubTryGetAuthKey());
    }
    
    public function testFromHash() : void
    {
        $keyb64 = "Rcuq2uOKWQX8PDZKzvkG4JhqTqXNCnNQsGFqQtN1clM=";
        $hash = base64_decode("pib/+unTgVADDTl1T7JdMOxZP9/1IhRVKrS5dqX1yPA=");
        
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, array('id'=>'test123','authkey'=>$hash), false);

        //$obj->pubInitAuthKey();
        //echo " ".$obj->pubTryGetAuthKey()."\n";
        //echo " ".base64_encode($obj->pubGetAuthHash())."\n";
        
        $exc = false; try { $obj->pubTryGetAuthKey(); } // key not available yet
        catch (Exceptions\RawKeyNotAvailableException $e) { $exc = true; }
        $this->assertTrue($exc);
        
        $this->assertSame($hash, $obj->pubGetAuthHash());
        $this->assertTrue($obj->CheckKeyMatch($keyb64));
        $this->assertSame($keyb64, $obj->pubTryGetAuthKey()); // have key now
    }
    
    public function testTryGetFullKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, ['id'=>$id='test123'], false);
        $obj->pubInitAuthKey();
        
        $key = $obj->pubTryGetAuthKey();
        $this->assertSame("test:$id:$key", $obj->TryGetFullKey());
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->TryGetFullKey());

        $this->expectException(Exceptions\RawKeyNotAvailableException::class);
        $obj->GetFullKey();
    }
    
    public function testCheckFullKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new AuthObjectTest_AuthObject($objdb, ['id'=>$id='test123'], false);
        $obj->pubInitAuthKey();
        
        $key = $obj->pubTryGetAuthKey();
        $full = "test:$id:$key";
        
        $this->assertTrue($obj->CheckFullKey($full));
        $obj->pubSetAuthKey(null);
        $this->assertFalse($obj->CheckFullKey($full));
    }
    
    public function testGetIDFromFullKey() : void
    {
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey(""));
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey("test:test2")); // too short
        $this->assertNull(AuthObjectTest_AuthObject::TryGetIDFromFullKey("aa:id123:fullkey456")); // wrong tag
        $this->assertSame($id="myid123", AuthObjectTest_AuthObject::TryGetIDFromFullKey("test:$id:fullkey456"));
    }
}
