<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/JoinUtils.php");
require_once(ROOT."/core/database/FieldTypes.php");
require_once(ROOT."/core/database/QueryBuilder.php");

class KeyNotFoundException extends DatabaseException    { public $message = "DB_OBJECT_KEY_NOT_FOUND"; }
class NotCounterException extends DatabaseException     { public $message = "DB_OBJECT_DELTA_NON_COUNTER"; }
class ChangeNullRefException extends DatabaseException  { public $message = "ADD_OR_REMOVE_NULL_REFERENCE"; }
class ObjectNotFoundException extends DatabaseException { public $message = "OBJECT_NOT_FOUND"; }
class NullValueException extends DatabaseException      { public $message = "VALUE_IS_NULL"; }

abstract class BaseObject
{
    public const IDLength = 12;
    protected ObjectDatabase $database; 
    
    public static function GetFieldTemplate() : array { return array(); }
    
    public static function GetDBClass() : string { return static::class; }
    
    public static function LoadByQuery(ObjectDatabase $database, QueryBuilder $query) : array
    {
        return $database->LoadObjectsByQuery(static::class, $query);
    }
    
    public static function DeleteByQuery(ObjectDatabase $database, QueryBuilder $query) : void
    {
        $database->DeleteObjectsByQuery(static::class, $query);
    }
    
    public static function NotNull(?self $obj) : self 
    { 
        if ($obj === null) throw new ObjectNotFoundException(static::class); return $obj; 
    }
    
    protected static function TryLoadUniqueByKey(ObjectDatabase $database, string $field, string $key) : ?self
    {
        return $database->TryLoadObjectByUniqueKey(static::class, $field, $key);
    }
    
    protected static function DeleteByUniqueKey(ObjectDatabase $database, string $field, string $key) : void
    {
        $q = new QueryBuilder(); static::DeleteByQuery($database, $q->Where($q->Equals($field, $key)));
    }
    
    public static function TryLoadUniqueByQuery(ObjectDatabase $database, QueryBuilder $query) : ?self
    {
        $result = static::LoadByQuery($database, $query);
        return count($result) ? array_values($result)[0] : null;
    }
    
    public static function TryLoadByID(ObjectDatabase $database, string $id) : ?self
    {
        return static::TryLoadUniqueByKey($database,'id',$id);
    }
    
    public static function DeleteByID(ObjectDatabase $database, string $id) : void
    {
        static::DeleteByUniqueKey($database,'id',$id);
    }
        
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array 
    {
        return static::LoadByQuery($database, (new QueryBuilder())->Limit($limit)->Offset($offset));
    }
    
    public static function DeleteAll(ObjectDatabase $database) : void
    {
        static::DeleteByQuery($database, (new QueryBuilder()));
    }
    
    public static function LoadByObjectID(ObjectDatabase $database, string $field, string $id, ?string $class = null) : array
    {
        $v = $class ? FieldTypes\ObjectPoly::GetIDTypeDBValue($id, $class) : $id;
        $q = new QueryBuilder(); return static::LoadByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    public static function DeleteByObjectID(ObjectDatabase $database, string $field, string $id, ?string $class = null) : void
    {
        $v = $class ? FieldTypes\ObjectPoly::GetIDTypeDBValue($id, $class) : $id;
        $q = new QueryBuilder(); static::DeleteByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    public static function LoadByObject(ObjectDatabase $database, string $field, BaseObject $object, bool $isPoly = false) : array
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        $q = new QueryBuilder(); return static::LoadByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    public static function DeleteByObject(ObjectDatabase $database, string $field, BaseObject $object, bool $isPoly = false) : void
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        $q = new QueryBuilder(); static::DeleteByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    public static function TryLoadUniqueByObject(ObjectDatabase $database, string $field, BaseObject $object, bool $isPoly = false) : ?self
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        return static::TryLoadUniqueByKey($database, $field, $v);
    }
    
    public static function DeleteByUniqueObject(ObjectDatabase $database, string $field, BaseObject $object, bool $isPoly = false) : void
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        static::DeleteByUniqueKey($database, $field, $v);
    }
    
