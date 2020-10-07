<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\Fields;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CounterOverLimitException extends Exceptions\ServerException    { public $message = "COUNTER_EXCEEDS_LIMIT"; }

interface ClientObject { public function GetClientObject(int $level = 0) : array; }

abstract class StandardObject extends BaseObject
{
    protected function GetDate(string $name) : ?int                { return $this->GetScalar("dates__$name"); }
    protected function TryGetDate(string $name) : ?int             { return $this->TryGetScalar("dates__$name"); } 
    
    protected function SetDate(string $name, int $value = null) : self
    { 
        if ($value === null) $value = time(); 
        return $this->SetScalar("dates__$name", $value); 
    }
    
    public function GetDateCreated() : int { return $this->GetDate('created'); }    
    protected static function BaseCreate(ObjectDatabase $database) {
        return parent::BaseCreate($database)->SetDate('created'); }
    
    protected function GetFeature(string $name) : ?int             { return $this->GetScalar("features__$name"); }
    protected function TryGetFeature(string $name) : ?int          { return $this->TryGetScalar("features__$name"); }
    protected function ExistsFeature(string $name) : ?int          { return $this->ExistsScalar("features__$name"); }
    
    protected function SetFeature(string $name, ?int $value, bool $temp = false) : self   { return $this->SetScalar("features__$name", $value, $temp); }
    protected function EnableFeature(string $name, bool $temp = false) : self             { return $this->SetScalar("features__$name", 1, $temp); }
    protected function DisableFeature(string $name, bool $temp = false) : self            { return $this->SetScalar("features__$name", 0, $temp); }
    
    protected function GetCounter(string $name) : ?int             { return $this->GetScalar("counters__$name"); }
    protected function TryGetCounter(string $name) : ?int          { return $this->TryGetScalar("counters__$name"); }
    protected function ExistsCounter(string $name) : ?int          { return $this->ExistsScalar("counters__$name"); }
    protected function GetCounterLimit(string $name) : ?int        { return $this->GetScalar("counters_limits__$name"); }
    protected function TryGetCounterLimit(string $name) : ?int     { return $this->TryGetScalar("counters_limits__$name"); }
    protected function ExistsCounterLimit(string $name) : ?int     { return $this->ExistsScalar("counters_limits__$name"); }
    
    protected function SetCounterLimit(string $name, ?int $value, bool $temp = false) : self  { return $this->SetScalar("counters_limits__$name", $value, $temp); }
    
    protected function DeltaCounter(string $name, int $delta = 1) : self
    { 
        if (($limit = $this->TryGetCounterLimit($name)) !== null) 
        {
            $value = $this->GetCounter($name) + $delta;
            if ($value > $limit) throw new CounterOverLimitException($name); 
        } 
            
        return $this->DeltaScalar("counters__$name",$delta); 
    }
    
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : BaseObject
    {        
        if (($limit = $this->TryGetCounterLimit($field)) !== null)
        {
            $value = $this->TryCountObjectRefs($field);
            if ($value === $limit) throw new CounterOverLimitException($field);
        }

        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function GetAllDates() : array        { return $this->GetAllScalars('dates'); }
    protected function GetAllFeatures() : array     { return $this->GetAllScalars('features'); }
    
    protected function GetAllCounters() : array
    { 
        $counters = $this->GetAllScalars('counters'); 
        foreach (array_keys($this->objectrefs) as $refskey) 
            $counters[$refskey] = $this->objectrefs[$refskey]->GetValue();
        return $counters;
    }
    
    protected function GetAllCounterLimits() : array { return $this->GetAllScalars('counters_limits'); }
    
    private function GetAllScalars(string $prefix) : array
    {
        $output = array(); 
        foreach (array_keys($this->scalars) as $key)
        {
            $keyarr = explode("__",$key); 
            if ($keyarr[0] === $prefix)
            {
                $realkey = implode('__',array_slice($keyarr, 0, 2));
                $output[$keyarr[1]] = $this->TryGetScalar($realkey);
            }                
        }
        return $output;
    } 
    
}

class DuplicateSingletonException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBSINGLETON"; }

abstract class SingletonObject extends StandardObject
{
    private static $instances = array();
    
