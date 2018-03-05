<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\Fields;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class BaseObjectSpecialColumnException extends Exceptions\ServerException { public $message = "DB_INVALID_SPEICAL_COLUMN"; }
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
        
    public static function LoadByUniqueKey(ObjectDatabase $database, string $field, string $key) : BaseObject 
    {
        $class = static::class; $object = $database->TryLoadObjectByUniqueKey($class, $field, $key);
        if ($object !== null) return $object; else throw new ObjectNotFoundException($class);
    }
        
    public static function TryLoadByUniqueKey(ObjectDatabase $database, string $field, ?string $key) : ?BaseObject 
    {
        if ($key === null) return null;
        $class = static::class; return $database->TryLoadObjectByUniqueKey($class, $field, $key); 
    }

    public static function LoadManyMatchingAny(ObjectDatabase $database, string $field, array $keys) : array 
    {
        $class = static::class; return $database->LoadObjectsMatchingAny($class, $field, $keys); 
    }

    public static function LoadManyMatchingAll(ObjectDatabase $database, ?array $criteria, int $limit = -1) : array 
    {              
        $class = static::class; return $database->LoadObjectsMatchingAll($class, $criteria, $limit); 
    } 
    
    public static function LoadManyByJoin(ObjectDatabase $database, string $joinclass, string $joinid, int $limit = -1) : array 
    {
        $class = static::class; return $database->LoadObjectsByJoins($class, $joinclass, $joinid, $limit); 
    }
    
    public function ID() : string { return $this->scalars['id']->GetValue(); }
    
    protected $scalars = array();
    protected $objects = array();
    protected $objectpolys = array();
    protected $objectrefs = array();
    protected $objectjoins = array();
    
    protected function ExistsScalar(string $field) : bool { return array_key_exists($field, $this->scalars); }
    protected function ExistsObject(string $field) : bool { return array_key_exists($field, $this->objects); }
    protected function ExistsPolyObject(string $field) : bool  { return array_key_exists($field, $this->objectpolys); }
    protected function ExistsObjectRefs(string $field) : bool  { return array_key_exists($field, $this->objectrefs); }
    protected function ExistsObjectJoins(string $field) : bool { return array_key_exists($field, $this->objectjoins); }
    
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
    
    protected function GetObject(string $field)
    {
        if (!$this->ExistsObject($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objects[$field]->GetObject();
    }
    
    protected function TryGetObject(string $field)
    {
        if (!$this->ExistsObject($field)) return null;
        return $this->objects[$field]->GetObject();
    }
    
    protected function GetPolyObject(string $field)
    {
        if (!$this->ExistsPolyObject($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objectpolys[$field]->GetObject();
    }
    
    protected function TryGetPolyObject(string $field)
    {
        if (!$this->ExistsPolyObject($field)) return null;
        return $this->objectpolys[$field]->GetObject();
    }
    
    protected function GetObjectRefs(string $field)
    {
        if (!$this->ExistsObjectRefs($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function TryGetObjectRefs(string $field)
    {
        if (!$this->ExistsObjectRefs($field)) return null;
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function GetObjectJoins(string $field)
    {
        if (!$this->ExistsObjectJoins($field)) throw new BaseObjectKeyNotFoundException($field);
        return $this->objectjoins[$field]->GetObjects();
    }
    
    protected function TryGetObjectJoins(string $field)
    {
        if (!$this->ExistsObjectJoins($field)) return null;
        return $this->objectrefs[$field]->GetObjects();
    }
    
    protected function SetScalar(string $field, $value, bool $temp = false)
    {    
        if (!$this->ExistsScalar($field)) throw new BaseObjectKeyNotFoundException($field);
        $this->scalars[$field]->SetValue($value);
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
    
    protected function SetPolyObject(string $field, ?BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsPolyObject($field)) throw new BaseObjectKeyNotFoundException($field);
        if ($object == $this->objectpolys[$field]) return;
        
        if (!$notification)
        {
            $oldref = $this->objectpolys[$field]->GetObject();
            $reffield = $this->objectpolys[$field]->GetRefField();
            
            if ($oldref !== null && $reffield !== null)
                $oldref->RemoveObjectRef($reffield, $this, true);
                
            if ($object !== null && $reffield !== null)
                $object->AddObjectRef($reffield, $this, true);
        }
        
        $class = explode("\\",get_class($object)); unset($class[0]); $class = implode("\\",$class);
        $this->SetScalar("type__$field", $class);
        
        $this->objectpolys[$field]->SetObject($object);
        $this->database->setModified($this);
        return $this;
    }
    
    protected function UnsetObject(string $field, bool $notification = false) { 
        return $this->SetObject($field, null, $notification); }
        
    protected function UnsetPolyObject(string $field, bool $notification = false) { 
        return $this->SetPolyObject($field, null, $notification); }
    
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObjectRefs($field)) throw new BaseObjectKeyNotFoundException($field);

        $reffield = $this->objectrefs[$field]->GetRefField();        
        if ($reffield !== null) {
            if (substr($reffield, 0, 1) == "?") 
                $object->SetPolyObject(substr($reffield, 1), $this, true);
            else $object->SetObject($reffield, $this, true);
        }

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
    
    protected function AddObjectJoin(string $field, BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObjectJoins($field)) throw new BaseObjectKeyNotFoundException($field);
        
        $reffield = $this->objectjoins[$field]->GetRefField();
        
        if (!$notification) 
        {
            $object->AddObjectJoin($reffield, $this, true);
            $this->database->setModified($this);
        }
        
        $this->objectjoins[$field]->AddObject($object, $notification);
        return $this;
    }
    
    protected function RemoveObjectJoin(string $field, BaseObject $object, bool $notification = false)
    {
        if (!$this->ExistsObjectJoins($field)) throw new BaseObjectKeyNotFoundException($field);
         
        $reffield = $this->objectjoins[$field]->GetRefField();
        
        if (!$notification) 
        {
            $object->RemoveObjectJoin($reffield, $this, true);
            $this->database->setModified($this);
        }
        
        $this->objectjoins[$field]->RemoveObject($object, $notification);
        return $this;
    }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        $this->database = $database;
        
        foreach (array_keys($data) as $key) 
        {   
            $value = $data[$key];
            
            if (strpos($key,'*') === false) $this->scalars[$key] = new Fields\Scalar($value);
            else
            {
                $header = explode('*',$key); $key = $header[0]; $special = $header[1]; $count = count($header);
                
                if      ($special == "json")    $this->scalars[$key] = new Fields\JSON($value);
                else if ($special == "counter") $this->scalars[$key] = new Fields\Counter($value);
                
                else if ($special == "object" && ($count==3 || $count==4)) 
                {
                    $class = $header[2]; $field = ($count==4) ? $header[3] : null;
                    $this->objects[$key] = new Fields\ObjectPointer($this->database, $value, $class, $field);
                }
                
                else if ($special == "objectpoly" && ($count==2 || $count==3))
                {              
                    $typefield = "type__$key"; 
                    
                    if ($value && !isset($data[$typefield])) throw new BaseObjectKeyNotFoundException("polyobject type missing: $typefield"); 
                    
                    $class = $data[$typefield]; $field = ($count==3) ? $header[2] : null;
                    $this->objectpolys[$key] = new Fields\ObjectPointer($this->database, $value, $class, $field);
                }
                
                else if ($special == "objectrefs" && $count==4) 
                {
                    $myfield = $header[0]; $class = $header[2]; $field = $header[3];
                    $this->objectrefs[$key] = new Fields\ObjectRefs($this->database, static::class, $this->ID(), $class, $field, $myfield); 
                }
                    
                else if ($special == "objectjoins" && $count==4)
                {
                    $myfield = $header[0]; $class = $header[2]; $field = $header[3]; 
                    $this->objectjoins[$key] = new Fields\ObjectJoins($this->database, static::class, $this->ID(), $class, $field, $myfield); 
                }
                    
                else throw new BaseObjectSpecialColumnException("Class ".static::class." Column $key");
            }
        }        
    } 
    
    public function Save() 
    {
        $class = static::class; $values = array(); $counters = array();
        
        foreach(array_keys($this->scalars) as $key)
        {
            $value = $this->scalars[$key]; $special = $value::SPECIAL ?? "";
            if (!$value->GetDelta()) continue;
            if ($special) $key .= "*$special";
            
            if ($special == "counter") $counters[$key] = $value->ToString();
            else $values[$key] = $value->ToString();
        }
        
        foreach(array_keys($this->objects) as $key)
        {
            $value = $this->objects[$key]; 
            if (!$value->GetDelta()) continue;
            $key .= "*object*".$value->GetRefClass();
            $reffield = $value->GetRefField(); 
            if ($reffield !== null) $key .= "*$reffield";
            $values[$key] = $value->ToString();
        }
        
        foreach(array_keys($this->objectpolys) as $key)
        {
            $value = $this->objectpolys[$key];
            if (!$value->GetDelta()) continue;
            $key .= "*objectpoly";
            $reffield = $value->GetRefField();
            if ($reffield !== null) $key .= "*$reffield";
            $values[$key] = $value->ToString();
        }
        
        foreach($this->objectjoins as $join)
        {
            $added = $join->GetJoinsAdded(); $deleted = $join->GetJoinsDeleted(); 
            $refclass = "Andromeda\\".$join->GetRefClass();
            
            $this->database->SaveObjectJoins($class, $refclass, $this->ID(), $added, $deleted);
        }
        
        return $this->database->SaveObject($class, $this, $values, $counters);
    } 
    
    public function Delete()
    {
        $class = static::class; 
        
        foreach (array_merge($this->objects, $this->objectpolys) as $field)
        {
            $object = $field->GetObject(); $reffield = $field->GetRefField();
            if ($reffield !== null) $object->RemoveObjectRef($reffield, $this);
        }
        
        foreach ($this->objectrefs as $refs)
        {
            $objects = $refs->GetObjects(); $reffield = $refs->GetRefField();
            foreach ($objects as $object) $object->UnsetObject($reffield);
        }
        
        foreach ($this->objectjoins as $join)
        {
            $objects = $join->GetObjects(); $reffield = $join->GetRefField();
            foreach ($objects as $object) $object->RemoveObjectJoin($reffield, $this);
        }
        
        $this->database->DeleteObject($class, $this);
    }
    
    protected static function BaseCreate(ObjectDatabase $database, array $input, ?int $idlen = null)
    {
        $class = static::class; return $database->CreateObject($class, $input, $idlen);
    }

}
