<?php namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

require_once(ROOT."/Core/Database/BaseObject.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");

class BaseObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic() : void
    {
        // test BaseCreate, construct, ID()
        $database = $this->createMock(ObjectDatabase::class);
        $obj = EasyObject::Create($database);
        
        $this->assertInstanceOf(EasyObject::class, $obj);
        $this->assertSame(12, strlen($obj->ID()));
    }
    
    public function testToString() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $obj = EasyObject::Create($database); $id = $obj->ID();
        
        $str = "$id:EasyObject";
        $this->assertSame($str, BaseObject::toString($obj));
        $this->assertSame($str, "$obj");
    }
    
    public function testSave() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $obj = EasyObject::Create($database);
        
        $database->expects($this->once())->method('InsertObject');
        $database->expects($this->once())->method('UpdateObject');
        $database->expects($this->once())->method('DeleteObject');
        
        $obj->Save(true); // nothing
        $obj->Save(); // insert
        $obj->Save(); // update
        
        $obj->Delete(); 
        $obj->notifyDeleted();
        $obj->Save(); // nothing
    }
    
    public function testNotifyDelete() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->exactly(0))->method('DeleteObject');
        
        $obj = EasyObject::Create($database);
        $obj->notifyDeleted();
        $this->assertTrue($obj->isDeleted());
        
        $obj = new EasyObject($database, array());
        
        $obj->SetGeneralKey(5);
        $this->assertSame(5, $obj->GetGeneralKey());
        
        $obj->notifyDeleted();
        $this->assertTrue($obj->isDeleted());
        
        $this->assertSame(null, $obj->GetGeneralKey()); //default
    }
    
    public function testDeleteCreated() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->exactly(0))->method('DeleteObject');
        
        $obj = EasyObject::Create($database);
        $obj->Delete(); // created, no delete call
        $this->assertTrue($obj->isDeleted());
    }
    
    public function testDeleteLoaded() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        $obj = EasyObject::Create($database)->Save();
        
        $database->expects($this->exactly(1))->method('DeleteObject')
            ->willReturnCallback(function()use($obj,$database){ 
                $obj->notifyDeleted(); return $database; });
        
        $obj->Delete();
        $obj->Delete(); // no 2nd call
        $this->assertTrue($obj->isDeleted());
    }
}
