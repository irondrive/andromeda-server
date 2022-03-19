<?php namespace Andromeda\Core\Database\FieldTypes; 

require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

require_once(ROOT."/Core/Database/FieldTypes.php");
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

class TestObject1 extends BaseObject { use TableNoChildren; }
class TestObject2 extends BaseObject { use TableNoChildren; }

class FieldTypesTest extends \PHPUnit\Framework\TestCase
{
    protected function getMockParent() : BaseObject
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        return $parent;
    }
    
    public function testSaveOnRollback() : void
    {
        $parent = $this->getMockParent();
        
        $field = (new StringType('myfield'))->SetParent($parent);
        $this->assertFalse($field->isSaveOnRollback());
        
        $field = (new StringType('myfield', true))->SetParent($parent);
        $this->assertTrue($field->isSaveOnRollback());
    }
    
    public function testID() : void
    {
        $database = $this->CreateMock(ObjectDatabase::class);
        $parent = new TestObject1($database, array('id'=>$id='test123'));
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $this->assertSame("$id:myfield", $field->ID());
    }
    
    public function testNullDefault() : void
    {
        $parent = $this->getMockParent();
        
        $field = (new NullIntType('myfield', false))->SetParent($parent);
        $this->assertFalse($field->isModified());
        $this->assertSame(0, $field->GetDelta());
        $this->assertSame(null, $field->TryGetValue());
        
        $field = (new NullIntType('myfield', false, 5))->SetParent($parent);
        $this->assertTrue($field->isModified());
        $this->assertSame(1, $field->GetDelta());
        $this->assertSame(5, $field->TryGetValue());
    }
    
    public function testDefault() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield', false, 5))->SetParent($parent);
        
        $this->assertTrue($field->isModified());
        $this->assertSame(1, $field->GetDelta());
        $this->assertSame(5, $field->GetValue());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field = (new IntType('myfield', false, "test"))->SetParent($parent); /** @phpstan-ignore-line */
    }
    
    public function testRestoreDefault() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield', false, 5))->SetParent($parent);
        
        $this->assertSame(5, $field->GetValue());
        
        $field->SetValue(10);
        $this->assertSame(10, $field->GetValue());
        
        $field->RestoreDefault();
        $this->assertSame(5, $field->GetValue());
        
        $field = (new NullIntType('myfield'))->SetParent($parent);
        
        $this->assertSame(null, $field->TryGetValue());
        
        $field->SetValue(10);
        $this->assertSame(10, $field->TryGetValue());
        
        $field->RestoreDefault();
        $this->assertSame(null, $field->TryGetValue());
    }
    
    public function testDelta() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $this->assertSame(0, $field->GetDelta());
        $this->assertFalse($field->isModified());
        
        $this->assertTrue($field->SetValue(5)); 
        $this->assertSame(1, $field->GetDelta());
        $this->assertTrue($field->isModified());
        
        $this->assertTrue($field->SetValue(6)); 
        $this->assertSame(2, $field->GetDelta());
        
        $this->assertTrue($field->SetValue(7)); 
        $this->assertSame(3, $field->GetDelta());
        
        $this->assertFalse($field->SetValue(7)); 
        $this->assertSame(3, $field->GetDelta());
    }

    public function testInitValue() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
            
        $field->InitDBValue(75);
        
        $this->assertSame(0, $field->GetDelta());
        $this->assertFalse($field->isModified());
        $this->assertSame(75, $field->GetDBValue());
        $this->assertSame(75, $field->GetValue());
        $this->assertSame(75, $field->GetValue(false));
    }
    
    public function testTempValue() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(75);
        
        $field->SetValue(22, true);
        $this->assertSame(22, $field->GetValue());
        $this->assertSame(75, $field->GetValue(false));
        $this->assertSame(75, $field->GetDBValue());
        
        $this->assertFalse($field->isModified());
        $this->assertSame(0, $field->GetDelta());
    }

    public function testSaveDBValue() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $this->assertSame(0, $field->GetDelta());
        
        $field->SetValue(5);
        
        $this->assertSame(1, $field->GetDelta());
        $this->assertSame(5, $field->GetDBValue());
        $this->assertSame(5, $field->SaveDBValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testCheckValueInit() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->isInitialized());
        $field->InitDBValue(75);
        $this->assertTrue($field->isInitialized());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->InitDBValue("test");
    }
    
    public function testInitBool() : void
    {
        $parent = $this->getMockParent();
        $field = (new BoolType('myfield'))->SetParent($parent);
        
        $field->InitDBValue(3);
        
        $this->assertSame(true, $field->GetValue());
    }
    
    public function testCheckValueSet() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->isInitialized());
        $field->SetValue(75);
        $this->assertTrue($field->isInitialized());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue("test"); /** @phpstan-ignore-line */
    }
    
    public function testNullString() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullStringType('myfield'))->SetParent($parent);
        
        $field->SetValue(null);
        $field->SetValue("test");
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(57); /** @phpstan-ignore-line */
    }
    
    public function testNullEmptyString() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullStringType('myfield'))->SetParent($parent);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue("");
    }
    
    public function testNullBool() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullBoolType('myfield'))->SetParent($parent);
        
        $field->SetValue(null);
        $this->assertSame(null, $field->GetDBValue());
        
        $field->SetValue(false);
        $field->SetValue(true);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(57); /** @phpstan-ignore-line */
    }
    
    public function testNullInt() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullIntType('myfield'))->SetParent($parent);
        
        $field->SetValue(null);
        $field->SetValue(0);
        $field->SetValue(57);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(57.123); /** @phpstan-ignore-line */
    }
    
    public function testNullFloat() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullFloatType('myfield'))->SetParent($parent);
        
        $field->SetValue(null);
        $field->SetValue(0.0);
        $field->SetValue(57.123);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(57);
    }
    
    public function testString() : void
    {
        $parent = $this->getMockParent();
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $field->SetValue("test");
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }
    
    public function testEmptyString() : void
    {
        $parent = $this->getMockParent();
        $field = (new StringType('myfield'))->SetParent($parent);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue("");
    }
    
    public function testBool() : void
    {
        $parent = $this->getMockParent();
        $field = (new BoolType('myfield'))->SetParent($parent);
        
        $field->SetValue(false);
        $this->assertSame(0, $field->GetDBValue());
        
        $field->SetValue(true);
        $this->assertSame(1, $field->GetDBValue());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }
    
    public function testInt() : void
    {
        $parent = $this->getMockParent();
        $field = (new IntType('myfield'))->SetParent($parent);
        
        $field->SetValue(0);
        $field->SetValue(57);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }
    
    public function testFloat() : void
    {
        $parent = $this->getMockParent();
        $field = (new FloatType('myfield'))->SetParent($parent);
        
        $field->SetValue(0.0);
        $field->SetValue(57.123);
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }

    public function testCounter() : void
    {
        $parent = $this->getMockParent();
        $field = (new Counter('myfield'))->SetParent($parent);
        
        $this->assertFalse($field->isModified());
        $this->assertSame(0, $field->GetValue());
        $this->assertSame(0, $field->GetDelta());
        $this->assertSame(0, $field->GetDBValue());
        
        $field->DeltaValue(5);
        $this->assertSame(5, $field->GetDelta());
        
        $field->InitDBValue(100);
        $this->assertFalse($field->isModified());
        $this->assertSame(0, $field->GetDBValue());
        
        $field->DeltaValue();
        $this->assertSame(101, $field->GetValue());
        $this->assertSame(1, $field->GetDelta());
        $this->assertSame(1, $field->GetDBValue());
        
        $field->DeltaValue(49);
        $this->assertSame(50, $field->GetDelta());
        $this->assertSame(150, $field->GetValue());
        
        $field->DeltaValue(-100);
        $this->assertSame(-50, $field->GetDelta());
        $this->assertSame(50, $field->GetValue());
        
        $this->assertSame(-50, $field->SaveDBValue());
        $this->assertSame(50, $field->GetValue());
        $this->assertSame(0, $field->GetDelta());
    }
    
    public function testCounterLimitCheck() : void
    {
        $parent = $this->getMockParent();
        
        $limit = (new NullIntType('mylimit'))->SetParent($parent);
        $counter = (new Counter('mycounter', false, $limit))->SetParent($parent);
        
        $this->assertTrue($counter->CheckDelta(9999));
        
        $limit->SetValue(10);        
        $this->assertTrue($counter->CheckDelta(1));
        $this->assertTrue($counter->CheckDelta(10));
        $this->assertFalse($counter->CheckDelta(11, false));
        
        $counter->InitDBValue(20);        
        $this->assertTrue($counter->CheckDelta(-1));
        $this->assertTrue($counter->CheckDelta(0));
        $this->assertFalse($counter->CheckDelta(1, false));

        $this->expectException(CounterOverLimitException::class);
        $counter->CheckDelta(1);
    }
    
    public function testCounterLimitDelta() : void
    {
        $parent = $this->getMockParent();
        
        $limit = (new NullIntType('mylimit', false, 10))->SetParent($parent);
        $this->assertSame(10, $limit->TryGetValue());
        
        $counter = (new Counter('mycounter', false, $limit))->SetParent($parent);
        
        $counter->DeltaValue(10); // okay
        
        $counter->InitDBValue(0);
        $counter->DeltaValue(20, true); // ignore
        
        $this->expectException(CounterOverLimitException::class);
        $counter->InitDBValue(0);
        $counter->DeltaValue(20);
    }
    
    public function testJsonArray() : void
    {
        $parent = $this->getMockParent();
        $field = (new JsonArray('myjson'))->SetParent($parent);
        
        $json = '{"key1":"val1","key2":5}';
        $field->InitDBValue($json);
        $this->assertSame(array('key1'=>'val1','key2'=>5), $field->GetValue());
        $this->assertSame($json, $field->GetDBValue());
        
        $array = array('key3'=>'val3','key4'=>7);
        $field->SetValue($array);
        $this->assertSame($array, $field->GetValue());
        $this->assertSame('{"key3":"val3","key4":7}', $field->GetDBValue());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }
    
    public function testNullJsonArray() : void
    {
        $parent = $this->getMockParent();
        $field = (new NullJsonArray('myjson'))->SetParent($parent);
        
        $field->InitDBValue(null);
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(null, $field->TryGetValue());

        $json = '{"key1":"val1","key2":5}';
        $field->InitDBValue($json);
        $this->assertSame(array('key1'=>'val1','key2'=>5), $field->TryGetValue());
        $this->assertSame($json, $field->GetDBValue());

        $field->SetValue(null);
        $this->assertSame(null, $field->GetDBValue());
        $this->assertSame(null, $field->TryGetValue());
        
        $array = array('key3'=>'val3','key4'=>7);
        $field->SetValue($array);
        $this->assertSame($array, $field->TryGetValue());
        $this->assertSame('{"key3":"val3","key4":7}', $field->GetDBValue());
    }
    
    public function testNullObjectInit() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
 
        $field = (new NullObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $this->assertNull($field->GetValue());

        $field->InitDBValue($id='test123');
        $this->assertSame($id, $field->GetDBValue());
        
        $database->expects($this->once())
            ->method('TryLoadUniqueByKey')
            ->with(TestObject1::class, 'id', $id)
            ->willReturn($obj = $this->createMock(TestObject1::class));
        
        $this->assertSame($obj, $field->GetValue());
    }
    
    public function testNullObjectValue() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new NullObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $database->expects($this->exactly(0))
            ->method('TryLoadUniqueByKey');
    
        $field->InitDBValue('test123');
        
        $obj = new TestObject1($database, array('id'=>'test456'));
        
        $field->SetValue($obj);
        $this->assertSame($obj, $field->GetValue());
        
        $field->SetValue(null);
        $this->assertSame(null, $field->GetValue());
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue($this->createMock(TestObject2::class)); /** @phpstan-ignore-line */
    }
    
    public function testObjectInit() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new ObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $obj = $this->createStub(TestObject1::class);
        
        $field->InitDBValue($id='test123');
        $this->assertSame($id, $field->GetDBValue());
        
        $database->expects($this->once())
            ->method('TryLoadUniqueByKey')
            ->with(TestObject1::class, 'id', $id)
            ->willReturn($obj);
            
        $this->assertSame($obj, $field->GetValue());
    }
    
    public function testObjectValue() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new ObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        
        $database->expects($this->exactly(0))
            ->method('TryLoadUniqueByKey');
        
        $field->InitDBValue('test123');
        
        $obj = new TestObject1($database, array('id'=>'test456'));
        
        $field->SetValue($obj);
        $this->assertSame($obj, $field->GetValue());
        
        $field->RestoreDefault();
        
        $this->expectException(FieldDataTypeMismatch::class);
        $field->SetValue(null); /** @phpstan-ignore-line */
    }

    public function testObjectDBValue() : void
    {
        $database = $this->createMock(ObjectDatabase::class);
        $parent = $this->createMock(BaseObject::class);
        $parent->method('GetDatabase')->willReturn($database);
        
        $field = (new ObjectRefT(TestObject1::class, 'myobj'))->SetParent($parent);
        $obj = new TestObject1($database, array('id'=>'test123'));
        
        $database->expects($this->once())->method('TryLoadUniqueByKey')->willReturn($obj);
        
        $field->SetValue($obj);
        $this->assertSame($obj, $field->GetValue()); // no DB call (have temp obj)
        $this->assertSame($obj->ID(), $field->GetDBValue());
        $this->assertSame($obj->ID(), $field->GetObjectID());
        $this->assertSame($obj->ID(), $field->SaveDBValue());
        $this->assertSame($obj, $field->GetValue()); // calls DB (no temp obj)
    }
}