    public function ID() : string { return $this->scalars['id']->GetValue(); }
    public function __toString() : string { return get_class($this)." ".$this->ID(); }
    
    protected array $scalars = array();
    protected array $objects = array();
    protected array $objectrefs = array();

    protected function ExistsScalar(string $field) : bool { return array_key_exists($field, $this->scalars); }
    protected function ExistsObject(string $field) : bool { return array_key_exists($field, $this->objects); }
    protected function ExistsObjectRefs(string $field) : bool { return array_key_exists($field, $this->objectrefs); }

    protected function GetScalar(string $field, bool $allowTemp = true)
    {
        if (!$this->ExistsScalar($field)) throw new KeyNotFoundException($field);
        $value = $this->scalars[$field]->GetValue($allowTemp);
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    protected function TryGetScalar(string $field, bool $allowTemp = true)
    {
        if (!$this->ExistsScalar($field)) return null;
        return $this->scalars[$field]->GetValue($allowTemp);
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
    
    protected function HasObject(string $field) : bool
    {
        if (!$this->ExistsObject($field)) throw new KeyNotFoundException($field);
        return boolval($this->objects[$field]->GetValue());
    }
    
    protected function GetObjectID(string $field) : string
    {
        if (!$this->ExistsObject($field)) throw new KeyNotFoundException($field);
        $value = $this->objects[$field]->GetValue();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    protected function TryGetObjectID(string $field) : ?string
    {
        if (!$this->ExistsObject($field)) return null;
        return $this->objects[$field]->GetValue();
    }
    
    protected function GetObjectType(string $field) : ?string
    {
        if (!$this->ExistsObject($field)) throw new KeyNotFoundException($field);
        return $this->objects[$field]->GetRefClass();
    }
    
    protected function DeleteObject(string $field) : self
    {
        if (!$this->ExistsObject($field)) return $this;
        $this->objects[$field]->DeleteObject(); return $this;
    }
    
    protected function GetObjectRefs(string $field, ?int $limit = null, ?int $offset = null) : array
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        return $this->objectrefs[$field]->GetObjects($limit, $offset);
    }
    
    protected function TryGetObjectRefs(string $field, ?int $limit = null, ?int $offset = null) : ?array
    {
        if (!$this->ExistsObjectRefs($field)) return null;
        return $this->objectrefs[$field]->GetObjects($limit, $offset);
    }
    
    protected function CountObjectRefs(string $field) : int
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        return $this->objectrefs[$field]->GetValue() ?? 0;
    }
    
    protected function GetJoinObject(string $field, BaseObject $obj) : StandardObject
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        return $this->objectrefs[$field]->GetJoinObject($obj);
    }
    
    protected function TryGetJoinObject(string $field, BaseObject $obj) : ?StandardObject
    {
        if (!$this->ExistsObjectRefs($field)) return 0;
        return $this->objectrefs[$field]->GetJoinObject($obj);
    }
    
    protected function DeleteObjectRefs(string $field) : self
    {
        if (!$this->ExistsObjectRefs($field)) return $this;
        $this->objectrefs[$field]->DeleteObjects(); return $this;
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
        if ($delta === 0) return $this;
        
        if (!$this->ExistsScalar($field)) throw new KeyNotFoundException($field);
        
        if ($this->scalars[$field]->GetOperatorType() !== FieldTypes\OPERATOR_INCREMENT)
            throw new NotCounterException($field);
        
        if ($this->scalars[$field]->Delta($delta))
            $this->database->setModified($this);
        
        return $this;
    }
    