    /* PHP hackery: singletonobject keeps its own global private static array of instances of subclasses
     * When a subclass runs load, it uses its own class name to index the global array of instances
     * Can't just have a single $instance because php doesn't consider the static property as part of the 
     * subclass ... the static:: keyword can refer to the subclass but self:: only refers to the base class */
    
    public static function Load(ObjectDatabase $database) : self
    {
        if (array_key_exists(static::class, self::$instances)) 
            return self::$instances[static::class];
        
        $objects = self::LoadManyMatchingAll($database, null);
        if (count($objects) > 1) throw new DuplicateSingletonException();
        else if (count($objects) == 0) throw new ObjectNotFoundException();
        
        else return (self::$instances[static::class] = array_values($objects)[0]);
    }
}

class JoinObject extends StandardObject
{
    static $createdjoins = array();
    
    private static function SerializeJoin(string $refclass, BaseObject $thisobj, BaseObject $destobj) : string
    {
        return $refclass.$thisobj->ID().$destobj->ID();
    }
    
    private static function TryGetCreatedJoin(string $refclass, BaseObject $obj1, BaseObject $obj2) : ?string
    {
        $try = self::SerializeJoin($refclass, $obj1, $obj2); 
        if (array_key_exists($try, self::$createdjoins)) return $try;
        
        $try = self::SerializeJoin($refclass, $obj2, $obj1);
        if (array_key_exists($try, self::$createdjoins)) return $try;        
        return null;
    }
    
    public static function CreateJoin(ObjectDatabase $database, Fields\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : void
    {
        $refclass = ObjectDatabase::GetFullClassName($joinobj->GetRefClass());
        $newobj = $database->CreateObject($refclass, false, static::class)
            ->SetObject($joinobj->GetMyField(), $destobj, true)->SetDate('created')
            ->SetObject($joinobj->GetRefField(), $thisobj, true);
        $newobj->created = true;
        
        $thisobj->AddObjectRef($joinobj->GetMyField(), $destobj, true);
        $destobj->AddObjectRef($joinobj->GetRefField(), $thisobj, true);
        self::$createdjoins[self::SerializeJoin($refclass, $thisobj, $destobj)] = $newobj;
    }
    
    public static function LoadJoinObject(ObjectDatabase $database, Fields\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : ?self
    {
        $refclass = ObjectDatabase::GetFullClassName($joinobj->GetRefClass());
        $created = self::TryGetCreatedJoin($refclass, $thisobj, $destobj);
        if ($created !== null) return self::$createdjoins[$created];        
        
        $tempobj = $database->CreateObject($refclass, true, static::class);
        
        $mycolname = $tempobj->objects[$joinobj->GetMyField()]->GetColumnName();
        $refcolname = $tempobj->objects[$joinobj->GetRefField()]->GetColumnName();
        $criteria = array($mycolname => $destobj->ID(), $refcolname => $thisobj->ID());
        
        $objects = $database->LoadObjectsMatchingAll($refclass, $criteria, false, null, null, static::class);
        return (count($objects) == 1) ? array_values($objects)[0] : null;
    }
    
    public static function DeleteJoin(ObjectDatabase $database, Fields\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : void
    {
        $obj = self::LoadJoinObject($database, $joinobj, $thisobj, $destobj);
        if ($obj !== null) $obj->Delete(); 
        
        $refclass = ObjectDatabase::GetFullClassName($joinobj->GetRefClass());
        $created = self::TryGetCreatedJoin($refclass, $thisobj, $destobj);
        if ($created !== null) unset(self::$createdjoins[$created]);
    }
}

