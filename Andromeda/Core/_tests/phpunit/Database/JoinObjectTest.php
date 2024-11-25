<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

class TestObject1 extends BaseObject
{ 
    use TableTypes\TableNoChildren;
}

class TestObject2 extends BaseObject
{ 
    use TableTypes\TableNoChildren;
}

class TestJoinObject extends JoinObject
{
    /** @var FieldTypes\ObjectRefT<TestObject1> */
    private FieldTypes\ObjectRefT $obj1;

    /** @var FieldTypes\ObjectRefT<TestObject2> */
    private FieldTypes\ObjectRefT $obj2;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->obj1 = $fields[] = new FieldTypes\ObjectRefT(TestObject1::class, 'test1');
        $this->obj2 = $fields[] = new FieldTypes\ObjectRefT(TestObject2::class, 'test2');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    public function GetTestObject1() : TestObject1 { return $this->obj1->GetObject(); }
    public function GetTestObject2() : TestObject2 { return $this->obj2->GetObject(); }

    /** @return array<string, TestObject1> */
    public static function LoadTestObject1(ObjectDatabase $database, TestObject2 $obj2)
    {
        return static::LoadFromJoin($database, TestObject1::class, 'test1', array('test2'=>$obj2));
    }

    /** @return array<string, TestObject2> */
    public static function LoadTestObject2(ObjectDatabase $database, TestObject1 $obj1)
    {
        return static::LoadFromJoin($database, TestObject2::class, 'test2', array('test1'=>$obj1));
    }
}

class JoinObjectTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadFromJoin() : void
    {
        $database = $this->createMock(PDODatabase::class);
        $objdb = new ObjectDatabase($database);

        $qstr = "SELECT a2obj_core_database_testobject1.* FROM a2obj_core_database_testobject1 "
            ."JOIN a2obj_core_database_testjoinobject ON a2obj_core_database_testjoinobject.test1 = a2obj_core_database_testobject1.id WHERE test2 = :d0";

        $database->expects($this->once())->method('read')
            ->with($qstr)->willReturn(array(['id'=>$id="test123"]));

        $obj2 = $this->createMock(TestObject2::class);
        $objs = TestJoinObject::LoadTestObject1($objdb, $obj2);
        $this->assertCount(1, $objs); $obj1 = $objs[$id];
        $this->assertInstanceOf(TestObject1:: class, $obj1);
    }

    // TODO RAY !! all other unit tests
}
