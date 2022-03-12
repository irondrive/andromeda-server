<?php namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/Database/TableTypes.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");

final class TestSingleton1 extends SingletonObject 
{
    use TableNoChildren; 
}

final class TestSingleton2 extends SingletonObject 
{
    use TableNoChildren;
    
    public static function Create(ObjectDatabase $database) : self 
    { 
        return parent::BaseCreate($database); 
    }
}

class SingletonObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testMissing() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        $this->expectException(SingletonNotFoundException::class);
        
        TestSingleton1::GetInstance($database);
    }
    
    public function testCreate() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        $database->expects($this->exactly(0))->method('TryLoadUniqueByKey');
        
        $obj = TestSingleton2::Create($database);
        
        $this->assertInstanceOf(TestSingleton2::class, $obj);
        
        $this->assertSame($obj, TestSingleton2::GetInstance($database));
        $this->assertSame($obj, TestSingleton2::GetInstance($database));
    }
    
    /** @depends testCreate */
    public function testLoad() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        
        // only one read because TestSingleton2 was created above
        $database->expects($this->exactly(1))->method('TryLoadUniqueByKey')
            ->willReturn(new TestSingleton1($database, array('id'=>$id='A')));
        
        $obj1 = TestSingleton1::GetInstance($database);
        $obj2 = TestSingleton2::GetInstance($database);
        
        $this->assertSame($obj1, TestSingleton1::GetInstance($database));
        $this->assertSame($obj2, TestSingleton2::GetInstance($database));
        
        $this->assertInstanceOf(TestSingleton1::class, $obj1);
        $this->assertInstanceOf(TestSingleton2::class, $obj2);
        
        $this->assertSame($id, $obj1->ID());
        $this->assertSame($id, $obj2->ID());
    }
}