    protected function TryDeltaScalar(string $field, int $delta) : self
    {
        if ($this->ExistsScalar($field)) $this->DeltaScalar($field, $delta); return $this;
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
            $usemany = $this->objects[$field]->GetRefIsMany();

            if ($reffield !== null)
            {
                if ($oldref !== null)
                {
                    if ($usemany)
                        $oldref->RemoveObjectRef($reffield, $this, true);
                    else $oldref->UnsetObject($reffield, true);
                }
                
                if ($object !== null)
                {
                    if ($usemany)
                        $object->AddObjectRef($reffield, $this, true);
                    else $object->SetObject($reffield, $this, true);
                }
            }
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
        
        $fieldobj = $this->objectrefs[$field];        
        
        if ($fieldobj->AddObject($object, $notification))
            $this->database->setModified($this);
        
        if (!$notification && $fieldobj->GetRefsType() === FieldTypes\REFSTYPE_SINGLE) 
            $object->SetObject($fieldobj->GetRefField(), $this, true);
        
        return $this;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if (!$this->ExistsObjectRefs($field)) throw new KeyNotFoundException($field);
        
        $fieldobj = $this->objectrefs[$field];        
        
        if ($this->objectrefs[$field]->RemoveObject($object, $notification))
            $this->database->setModified($this);            

        if (!$notification && $fieldobj->GetRefsType() === FieldTypes\REFSTYPE_SINGLE)
            $object->UnsetObject($fieldobj->GetRefField(), true);
        
        return $this;
    }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        $this->database = $database;

        $fields = static::GetFieldTemplate();
        $fields['id'] = new FieldTypes\Scalar();
        
        foreach ($fields as $key=>$field)
        {
            $field ??= new FieldTypes\Scalar();
            $field->Initialize($this->database, $this, $key);
            $fields[$key] = $field; $this->AddField($key, $field);            
        }
        
        foreach ($data as $column=>$value)
        {
            $fields[$column]->InitValue($value);
        }        

        $this->SubConstruct();
    }
    
    protected function AddField(string $key, $field)
    {
        $key = $field->GetMyField();
        switch ($field->GetReturnType())
        {
            case FieldTypes\RETURN_SCALAR: $this->scalars[$key] = $field; break;
            case FieldTypes\RETURN_OBJECT: $this->objects[$key] = $field; break;
            case FieldTypes\RETURN_OBJECTS: $this->objectrefs[$key] = $field; break;
        }
    }
    
    protected function SubConstruct() : void { }

    public function Save(bool $isRollback = false) : self
    {
        if ($isRollback && ($this->created || $this->deleted)) return $this;
        
        $class = static::class; $values = array(); $counters = array();

        if (!$this->deleted)
        {
            foreach (array_merge($this->scalars, $this->objects, $this->objectrefs) as $key => $value)
            {
                if (!$value->GetDelta()) continue;
                if ($isRollback && !$value->GetAlwaysSave()) continue;
    
                if ($value->GetOperatorType() === FieldTypes\OPERATOR_INCREMENT)
                    $counters[$key] = $value->GetDBValue();
                else $values[$key] = $value->GetDBValue();
                
                $value->ResetDelta();
            }
        }
        
        $this->database->SaveObject($class, $this, $values, $counters);
        $this->created = false; return $this;
    } 
    
    protected bool $deleted = false; public function isDeleted() : bool { return $this->deleted; }
    
    public function Delete() : void
    {
        if ($this->deleted) return;

        foreach ($this->objects as $field)
        {
            $myfield = $field->GetMyField();
            if ($field->GetValue()) $this->UnsetObject($myfield);
        }
        
        foreach ($this->objectrefs as $refs)
        {
            if (!$refs->GetValue()) continue;
            $objects = $refs->GetObjects(); $myfield = $refs->GetMyField();
            foreach ($objects as $object) $this->RemoveObjectRef($myfield, $object);
        }

        $this->database->setModified($this);
        $this->deleted = true; $this->Save();
    }
    
    protected bool $created = false; public function isCreated() : bool { return $this->created; }
    
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        $obj = $database->CreateObject(static::class);         
        $database->setModified($obj);
        $obj->created = true; return $obj;
    }
}
