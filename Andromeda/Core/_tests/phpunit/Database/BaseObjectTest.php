<?php declare(strict_types=1); namespace Andromeda\Core\Database; require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

class BaseObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testGetUniqueKeys() : void
    {
        $this->assertSame(array(
            EasyObject::class => array('id','uniqueKey')
        ), EasyObject::GetUniqueKeys());
        
        $this->assertSame(array(
            PolyObject5a::class => array('testprop5'),
            PolyObject1::class => array('id')
        ), PolyObject5aa::GetUniqueKeys());
    }
    
    public function testGetRowClass() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $this->assertSame(PolyObject4::class, PolyObject4::GetRowClass($database,array('type'=>5)));
        $this->assertSame(PolyObject5a::class, PolyObject4::GetRowClass($database,array('type'=>13)));
        
        $this->expectException(Exceptions\BadPolyTypeException::class);
        PolyObject4::GetRowClass($database,array('type'=>99));
    }
    
    public function testGetWhereChild() : void
    {
        $db = new ObjectDatabase($this->createMock(PDODatabase::class));
        
        $q = new QueryBuilder();
        $this->assertSame("a2obj_core_database_polyobject4.type = :d0", 
            PolyObject4::GetWhereChild($db, $q, PolyObject4::class));
        $this->assertSame(array('d0'=>5), $q->GetParams());
        
        $q = new QueryBuilder();
        $this->assertSame("a2obj_core_database_polyobject4.type = :d0",
            PolyObject4::GetWhereChild($db, $q, PolyObject5a::class));
        $this->assertSame(array('d0'=>13), $q->GetParams());
        
        $q = new QueryBuilder();
        $this->expectException(Exceptions\BadPolyClassException::class);
        PolyObject4::GetWhereChild($db, $q, PolyObject3::class);
    }
    
    public function testGetTableClasses() : void
    {
        $this->assertSame(array(EasyObject::class), 
            EasyObject::GetTableClasses());
        
        $this->assertSame(array(
            PolyObject1::class, PolyObject2::class,
            PolyObject4::class, PolyObject5a::class
        ), PolyObject5a::GetTableClasses());
        
        $this->assertSame(EasyObject::class, EasyObject::GetBaseTableClass());
        $this->assertSame(PolyObject1::class, PolyObject5a::GetBaseTableClass());
        
        $this->expectException(Exceptions\NoBaseTableException::class);
        PolyObject0::GetBaseTableClass();
    }
    
    public function testBasic() : void
    {
        // test Create, construct, ID()
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->once())->method('CreateObject')
            ->willReturnCallback(function()use($database){ return new EasyObject($database,array(),true) ;});
        
        $obj = EasyObject::Create($database);
        
        $this->assertInstanceOf(EasyObject::class, $obj);
        $this->assertSame(12, strlen($obj->ID()));
        
        $this->assertTrue($obj->didPostConstruct());
    }
    
    public function testToString() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $obj = new EasyObject($database,array(),true); $id = $obj->ID();
        
        $str = "$id:Andromeda\\Core\\Database\\EasyObject";
        $this->assertSame($str, BaseObject::toString($obj));
        $this->assertSame($str, "$obj");
    }
    
    /*public function testSave() : void // TODO DB !! make this an objdb test ... there should still be a Save() test here though
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
        $obj->NotifyPostDeleted();
        
        $this->expectException(Exceptions\SaveAfterDeleteException::class);
        $obj->Save(); // throws exception
    }*/
    
    /*public function testNotifyDelete() : void // TODO DB !! make this an objdb test
    {
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->exactly(0))->method('DeleteObject');
        
        $obj = EasyObject::Create($database);
        $obj->NotifyPostDeleted();
        $this->assertTrue($obj->isDeleted());
        
        $obj = new EasyObject($database, array());
        $this->assertSame(5, $obj->SetGeneralKey(5)->GetGeneralKey());
        
        $obj->NotifyPostDeleted();
        $this->assertTrue($obj->isDeleted());
    }*/
    
    /*public function testDeleteCreated() : void // TODO DB !! make this an objdb test
    {
        $database = $this->createMock(ObjectDatabase::class);
        $database->expects($this->exactly(0))->method('DeleteObject');
        
        $obj = EasyObject::Create($database);
        $obj->Delete(); // created, no delete call
        $this->assertTrue($obj->isDeleted());
    }*/
    
    /*public function testDeleteLoaded() : void // TODO DB !! these should probably be objdb tests now
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        $obj = EasyObject::Create($database)->Save();
        
        $database->expects($this->exactly(1))->method('DeleteObject')
            ->willReturnCallback(function()use($obj,$database){ 
                $obj->NotifyPostDeleted(); return $database; });
        
        $obj->Delete();
        $obj->Delete(); // no 2nd call
        $this->assertTrue($obj->isDeleted());
    }*/
}
