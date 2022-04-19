<?php namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/Database/ObjectDatabase.php");
require_once(ROOT."/Core/Database/FieldTypes.php");
require_once(ROOT."/Core/Database/BaseObject.php");
require_once(ROOT."/Core/Database/TableTypes.php");

class OnDelete { public function Delete() : void { } }

final class EasyObject extends BaseObject
{
    use TableNoChildren;
    
    public static function Create(ObjectDatabase $database) : self
    {
        return parent::BaseCreate($database);
    }
    
    private FieldTypes\NullIntType $uniqueKey;
    private FieldTypes\NullIntType $generalKey;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->uniqueKey = $fields[] = new FieldTypes\NullIntType('uniqueKey');
        $this->generalKey = $fields[] = new FieldTypes\NullIntType('generalKey');

        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    protected static function AddUniqueKeys(array& $keymap) : void
    {
        $keymap[self::class] = array('uniqueKey');
        
        parent::AddUniqueKeys($keymap);
    }
    
    private bool $postConstruct = false;
    protected function PostConstruct() : void { $this->postConstruct = true; }
    public function didPostConstruct() : bool { return $this->postConstruct; }
    
    public function Delete() : void { parent::Delete(); }
    
    public function GetUniqueKey() : ?int { return $this->uniqueKey->TryGetValue(); }
    public function SetUniqueKey(?int $val) : self { $this->uniqueKey->SetValue($val); return $this; }
    
    public function GetGeneralKey() : ?int { return $this->generalKey->TryGetValue(); }
    public function SetGeneralKey(?int $val) : self { $this->generalKey->SetValue($val); return $this; }
}

abstract class MyObjectBase extends BaseObject
{
    use TableLinkedChildren;
    
    private FieldTypes\NullIntType $mykey;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->mykey = $fields[] = new FieldTypes\NullIntType('mykey');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    protected static function AddUniqueKeys(array& $keymap) : void
    {
        $keymap[self::class] = array('mykey');
        
        parent::AddUniqueKeys($keymap);
    }
    
    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array { 
        return array(MyObjectChild::class); }
    
    public function GetMyKey() : ?int { return $this->mykey->TryGetValue(); }
    public function SetMyKey(?int $val) : self { $this->mykey->SetValue($val); return $this; }
}

final class MyObjectChild extends MyObjectBase
{
    use TableNoChildren;
}

abstract class PolyObject_ extends BaseObject { }

abstract class PolyObject0 extends PolyObject_
{
    // empty, no table!
    
    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        return array(PolyObject1::class); // so we can load via it
    }
    
    private ?OnDelete $ondel = null;
    
    public function SetOnDelete(OnDelete $del) : void { $this->ondel = $del; }
    
    public function notifyDeleted() : void
    {
        if ($this->ondel) $this->ondel->Delete();
        
        parent::notifyDeleted();
    }
}

abstract class PolyObject1 extends PolyObject0
{
    use TableLinkedChildren;
    
    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        return array(27=>PolyObject2::class); // skip over PolyObject15
    }
    
    private FieldTypes\NullIntType $testprop1;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->testprop1 = $fields[] = new FieldTypes\NullIntType('testprop1');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public function GetTestProp1() : ?int
    { 
        return $this->testprop1->TryGetValue(); 
    }
    
    public function SetTestProp1(?int $val) : self
    {
        $this->testprop1->SetValue($val); return $this;
    }
}

abstract class PolyObject15 extends PolyObject1
{
    // not part of the "loadable" chain - transparent to DB
    
    private FieldTypes\NullIntType $testprop15;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->testprop15 = $fields[] = new FieldTypes\NullIntType('testprop15');
        
        $this->RegisterChildFields($fields);
        
        parent::CreateFields();
    }
    
    public function GetTestProp15() : ?int
    {
        return $this->testprop15->TryGetValue();
    }
    
    public function SetTestProp15(?int $val) : self
    {
        $this->testprop15->SetValue($val); return $this;
    }
}

abstract class PolyObject2 extends PolyObject15
{
    use TableLinkedChildren;

    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        return array(27=>PolyObject3::class);
    }
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
}

abstract class PolyObject3 extends PolyObject2
{
    // does not have its own table! but is part of the loadable chain
    
    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        return array(PolyObject4::class);
    }
}

class PolyObject4 extends PolyObject3
{
    use TableTypedChildren;

    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        // includes self::class - this class is not abstract!
        return array(5=>self::class, 13=>PolyObject5a::class, 18=>PolyObject5b::class);
    }
    
    private FieldTypes\IntType $testprop4;
    private FieldTypes\NullIntType $testprop4n; // null
    private FieldTypes\Counter $testprop4c; // counter
    
    protected function CreateFields() : void
    {
        $fields = $this->GetTypeFields();
        
        $this->testprop4  = $fields[] = new FieldTypes\IntType('testprop4');
        $this->testprop4n = $fields[] = new FieldTypes\NullIntType('testprop4n');
        $this->testprop4c = $fields[] = new FieldTypes\Counter('testprop4c');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public function GetTestProp4() : int
    {
        return $this->testprop4->GetValue();
    }
    
    public function SetTestProp4(int $val) : self
    {
        $this->testprop4->SetValue($val); return $this;
    }
    
    public function SetTestProp4n(?int $val) : self
    {
        $this->testprop4n->SetValue($val); return $this;
    }
    
    public function DeltaTestProp4c(int $val) : self
    {
        $this->testprop4c->DeltaValue($val); return $this;
    }
}

class PolyObject5a extends PolyObject4
{   
    use TableTypedChildren;
    
    /** @return ?array<class-string<self>> */
    public static function GetChildMap() : ?array
    {
        // includes self::class - this class is not abstract!
        return array(100=>self::class, 101=>PolyObject5aa::class, 102=>PolyObject5ab::class);
    }

    public static function Create(ObjectDatabase $database) : self
    {
        return self::BaseCreate($database);
    }
    
    private FieldTypes\NullIntType $testprop5;
    
    protected function CreateFields() : void
    {
        $fields = $this->GetTypeFields();
        
        $this->testprop5 = $fields[] = new FieldTypes\NullIntType('testprop5');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    protected static function AddUniqueKeys(array& $keymap) : void
    {
        $keymap[self::class] = array('testprop5');
        
        parent::AddUniqueKeys($keymap);
    }
    
    public function GetTestProp5() : ?int
    {
        return $this->testprop5->TryGetValue();
    }
    
    public function SetTestProp5(?int $val) : self
    {
        $this->testprop5->SetValue($val); return $this;
    }
    
    public function Delete() : void
    {
        parent::Delete();
    }
}

final class PolyObject5aa extends PolyObject5a
{
    use NoChildren;
}

final class PolyObject5ab extends PolyObject5a
{
    use NoChildren;
}

final class PolyObject5b extends PolyObject4
{
    use NoChildren;
}
