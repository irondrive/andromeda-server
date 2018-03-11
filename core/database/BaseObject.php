<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\Fields;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class BaseObjectKeyNotFoundException extends Exceptions\ServerException   { public $message = "DB_OBJECT_KEY_NOT_FOUND"; }
class BaseObjectNotCounterException extends Exceptions\ServerException    { public $message = "DB_OBJECT_DELTA_NON_COUNTER"; }
class BaseObjectChangeNullRefException extends Exceptions\ServerException { public $message = "ADD_OR_REMOVE_NULL_REFERENCE"; }
class ObjectNotFoundException extends Exceptions\ServerException        { public $message = "OBJECT_NOT_FOUND"; }

abstract class BaseObject
{
    protected $database; 
    
    public static function GetClass() : string { return static::class; }
    
    public static function LoadByID(ObjectDatabase $database, string $id) : BaseObject 
    {
        return self::LoadByUniqueKey($database,'id',$id);
    }
    
    public static function TryLoadByID(ObjectDatabase $database, ?string $id) : ?BaseObject
    {
        if ($id === null) return null;
        else return self::TryLoadByUniqueKey($database,'id',$id);
    }
        
    public static function LoadManyByID(ObjectDatabase $database, array $ids) : array 
    {
        return self::LoadManyMatchingAny($database, 'id', $ids); 
    }
        
    public static function LoadAll(ObjectDatabase $database) : array 
    {
        return self::LoadManyMatchingAll($database, null); 
    }
        
    protected static function LoadByUniqueKey(ObjectDatabase $database, string $field, string $key) : BaseObject 
    {
        $class = static::class; $object = $database->TryLoadObjectByUniqueKey($class, $field, $key);
        if ($object !== null) return $object; else throw new ObjectNotFoundException($class);
    }
        
    protected static function TryLoadByUniqueKey(ObjectDatabase $database, string $field, ?string $key) : ?BaseObject 
    {
        if ($key === null) return null;
        $class = static::class; return $database->TryLoadObjectByUniqueKey($class, $field, $key); 
    }

    protected static function LoadManyMatchingAny(ObjectDatabase $database, string $field, array $keys) : array 
    {
        $class = static::class; return $database->LoadObjectsMatchingAny($class, $field, $keys); 
    }

    public static function LoadManyMatchingAll(ObjectDatabase $database, ?array $criteria, int $limit = -1) : array 
    {              
        $class = static::class; return $database->LoadObjectsMatchingAll($class, $criteria, $limit); 
    } 
    
    public function ID() : string { return $this->scalars['id']->GetValue(); }
    
    protected $scalars = array();
    protected $objects = array();
    protected $objectrefs = array();

    protected function ExistsScalar(string $field) : bool { return array_key_exists($field, $this->scalars); }
    protected function ExistsObject(string $field) : bool { return array_key_exists($field, $this->objects); }
    protected function ExistsObjectRefs(string $field) : bool  { return array_key_exists($field, $this->objectrefs); }

