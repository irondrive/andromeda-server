<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, TableTypes};

require_once(ROOT."/Apps/Accounts/Crypto/AuthObject.php");
require_once(ROOT."/Apps/Accounts/Crypto/Exceptions.php");

class MyAuthObject extends BaseObject
{
    use AuthObject, TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        
        parent::CreateFields();
    }        
    
    /** @return static */
    public static function Create(ObjectDatabase $database, bool $init) : self
    {
        $obj = static::BaseCreate($database);
        
        if ($init) $obj->InitAuthKey();
        
        return $obj;
    }
    
    public function pubTryGetAuthKey() : ?string
    {
        return $this->TryGetAuthKey();
    }
    
    public function pubGetAuthHash() : ?string
    {
        return $this->authkey->TryGetValue();
    }
    
    /** @return $this */
    public function pubSetAuthKey(?string $key) : self
    {
        return $this->SetAuthKey($key);
    }
}

class AuthObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testEmpty() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = MyAuthObject::Create($objdb, false);

        $this->assertNull($obj->pubTryGetAuthKey());
        $this->assertNull($obj->pubGetAuthHash());
        
        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeyMatch("test"));
    }
    
    public function testBasic() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = MyAuthObject::Create($objdb, true);

        $key = $obj->pubTryGetAuthKey();
        $this->assertIsString($key);
        
        $this->assertNotEquals($key, $obj->pubGetAuthHash());
        
        $this->assertTrue($obj->CheckKeyMatch($key));
        
        $this->assertFalse($obj->CheckKeyMatch(""));
        $this->assertFalse($obj->CheckKeymatch('0'));
        $this->assertFalse($obj->CheckKeyMatch("test"));
        $this->assertFalse($obj->CheckKeymatch($key.'0'));
        $this->assertFalse($obj->CheckKeymatch('0'.$key));
        $this->assertFalse($obj->CheckKeyMatch(strtoupper($key)));
    }
    
    public function testSetKey() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = MyAuthObject::Create($objdb, false);
        
        $key = "mytest123"; $obj->pubSetAuthKey($key);
        $this->assertSame($key, $obj->pubTryGetAuthKey());
        $this->assertTrue($obj->CheckKeymatch($key));
        
        $obj->pubSetAuthKey(null);
        $this->assertNull($obj->pubTryGetAuthKey());
    }
    
    public function testFromHash() : void
    {
        $key = "1qo95feuuz5ixu9d4o530sxvbto8b99j";
        $hash = '$argon2id$v=19$m=1024,t=1,p=1$SEREcGtDQ2hQaHRDcmZYcQ$rbYiVNjqfVeKKTrseQ0z+eiYGIGhHCzPCoe+5bfOknc';
        
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new MyAuthObject($objdb, array('id'=>'test123','authkey'=>$hash));
        
        $exc = false; try { $obj->pubTryGetAuthKey(); } // key not available yet
        catch (Exceptions\RawKeyNotAvailableException $e) { $exc = true; }
        $this->assertTrue($exc);
        
        $this->assertSame($hash, $obj->pubGetAuthHash());
        $this->assertTrue($obj->CheckKeyMatch($key));
        $this->assertSame($key, $obj->pubTryGetAuthKey()); // have key now
    }
    
    public function testRehash() : void
    {
        $key = "testKey123456789";
        $hash = '$2y$10$JvPO9nS5Papx9Z4KrwLhAOc2DIkJm5kRm1hv8z/dGcMqH23MHEaFi';
        
        $objdb = $this->createMock(ObjectDatabase::class);
        $obj = new MyAuthObject($objdb, array('id'=>'test123','authkey'=>$hash));
        
        $this->assertSame($hash, $obj->pubGetAuthHash());
        $this->assertTrue($obj->CheckKeyMatch($key));
        
        $this->assertNotEquals($hash, $obj->pubGetAuthHash()); // re-hash
        $this->assertTrue($obj->CheckKeyMatch($key));
    }
}