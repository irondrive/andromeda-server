<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\Fields;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class KeyNotFoundException extends Exceptions\ServerException   { public $message = "DB_OBJECT_KEY_NOT_FOUND"; }
class NotCounterException extends Exceptions\ServerException    { public $message = "DB_OBJECT_DELTA_NON_COUNTER"; }
class ChangeNullRefException extends Exceptions\ServerException { public $message = "ADD_OR_REMOVE_NULL_REFERENCE"; }
class ObjectNotFoundException extends Exceptions\ServerException { public $message = "OBJECT_NOT_FOUND"; }
class NullValueException extends Exceptions\ServerException     { public $message = "VALUE_IS_NULL"; }
class SaveDeletedException extends Exceptions\ServerException   { public $message = "MODIFIED_DELETED_OBJECT"; }

abstract class BaseObject
{
    protected $database; 
    
    public static function GetClass() : string { return static::class; }
    
    public static function LoadByID(ObjectDatabase $database, string $id) : self
    {
        return self::LoadByUniqueKey($database,'id',$id);
    }
    
    public static function TryLoadByID(ObjectDatabase $database, ?string $id) : ?self
    {
        if ($id === null) return null;
        else return self::TryLoadByUniqueKey($database,'id',$id);
    }
        
    public static function LoadManyByID(ObjectDatabase $database, array $ids) : array 
    {
        return self::LoadManyMatchingAny($database, 'id', $ids); 
    }
        
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null) : array 
    {
        return self::LoadManyMatchingAll($database, null, false, $limit); 
    }
        
    protected static function LoadByUniqueKey(ObjectDatabase $database, string $field, string $key) : self
    {
        $class = static::class; $object = $database->TryLoadObjectByUniqueKey($class, $field, $key);
        if ($object !== null) return $object; else throw new ObjectNotFoundException($class);
    }
        
    protected static function TryLoadByUniqueKey(ObjectDatabase $database, string $field, ?string $key) : ?self
    {
        if ($key === null) return null;
        $class = static::class; return $database->TryLoadObjectByUniqueKey($class, $field, $key); 
    }

    protected static function LoadManyMatchingAny(ObjectDatabase $database, string $field, array $keys, bool $like = false, ?int $limit = null) : array 
    {
        $class = static::class; return $database->LoadObjectsMatchingAny($class, $field, $keys, $like, $limit); 
    }

    public static function LoadManyMatchingAll(ObjectDatabase $database, ?array $criteria, bool $like = false, ?int $limit = null) : array 
    {              
        $class = static::class; return $database->LoadObjectsMatchingAll($class, $criteria, $like, $limit); 
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
        if (!$this->ExistsScalar($field)) throw new KeyNotFoundException($field);
        $value = $this->scalars[$field]->GetValue();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    protected function TryGetScalar(string $field)
    {
        if (!$this->ExistsScalar($field)) return null;
        return $this->scalars[$field]->GetValue();
    }
    
    protected function GetObject(string $field) : self
    {
        if (!$this->ExistsObject($field)) throw new KeyNotFoundException($field);
        $value = $this->objects[$field]->GetObject();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    protected function TryGetObject(string $field) : ?self
    {
        if (!$this->ExistsObject($field)) return null;
        return $this->objects[$field]->GetObject();
    }
    
    protected function GetObjectRefs(string $field) : array
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function TryGetObjectRefs(string $field) : ?array
    {
        if (!$this->ExistsObjectRefs($field)) return null;
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function SetScalar(string $field, $value, bool $temp = false) : self
    {    
        if (!$this->ExistsScalar($field))
        {
            if ($value === null) return $this;
            else throw new KeyNotFoundException($field);
        }
        
        if ($this->scalars[$field]->SetValue($value, $temp))
            $this->database->setModified($this);
        return $this;
    } 
    
    protected function TrySetScalar(string $field, $value, bool $temp = false) : self
    {
        if ($this->ExistsScalar($field)) $this->SetScalar($field, $value, $temp); return $this;
    } 
    
    protected function DeltaScalar(string $field, int $delta) : self
    {
        if ($delta == 0) return $this;
        
        if (!$this->ExistsScalar($field)) throw new KeyNotFoundException($field);
        
        if (!($this->scalars[$field] instanceof Fields\Counter))
            throw new NotCounterException(get_class($this->scalars[$field]));
        
        if ($this->scalars[$field]->Delta($delta))
            $this->database->setModified($this);
        
        return $this;
    }
    
    protected function SetObject(string $field, ?BaseObject $object, bool $notification = false) : self
    {
        if (!$this->ExistsObject($field)) 
        {
            if ($object === null) return $this;
            else throw new KeyNotFoundException($field);
        }
        
        if ($object === $this->objects[$field]) return $this;
        
        if (!$notification)
        {
            $oldref = $this->objects[$field]->GetObject();
            $reffield = $this->objects[$field]->GetRefField();
            
            if ($oldref !== null && $reffield !== null)
                $oldref->RemoveObjectRef($reffield, $this, true);
                
            if ($object !== null && $reffield !== null)
                $object->AddObjectRef($reffield, $this, true);
        }
        
        if ($this->objects[$field]->SetObject($object))
            $this->database->setModified($this);

        return $this;
    } 
    
    protected function TrySetObject(string $field, ?BaseObject $object, bool $notification = false) : self
    {
        if ($this->ExistsObject($field)) $this->SetObject($field, $object, $notification); return $this;
    } 
    
    protected function UnsetObject(string $field, bool $notification = false) : self 
    { 
        return $this->SetObject($field, null, $notification); 
    }
        
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);

        $reffield = $this->objectrefs[$field]->GetRefField();        
        if ($reffield !== null) $object->SetObject($reffield, $this, true);

        $this->objectrefs[$field]->AddObject($object, $notification);
        return $this;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        
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
    
    public function Save(bool $new = false) : self
    {
        if ($this->deleted) throw new SaveDeletedException();
        
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
        
        if (!$new) return $this->database->SaveObject($class, $this, $values, $counters);
        else return $this->database->SaveNewObject($class, $this, $values, $counters);
    } 
    
    private $deleted = false;
    
    public function Delete()
    {
        $class = static::class; 
        
        foreach ($this->objects as $field)
        {
            $object = $field->GetObject(); $reffield = $field->GetRefField();
            if ($object !== null && $reffield !== null) $object->RemoveObjectRef($reffield, $this, true);
        }
        
        foreach ($this->objectrefs as $refs)
        {
            $objects = $refs->GetObjects(); $reffield = $refs->GetRefField();
            foreach ($objects as $object) $object->UnsetObject($reffield, true);
        }
        
        $this->database->DeleteObject($class, $this); $this->deleted = true;
    }
    
    protected static function BaseCreate(ObjectDatabase $database)
    {
        $class = static::class; return $database->CreateObject($class);
    }

}