    protected function GetScalar(string $field)
    {
        if (!$this->ExistsScalar($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->scalars[$field]->GetValue();
    }
    
    protected function TryGetScalar(string $field)
    {
        if (!$this->ExistsScalar($field)) return null;
        return $this->scalars[$field]->GetValue();
    }
    
    protected function GetObject(string $field) : BaseObject
    {
        if (!$this->ExistsObject($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objects[$field]->GetObject();
    }
    
    protected function TryGetObject(string $field) : ?BaseObject
    {
        if (!$this->ExistsObject($field)) return null;
        return $this->objects[$field]->GetObject();
    }
    
    protected function GetObjectRefs(string $field) : array
    {
        if (!$this->ExistsObjectRefs($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function TryGetObjectRefs(string $field) : ?array
    {
        if (!$this->ExistsObjectRefs($field)) return null;
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function SetScalar(string $field, $value, bool $temp = false)
    {    
        if (!$this->ExistsScalar($field)) throw new BaseObjectKeyNotFoundException($field);
        $this->scalars[$field]->SetValue($value, $temp);
        if (!$temp) $this->database->setModified($this);
        return $this;
    } 
    
    protected function DeltaScalar(string $field, int $delta)
    {
        if ($delta == 0) return $this;
        
        if (!$this->ExistsScalar($field)) throw new BaseObjectKeyNotFoundException($field);
        
        if (!($this->scalars[$field] instanceof Fields\Counter))
            throw new BaseObjectNotCounterException(get_class($this->scalars[$field]));
        
        $this->scalars[$field]->Delta($delta);
        $this->database->setModified($this);
        return $this;
    }
    
    protected function SetObject(string $field, ?BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObject($field)) throw new BaseObjectKeyNotFoundException($field);
        if ($object == $this->objects[$field]) return;
        
        if (!$notification)
        {
            $oldref = $this->objects[$field]->GetObject();
            $reffield = $this->objects[$field]->GetRefField();
            
            if ($oldref !== null && $reffield !== null)
                $oldref->RemoveObjectRef($reffield, $this, true);
                
            if ($object !== null && $reffield !== null)
                $object->AddObjectRef($reffield, $this, true);
        }
        
        $this->objects[$field]->SetObject($object);
        $this->database->setModified($this);

        return $this;
    } 
    
    protected function UnsetObject(string $field, bool $notification = false) { 
        return $this->SetObject($field, null, $notification); }
        
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObjectRefs($field)) throw new BaseObjectKeyNotFoundException($field);

        $reffield = $this->objectrefs[$field]->GetRefField();        
        if ($reffield !== null) $object->SetObject($reffield, $this, true);

        $this->objectrefs[$field]->AddObject($object, $notification);
        return $this;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObjectRefs($field)) throw new BaseObjectKeyNotFoundException($field);
        
        $reffield = $this->objectrefs[$field]->GetRefField();
        if ($reffield !== null) $object->UnsetObject($reffield, true);
        
        $this->objectrefs[$field]->RemoveObject($object, $notification);
        return $this;
    }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        $this->database = $database;
        
        foreach (array_keys($data) as $key) 
        {   
            $field = Fields\Field::Init($this->database, $this, $key, $data[$key]); $key = $field->GetMyField();
            
            if ($field instanceof Fields\Scalar)              $this->scalars[$key] = $field;
            else if ($field instanceof Fields\ObjectPointer)  $this->objects[$key] = $field;
            else if ($field instanceof Fields\ObjectRefs)     $this->objectrefs[$key] = $field;
        } 
    } 
    
    public function Save() 
    {
        $class = static::class; $values = array(); $counters = array();
        
        foreach(array_keys($this->scalars) as $key)
        {
            $value = $this->scalars[$key];
            if (!$value->GetDelta()) continue;
            $column = $value->GetColumnName();
            
            if ($value instanceof Fields\Counter) 
                $counters[$column] = $value->GetDBValue();
            else $values[$column] = $value->GetDBValue();
        }
        
        foreach(array_keys($this->objects) as $key)
        {
            $value = $this->objects[$key]; 
            if (!$value->GetDelta()) continue;
            $column = $value->GetColumnName();
            $values[$column] = $value->GetDBValue();
        }
        
        return $this->database->SaveObject($class, $this, $values, $counters);
    } 
    
    public function Delete()
    {
        $class = static::class; 
        
        foreach ($this->objects as $field)
        {
            $object = $field->GetObject(); $reffield = $field->GetRefField();
            if ($reffield !== null) $object->RemoveObjectRef($reffield, $this, true);
        }
        
        foreach ($this->objectrefs as $refs)
        {
            $objects = $refs->GetObjects(); $reffield = $refs->GetRefField();
            foreach ($objects as $object) $object->UnsetObject($reffield);
        }
        
        $this->database->DeleteObject($class, $this);
    }
    
    protected static function BaseCreate(ObjectDatabase $database, array $input, ?int $idlen = null)
    {
        $class = static::class; return $database->CreateObject($class, $input, $idlen);
    }

}
