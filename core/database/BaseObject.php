<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/database/Database.php");
require_once(ROOT."/core/database/FieldTypes.php");
require_once(ROOT."/core/database/QueryBuilder.php");

/** Exception indicating the specified field name is invalid */
class KeyNotFoundException extends DatabaseException    { public $message = "DB_OBJECT_KEY_NOT_FOUND"; }

/** Exception indicating that the requested counter name is not a counter */
class NotCounterException extends DatabaseException     { public $message = "DB_OBJECT_DELTA_NON_COUNTER"; }

/** Exception indicating that the requested object is null */
class ObjectNotFoundException extends DatabaseException { public $message = "OBJECT_NOT_FOUND"; }

/** Exception indicating that the requested scalar is null */
class NullValueException extends DatabaseException      { public $message = "VALUE_IS_NULL"; }

/**
 * The base class for representing objects stored in the database.  
 * 
 * Manages interaction with the database, provides functions for managing object data, 
 * and provides helper functions for the outside world. Most of the public functions are intended
 * to be ignored in favor of more domain-specific alternatives provided by classes that extend this one.
 * 
 * All objects have a unique ID that globally identifies them.
 */
abstract class BaseObject
{
    /** The length of the ID used to identify the object */
    public const IDLength = 12;
    
    /** The object's primary reference to the database */
    protected ObjectDatabase $database; 
    
    /**
     * Gets a template array of the object's properties (columns).  
     * 
     * This template will be copied into the object when it is constructed.
     * If a field maps to null, a basic Scalar fieldtype will be used.
     * @return array<string, FieldTypes\Scalar> array of FieldTypes indexed by field names
     */
    public abstract static function GetFieldTemplate() : array;
    
    /**
     * Returns the name of the class that should be used in the database for the table name (cast down at save)
     * 
     * Defaults to the actual class used.  Can be overriden e.g. if multiple classes need to use the same table.
     */
    public static function GetDBClass() : string { return static::class; }
    
    /**
     * Returns the name of the class that should be used for a given DB row (cast up at load)
     * 
     * Defaults to the actual class used. Allows polymorphism on DB rows based on properties
     */
    public static function GetObjClass(array $row) : string { return static::class; }
    
    /**
     * Counts objects in the DB matching the given query
     * @param ObjectDatabase $database Reference to the database
     * @param QueryBuilder $query The query to use for matching objects
     * @return int count of matched objects
     */
    public static function CountByQuery(ObjectDatabase $database, QueryBuilder $query) : int
    {
        return $database->CountObjectsByQuery(static::class, $query);
    }
    
    /**
     * Loads an array of objects from the DB matching the given query
     * @param ObjectDatabase $database Reference to the database
     * @param QueryBuilder $query The query to use for matching objects
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    public static function LoadByQuery(ObjectDatabase $database, QueryBuilder $query) : array
    {
        return $database->LoadObjectsByQuery(static::class, $query);
    }
    
    /**
     * Deletes objects from the DB matching the given query
     * 
     * The objects are loaded when they are deleted and their Delete()s are run
     * @param ObjectDatabase $database Reference to the database
     * @param QueryBuilder $query The query to use for matching objects
     * @return int number of objects deleted
     */
    public static function DeleteByQuery(ObjectDatabase $database, QueryBuilder $query) : int
    {
        return $database->DeleteObjectsByQuery(static::class, $query);
    }
    
    /**
     * Loads a unique object matching the given query
     * @param ObjectDatabase $database Reference to the database
     * @param QueryBuilder $query the query to uniquely identify the object
     * @return self|NULL
     */
    public static function TryLoadUniqueByQuery(ObjectDatabase $database, QueryBuilder $query) : ?self
    {
        $result = static::LoadByQuery($database, $query);
        return count($result) ? array_values($result)[0] : null;
    }
    
    /**
     * Asserts that the given object is not null
     * @param self $obj the object to check for null
     * @throws ObjectNotFoundException if the object is null
     * @return $this
     */
    public static function NotNull(?self $obj) : self 
    { 
        if ($obj === null) throw new ObjectNotFoundException(static::class); return $obj; 
    }
    
    /**
     * Loads a unique object by its ID
     * @param ObjectDatabase $database Reference to the database
     * @param string $id the ID of the object
     * @return self|null object or null if not found
     */
    public static function TryLoadByID(ObjectDatabase $database, string $id) : ?self
    {
        return static::TryLoadUniqueByKey($database,'id',$id);
    }
    
    /**
     * Deletes a unique object by its ID
     * @param ObjectDatabase $database Reference to the database
     * @param string $id the ID of the object
     */
    public static function DeleteByID(ObjectDatabase $database, string $id) : void
    {
        if (!static::TryDeleteByUniqueKey($database,'id',$id))
            throw new ObjectNotFoundException();
    }
    
    /**
     * Loads all objects of this type from the database
     * @param ObjectDatabase $database Reference to the database
     * @param int $limit the maximum number of objects to load
     * @param int $offset the number of objects to skip loading 
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array 
    {
        return static::LoadByQuery($database, (new QueryBuilder())->Limit($limit)->Offset($offset));
    }
    
    /**
     * Deletes all objects of this type from the database
     * @param ObjectDatabase $database Reference to the database
     * @return int number of deleted objects
     */
    public static function DeleteAll(ObjectDatabase $database) : int
    {
        return static::DeleteByQuery($database, new QueryBuilder());
    }
    
    /**
     * Loads objects from the database with the given object ID as the value of the given field
     * 
     * Can be used as an alternative to LoadByObject() to avoid actually loading the object
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param string $id The ID of the object referenced
     * @param string $class optionally, the class to match if this column is polymorphic
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    public static function LoadByObjectID(ObjectDatabase $database, string $field, string $id, ?string $class = null) : array
    {
        $v = $class ? FieldTypes\ObjectPoly::GetIDTypeDBValue($id, $class) : $id;
        $q = new QueryBuilder(); return static::LoadByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    /**
     * Deletes objects from the database with the given object ID as the value of the given field
     *
     * Can be used as an alternative to DeleteByObject() to avoid actually loading the object
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param string $id The ID of the object referenced
     * @param string $class optionally, the class to match if this column is polymorphic
     * @return int number of rows deleted
     */
    public static function DeleteByObjectID(ObjectDatabase $database, string $field, string $id, ?string $class = null) : int
    {
        $v = $class ? FieldTypes\ObjectPoly::GetIDTypeDBValue($id, $class) : $id;
        $q = new QueryBuilder(); return static::DeleteByQuery($database, $q->Where($q->Equals($field, $v)));
    }
        
    /**
     * Loads a unique object matching the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field the name of the field to check
     * @param string $key the value of the field that uniquely identifies the object
     * @return self|NULL
     */
    protected static function TryLoadUniqueByKey(ObjectDatabase $database, string $field, string $key) : ?self
    {
        return $database->TryLoadObjectByUniqueKey(static::class, $field, $key);
    }
    
    /**
     * Deletes a unique object matching the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field the name of the field to check
     * @param string $key the value of the field that uniquely identifies the object
     * @return bool true if an object was deleted
     */
    protected static function TryDeleteByUniqueKey(ObjectDatabase $database, string $field, string $key) : bool
    {
        $q = new QueryBuilder(); $rows = static::DeleteByQuery($database, $q->Where($q->Equals($field, $key)));
        
        if ($rows > 1) throw new DuplicateUniqueKeyException(); return (bool)$rows;
    }
    
    /**
     * Loads objects from the database with the given object referenced by the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @param bool $isPoly whether or not this field is polymorphic
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    public static function LoadByObject(ObjectDatabase $database, string $field, self $object, bool $isPoly = false) : array
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        $q = new QueryBuilder(); return static::LoadByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    /**
     * Deletes objects from the database with the given object referenced by the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @param bool $isPoly whether or not this field is polymorphic
     * @return int number of deleted objects
     */
    public static function DeleteByObject(ObjectDatabase $database, string $field, self $object, bool $isPoly = false) : int
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        $q = new QueryBuilder(); return static::DeleteByQuery($database, $q->Where($q->Equals($field, $v)));
    }
    
    /**
     * Loads a unique object from the database with the given object referenced by the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @param bool $isPoly whether or not this field is polymorphic
     * @return self|null
     */
    public static function TryLoadUniqueByObject(ObjectDatabase $database, string $field, self $object, bool $isPoly = false) : ?self
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        return static::TryLoadUniqueByKey($database, $field, $v);
    }
    
    /**
     * Deletes a unique object from the database with the given object referenced by the given field
     * @param ObjectDatabase $database Reference to the database
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @param bool $isPoly whether or not this field is polymorphic
     * @return bool true if an object was deleted
     */
    public static function TryDeleteByUniqueObject(ObjectDatabase $database, string $field, self $object, bool $isPoly = false) : bool
    {
        $v = $isPoly ? FieldTypes\ObjectPoly::GetObjectDBValue($object) : $object->ID();
        $rows = static::TryDeleteByUniqueKey($database, $field, $v);
        
        if ($rows > 1) throw new DuplicateUniqueKeyException(); return (bool)$rows;
    }
    
    /** Returns the unique ID of the object */
    public function ID() : string { return $this->scalars['id']->GetValue(); }
    
    /** 
     * Returns an array of the object's ID and its class name 
     * @return array<string, string> ID => class name
     */
    public function getIDType() : array { return array($this->ID() => Utilities::ShortClassName(static::class)); }
    public function __toString() : string { return $this->ID().' => '.Utilities::ShortClassName(static::class); }
    
    /** 
     * Returns the given object's getIDType() if not null, else null 
     * @return ?string[] [string, string]
     */
    public static function toIDType(?self $obj) : ?array { return $obj ? $obj->getIDType() : null; }    
    
    /** @var array<string, FieldTypes\Scalar> array of scalar properties indexed by their field names */
    protected array $scalars = array();
    
    /** @var array<string, FieldTypes\ObjectRef> array of properties, indexed by their field names, that reference another object */
    protected array $objects = array();
    
    /** @var array<string, FieldTypes\ObjectRefs> array of properties, indexed by their field names, that reference a collection of objects */
    protected array $objectrefs = array();

    /**
     * Gets a scalar field
     * @param string $field the field name of the scalar
     * @param bool $allowTemp whether to allow returning a value that was set as temporary
     * @throws KeyNotFoundException if the field name is invalid
     * @throws NullValueException if the field value is null
     * @return mixed any non-null scalar value
     */
    protected function GetScalar(string $field, bool $allowTemp = true)
    {
        if (!array_key_exists($field, $this->scalars)) throw new KeyNotFoundException($field);
        
        $value = $this->scalars[$field]->GetValue($allowTemp);
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    /** 
     * Same as GetScalar() but returns null instead of throwing exceptions 
     * @see BaseObject::GetScalar()
     */
    protected function TryGetScalar(string $field, bool $allowTemp = true)
    {
        if (!array_key_exists($field, $this->scalars)) throw new KeyNotFoundException($field);
        
        return $this->scalars[$field]->GetValue($allowTemp);
    }
    
    /**
     * Returns the delta of the given scalar (non-zero if modified)
     * @param string $field the field name of the scalar
     * @throws KeyNotFoundException if the field name is invalid
     * @return int # of times modified for scalars, delta for counters
     */
    protected function GetScalarDelta(string $field) : int
    {
        if (!array_key_exists($field, $this->scalars)) throw new KeyNotFoundException($field);
        
        return $this->scalars[$field]->GetDelta();
    }
    
    /**
     * Gets a single object reference
     * @param string $field the field name holding the reference
     * @throws KeyNotFoundException if the field name is invalid
     * @throws NullValueException if the field value is null
     * @return self any object value
     */
    protected function GetObject(string $field) : self
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        $value = $this->objects[$field]->GetObject();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    /** 
     * Same as GetObject() but returns null instead of throwing exceptions 
     * @see BaseObject::GetObject()
     */
    protected function TryGetObject(string $field) : ?self
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        return $this->objects[$field]->GetObject();
    }
    
    /**
     * Checks if the object reference is not-null without actually loading it (faster)
     * @param string $field the field name holding the reference
     * @throws KeyNotFoundException if the field name is invalid
     * @return bool true if the object reference is not null
     */
    protected function HasObject(string $field) : bool
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        return boolval($this->objects[$field]->GetValue());
    }
    
    /**
     * Gets the ID of a referenced object without actually loading it (faster)
     * @param string $field the field name holding the reference
     * @throws KeyNotFoundException if the field name is invalid
     * @throws NullValueException if the field value is null
     * @return string the ID of the referenced object
     */
    protected function GetObjectID(string $field) : string
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        $value = $this->objects[$field]->GetValue();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    /** 
     * Same as GetObjectID() but returns null instead of throwing exceptions
     * @see BaseObject::GetObjectID()
     */
    protected function TryGetObjectID(string $field) : ?string
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        return $this->objects[$field]->GetValue();
    }
    
    /**
     * Gets the class name of a referenced object without actually loading it (faster)
     * @param string $field the field name holding the reference
     * @throws KeyNotFoundException if the field name is invalid
     * @return string|NULL the class name of the referenced object
     */
    protected function GetObjectType(string $field) : string
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        $value = $this->objects[$field]->GetRefClass();
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    /**
     * Gets the class name of a referenced object without actually loading it (faster)
     * @param string $field the field name holding the reference
     * @throws KeyNotFoundException if the field name is invalid
     * @return string|NULL the class name of the referenced object
     */
    protected function TryGetObjectType(string $field) : ?string
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        return $this->objects[$field]->GetRefClass();
    }
    
    /**
     * Deletes the object referenced in the field
     * @param string $field the field name holding the reference
     * @return $this
     */
    protected function DeleteObject(string $field) : self
    {
        if (!array_key_exists($field, $this->objects)) throw new KeyNotFoundException($field);
        
        $this->objects[$field]->DeleteObject(); return $this;
    }
    
    /**
     * Gets an array of objects that reference this object
     * @param string $field the field name of the collection
     * @param int $limit the maximum number of objects to load
     * @param int $offset the number of objects to skip loading
     * @throws KeyNotFoundException if the field name is invalid
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    protected function GetObjectRefs(string $field, ?int $limit = null, ?int $offset = null) : array
    {
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        return $this->objectrefs[$field]->GetObjects($limit, $offset);
    }
    
    /**
     * Gets the counter of objects referencing this object
     * @param string $field the field name of the collection
     * @throws KeyNotFoundException if the field name is invalid
     * @return int the number of objects
     */
    protected function CountObjectRefs(string $field) : int
    {
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        return $this->objectrefs[$field]->GetValue() ?? 0;
    }
    
    /**
     * Loads the object that joins together two classes using a FieldTypes\ObjectJoin
     * @param string $field the field name using the join reference
     * @param BaseObject $obj the object that is joined together with this one
     * @throws KeyNotFoundException if the field name is invalid
     * @throws NullValueException if the given object is not joined to us
     * @return StandardObject the join object that connects us to $obj
     */
    protected function GetJoinObject(string $field, self $obj) : StandardObject
    {
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        $value = $this->objectrefs[$field]->GetJoinObject($obj);
        if ($value !== null) return $value; else throw new NullValueException($field);
    }
    
    /** 
     * Same as GetJoinObject() but returns null instead of throwing exceptions 
     * @see BaseObject::GetJoinObject()
     */
    protected function TryGetJoinObject(string $field, self $obj) : ?StandardObject
    {
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        return $this->objectrefs[$field]->GetJoinObject($obj);
    }
    
    /**
     * Deletes all objects that reference this object
     * @param string $field the field name of the collection
     * @return $this
     */
    protected function DeleteObjects(string $field) : self
    {
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        $this->objectrefs[$field]->DeleteObjects(); return $this;
    }
    
    /**
     * Sets a scalar field to the given value
     * @param string $field the name of the field
     * @param mixed $value the value of the scalar to set
     * @param bool $temp if true, the value is temporary and will not be saved
     * @throws KeyNotFoundException if the property name is invalid
     * @return $this
     */
    protected function SetScalar(string $field, $value, bool $temp = false) : self
    {    
        if (!$temp && $this->database->isReadOnly()) throw new DatabaseReadOnlyException();
        
        if (!array_key_exists($field, $this->scalars))
        {
            if ($value === null) return $this;
            else throw new KeyNotFoundException($field);
        }
        
        $this->modified |= $this->scalars[$field]->SetValue($value, $temp);
        
        return $this;
    } 

    /** 
     * Increments a counter field by the given delta (thread safe)
     * @param string $field the name of the counter field
     * @param int $delta the value to increment by
     * @throws KeyNotFoundException if the property name is invalid
     * @throws NotCounterException if the field is not a counter
     * @return $this
     */
    protected function DeltaCounter(string $field, int $delta) : self
    {
        if ($this->database->isReadOnly()) throw new DatabaseReadOnlyException();
        
        if ($delta === 0) return $this;
        
        if (!array_key_exists($field, $this->scalars)) throw new KeyNotFoundException($field);
        
        if ($this->scalars[$field]->GetOperatorType() !== FieldTypes\OPERATOR_INCREMENT)
            throw new NotCounterException($field);
        
        $this->modified |= $this->scalars[$field]->Delta($delta);
        
        return $this;
    }

    /**
     * Sets a field to reference the given object
     * 
     * Will also call SetObject or AddObjectRef on the given object as appropriate for two-way references
     * @param string $field the name of the reference field
     * @param BaseObject $object the object for the field to reference
     * @param bool $notification true if this is a notification from another object that cross-references this one (internal only!)
     * @throws KeyNotFoundException if the property name is invalid
     * @return bool true if this object was modified
     */
    protected function BoolSetObject(string $field, ?self $object, bool $notification = false) : bool
    {
        if ($this->database->isReadOnly()) throw new DatabaseReadOnlyException();

        if (!array_key_exists($field, $this->objects)) 
        {
            if ($object === null) return $this;
            else throw new KeyNotFoundException($field);
        }
        
        $fieldobj = $this->objects[$field];

        if (!$notification)
        {            
            if (($reffield = $fieldobj->GetRefField()) !== null) 
                $oldref = $fieldobj->GetObject();
            
            $modified = $fieldobj->SetObject($object);
            
            if ($modified && $reffield !== null)
            {
                $usemany = $fieldobj->GetRefIsMany();
                
                if ($oldref !== null)
                {
                    if ($usemany)
                         $oldref->RemoveObjectRef($reffield, $this, true);
                    else $oldref->SetObject($reffield, null, true);
                    
                    if ($fieldobj->isAutoDelete()) $oldref->Delete();
                }
                
                if ($object !== null)
                {
                    if ($usemany)
                         $object->AddObjectRef($reffield, $this, true);
                    else $object->SetObject($reffield, $this, true);
                }
            }
        } 
        else $modified = $fieldobj->SetObject($object);

        $this->modified |= $modified; return $modified;
    } 

    /**
     * Same as BoolSetObject() but returns $this
     * @see BaseObject::BoolSetObject()
     */
    protected function SetObject(string $field, ?self $object, bool $notification = false) : self
    {
        $this->BoolSetObject($field, $object, $notification); return $this;
    }

    /**
     * Adds the given object to a collection of referenced objects
     * 
     * Will also call SetObject on the given object to actually create the reference
     * @param string $field the name of the field of the collection
     * @param BaseObject $object the object to add to the collection
     * @param bool $notification true if this is a notification from another object that cross-references this one (internal only!)
     * @throws KeyNotFoundException if the property name is invalid
     * @return bool true if this object was modified
     */
    protected function AddObjectRef(string $field, self $object, bool $notification = false) : bool
    {
        if ($this->database->isReadOnly()) throw new DatabaseReadOnlyException();        
        
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        $fieldobj = $this->objectrefs[$field];
        
        if ($fieldobj->GetIsRefsMany())
        {
            $modified = $fieldobj->AddObject($object, $notification);
        }
        else
        {
            $update = $notification || $object->BoolSetObject($fieldobj->GetRefField(), $this, true);
            
            $modified = $update ? $fieldobj->AddObject($object, $notification) : false;
        }
        
        $this->modified |= $modified; return $modified;
    }
    
    /**
     * Removes the given object from a collection of referenced objects
     * @param string $field the name of the field of the collection
     * @param BaseObject $object the object to add to the collection
     * @param bool $notification true if this is a notification from another object that cross-references this one (internal only!)
     * @throws KeyNotFoundException if the property name is invalid
     * @return bool true if this object was modified
     */
    protected function RemoveObjectRef(string $field, self $object, bool $notification = false) : bool
    {
        if ($this->database->isReadOnly()) throw new DatabaseReadOnlyException();
        
        if (!array_key_exists($field, $this->objectrefs)) throw new KeyNotFoundException($field);
        
        $fieldobj = $this->objectrefs[$field];
            
        if ($fieldobj->GetIsRefsMany())
        {
            $modified = $fieldobj->RemoveObject($object, $notification);
        }
        else
        {
            $update = $notification || $object->BoolSetObject($fieldobj->GetRefField(), null, true);
            
            $modified = $update ? $fieldobj->RemoveObject($object, $notification) : false;
        }
        
        if (!$notification && $fieldobj->isAutoDelete()) $object->Delete();
        
        $this->modified |= $modified; return $modified;
    }
    
    /**
     * Constructs the object by initializing its field template with values from the database
     * @param ObjectDatabase $database Reference to the database
     * @param array<string, string> $data array of columns from the DB in the form of name=>value
     */
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
    
    /** Adds the given field object to the correct internal array */
    private function AddField(string $key, $field)
    {
        $key = $field->GetMyField();
        switch ($field->GetReturnType())
        {
            case FieldTypes\RETURN_SCALAR: $this->scalars[$key] = $field; break;
            case FieldTypes\RETURN_OBJECT: $this->objects[$key] = $field; break;
            case FieldTypes\RETURN_OBJECTS: $this->objectrefs[$key] = $field; break;
        }
    }
    
    /** Function to allow subclasses to do something after being constructed without overriding the constructor */
    protected function SubConstruct() : void { }
    
    /** Function to allow subclasses to do something before the object is saved to DB */
    protected function SubSave() : void { }

    /** 
     * Collects fields that have changed and saves them to the database
     * @param bool $onlyMandatory true if only required fields should be saved
     * @return $this
     */
    public function Save(bool $onlyMandatory = false) : self
    {
        if ($this->deleted || ($onlyMandatory && $this->created)) return $this; 
        
        if (!$onlyMandatory) $this->SubSave(); 
        
        if ($this->deleted || !$this->modified) return $this;
        
        $values = array(); $counters = array();

        foreach (array_merge($this->scalars, $this->objects, $this->objectrefs) as $key=>$field)
        {
            if (!$field->GetDelta() || ($onlyMandatory && !$field->isMandatorySave())) continue;

            if ($field->GetOperatorType() === FieldTypes\OPERATOR_INCREMENT)
                $counters[$key] = $field->GetDBValue();
            else $values[$key] = $field->GetDBValue();
            
            $field->ResetDelta();
        }
        
        $this->database->SaveObject($this, $values, $counters);
        
        $this->created = false; return $this;
    } 
    
    /** whether or not this object has been modified */
    protected bool $modified = false;
    
    /** whether or not this object has been deleted */
    protected bool $deleted = false; 
    
    /** 
     * whether or not this object has been, or should be considered, deleted
     * 
     * This function can be overriden with a custom validity-check, and is used as a filter when loading objects 
     */
    public function isDeleted() : bool { return $this->deleted; }
    
    /** whether or not this object has been deleted by DB */
    protected bool $dbDeleted = false;
    
    /** Deletes this object without sending to the DB */
    public function NotifyDBDeleted() : void { $this->dbDeleted = true; $this->Delete(); }
    
    /** Deletes this object from the DB */
    public function Delete() : void
    {
        foreach ($this->objects as $field=>$ref)
        {            
            if ($ref->GetValue()) $this->SetObject($field, null);
        }
        
        foreach ($this->objectrefs as $field=>$refs)
        {
            if (!$refs->GetValue()) continue;
            
            foreach ($refs->GetObjects() as $object) 
            {
                $this->RemoveObjectRef($field, $object);
            }
        }
        
        if (!$this->deleted && !$this->dbDeleted) 
            $this->database->DeleteObject($this); 
        
        $this->deleted = true;
    }

    /** True if this object has been created and not yet saved to DB */
    protected bool $created = false; 
    
    /** True if this object has been created and not yet saved to DB (should not be overriden) */
    public function isCreated() : bool { return $this->created; }
    
    /** Creates a new object of this type in the database and returns it */
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        $obj = $database->CreateObject(static::class); 
        
        $obj->modified = true; $obj->created = true;
        
        foreach ($obj->objectrefs as $refs) $refs->InitValue(0);
        
        return $obj;
    }
}
