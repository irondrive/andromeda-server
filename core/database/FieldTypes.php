<?php namespace Andromeda\Core\Database\FieldTypes; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, ObjectTypeException};
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/JoinObject.php"); use Andromeda\Core\Database\JoinObject;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

const OPERATOR_SETEQUAL = 0; 
const OPERATOR_INCREMENT = 1;

const RETURN_SCALAR = 0; 
const RETURN_OBJECT = 1; 
const RETURN_OBJECTS = 2;

/**
 * Represents a basic scalar value stored in the column of a database table
 * 
 * This class is the starting point from which all other fieldtypes must inherit.
 */
class Scalar
{
    /** The name of this field */
    protected string $myfield; 
    
    /** 
     * The possibly-temporary-only value of this field 
     * 
     * Intended to be used e.g. when a field needs to be decrypted
     */
    protected $tempvalue;
    
    /** The actual value of this field that will exist in the DB */
    protected $realvalue; 
    
    /** Count of how many times this field has been modified */
    protected int $delta = 0; 
    
    /** If true, this field should always be saved even in a rollback */
    protected bool $alwaysSave = false;
    
    /** Reference to the database */
    protected ObjectDatabase $database;
    
    /** Reference to this field's parent object */
    protected BaseObject $parent;
    
    /** Returns OPERATOR_SETEQUAL as the operator used to update this field a DB query */
    public static function GetOperatorType(){ return OPERATOR_SETEQUAL; }
    
    /** Returns RETURN_SCALAR as the basic type of value stored in this field */
    public static function GetReturnType(){ return RETURN_SCALAR; }
    
    /**
     * Declares a new scalar fieldtype (use this in object templates)
     * @param mixed $defvalue the default value of the field type
     * @see Scalar::$alwaysSave
     */
    public function __construct($defvalue = null, bool $alwaysSave = false)
    {
        $this->alwaysSave = $alwaysSave;
        $this->tempvalue = $defvalue;
        $this->realvalue = $defvalue;
        $this->delta = ($defvalue !== null);
    }

    /**
     * Initializes this field by tying it to an actual object
     * @see Scalar::$database
     * @see Scalar::$parent
     * @see Scalar::$myfield
     */
    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield) : void
    {
        $this->database = $database; 
        $this->parent = $parent; 
        $this->myfield = $myfield;
    }
    
    /** Gives the field its value loaded from the database */
    public function InitValue(?string $value) : void
    {
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
    }
    
    /** @see Scalar::$myfield */
    public function GetMyField() : string { return $this->myfield; }
    
    /** @see Scalar::$alwaysSave */
    public function GetAlwaysSave() : bool { return $this->alwaysSave; }
    
    /** @see Scalar::$parent */
    public function GetParent() : BaseObject { return $this->parent; }
    
    /**
     * Gets the actual (possibly unserialized) value of this field
     * @param bool $allowTemp if false, force getting the real (non-temporary) value
     */
    public function GetValue(bool $allowTemp = true) { return $allowTemp ? $this->tempvalue : $this->realvalue; }

    /** @see Scalar::$delta */
    public function GetDelta() : int { return $this->delta; }
    
    /** Resets the delta of this field to 0 */
    public function ResetDelta() : self { $this->delta = 0; return $this; }

    /** Gets the serialized value of this field that will exist in the DB */
    public function GetDBValue() 
    { 
        if (is_bool($this->realvalue)) 
            return intval($this->realvalue);
        
        return $this->realvalue; 
    }
    
    /**
     * @param mixed $value the value to set for this field
     * @param bool $temp if true, the value is temporary only
     * @return bool if false, the value was not modified
     */
    public function SetValue($value, bool $temp = false) : bool
    {
        $this->tempvalue = $value;

        // only update the realvalue if the value has changed - use loose comparison (!=)
        // so strings/numbers match, but we don't want 0/false to == null (which they do)
        $nulls = (($value === null) xor ($this->realvalue === null)); 
        if (!$temp && ($value != $this->realvalue || $nulls))
        {
            $this->realvalue = $value; $this->delta++; return true;
        }
        
        return false;
    }
    
    /** Uses sodium to securely zero the value of this field */
    public function EraseValue() : void
    {
        if (function_exists('sodium_memzero'))
        {
            if (isset($this->tempvalue)) sodium_memzero($this->tempvalue);
            if (isset($this->realvalue)) sodium_memzero($this->realvalue);
        }
    }            
}

/** Stores a value that represents a thread-safe counter */
class Counter extends Scalar
{
    /** Returns OPERATOR_INCREMENT as the operator used to update this field a DB query */
    public static function GetOperatorType(){ return OPERATOR_INCREMENT; }
    
    /**
     * Constructs a new counter with a default value of zero
     * @see Scalar::$alwaysSave
     */
    public function __construct(bool $alwaysSave = false)
    {
        parent::__construct(0, $alwaysSave);
        $this->ResetDelta(); // using default values sets the delta
    }
    
    /** Gives the counter its value from the DB, or 0 if null */
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value ?? 0);
    }        
    
    /** Increments the counter by the given delta */
    public function Delta(int $delta = 1) : bool 
    { 
        if ($delta === 0) return false;
        $this->tempvalue += $delta; 
        $this->realvalue += $delta; 
        $this->delta += $delta; return true;
    }
        
    /** Returns the counter's delta as the value to be sent to the DB */
    public function GetDBValue() { return $this->delta; }
}

/** Stores a value that is automatically JSON-encoded */
class JSON extends Scalar
{
    /** Initializes the value of this field by decoding the given JSON string */
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value);
        if ($value) $value = Utilities::JSONDecode($value);
        $this->realvalue = $value; $this->tempvalue = $value;
    }
    
    /** Returns this field's value as a JSON string for the DB */
    public function GetDBValue() : string { return Utilities::JSONEncode($this->realvalue); }
}

/** Stores a reference to another BaseObject */
class ObjectRef extends Scalar
{
    /** The object that is referenced */
    protected ?BaseObject $object;
    
    /** The class of the object that is referenced */
    protected string $refclass; 
    
    /** The name of the field in the referenced object that cross-references our parent object */
    protected ?string $reffield; 
    
    /** if true and reffield is null, the referenced object's reffield is an array of objects rather than a single reference */
    protected bool $refmany;
    
    /** Returns RETURN_OBJECT as the basic type of value stored in this field */
    public static function GetReturnType(){ return RETURN_OBJECT; }

    /**
     * Creates a new object reference field
     * @see ObjectRef::$refclass
     * @see ObjectRef::$reffield
     * @see ObjectRef::$refmany
     */
    public function __construct(string $refclass, ?string $reffield = null, bool $refmany = true)
    {
        $this->refclass = $refclass; $this->reffield = $reffield; $this->refmany = $refmany;
    }
    
    /** Returns the base class that the referenced object must be */
    public function GetBaseClass() : string { return $this->refclass; }
    
    /** @see ObjectRef::$refclass */
    public function GetRefClass() : string { return $this->refclass; }
    
    /** @see ObjectRef::$reffield */
    public function GetRefField() : ?string { return $this->reffield; }
    
    /** @see ObjectRef::$refmany */
    public function GetRefIsMany() : bool { return $this->refmany; }

    /** Returns the object referenced by this field, possibly loading it from the DB */
    public function GetObject() : ?BaseObject
    {
        $id = $this->GetValue(); if ($id === null) return null;
        
        if (!isset($this->object)) $this->object = $this->GetRefClass()::TryLoadByID($this->database, $id);
        
        return $this->object;
    }
    
    /** Sets the value of this field to reference the given object */
    public function SetObject(?BaseObject $object) : bool
    { 
        if (isset($this->object) && $object === $this->object) return false;
        
        if ($object !== null && !is_a($object, $this->GetBaseClass())) 
            throw new ObjectTypeException();

        $this->SetValue( ($object !== null) ? $object->ID() : null );
        
        $this->object = $object; $this->delta++; return true;
    }
    
    /** Deletes the object referenced by this field */
    public function DeleteObject() : void
    {
        $id = $this->GetValue(); if ($id === null) return;        
        $this->GetRefClass()::DeleteByID($this->database, $id);
        if (isset($this->object) && $this->object) $this->object->Delete();
    }
}

/** Represents a reference to a polymorphic object that implements a base class */
class ObjectPoly extends ObjectRef
{
    /** The base class that the referenced object must inherit */
    protected string $baseclass;
    
    /** Returns the given class name minus the first namespace */
    private static function ShortClass(string $class) : string
    {
        return implode('\\',array_slice(explode('\\', $class),1)); 
    }
    
    /**
     * Creates a new object reference field
     * @see ObjectPoly::$baseclass
     * @see ObjectRef::$reffield
     * @see ObjectRef::$refmany
     */
    public function __construct(string $baseclass, ?string $reffield = null, bool $refmany = true)
    {
        $this->baseclass = $baseclass; $this->reffield = $reffield; $this->refmany = $refmany;
    }
    
    /** Initializes the poly reference using the DB string with the reference's ID and class */
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value);
        if ($value === null) return;
        
        $value = explode(':',$value);
        parent::InitValue($value[0]);
        $this->refclass = "Andromeda\\".$value[1];
    }
    
    /** @see ObjectPoly::$baseclass */
    public function GetBaseClass() : string { return $this->baseclass; }
    
    /**
     * Returns the serialized database value of the given object ID and type
     * 
     * Exposed for use in building QueryBuilder WHERE statements with poly objects
     */
    public static function GetIDTypeDBValue(string $id, string $type) : string 
    { 
        return $id.':'.static::ShortClass($type); 
    }
    
    /**
     * Returns the serialized database value of the given object
     * 
     * Exposed for use in building QueryBuilder WHERE statements with poly objects
     */
    public static function GetObjectDBValue(?BaseObject $obj) : ?string
    {
        return ($obj === null) ? null : static::GetIDTypeDBValue($obj->ID(), get_class($obj));
    }
    
    /**
     * Poly objects are serialized using their ID and class strings
     * @see Scalar::GetDBValue()
     */
    public function GetDBValue() : ?string 
    { 
        if ($this->GetValue() === null) return null; 
        
        return static::GetIDTypeDBValue($this->GetValue(), $this->refclass); 
    }
    
    /**
     * Also updates the class name of the object
     * @see ObjectRef::SetObject()
     */
    public function SetObject(?BaseObject $object) : bool
    {
        if (!parent::SetObject($object)) return false;
        
        $this->refclass = ($object === null) ? null : get_class($object);
        
        return true;
    }
}

const REFSTYPE_SINGLE = 0; const REFSTYPE_MANY = 1;

/** 
 * Represents a collection of (non-polymorphic) objects that reference this one.
 * 
 * Essentially is a syntactic-sugar/caching field that allows loading the array 
 * of objects without having to call into their class to load ones that reference us.  
 * The practical value is that the field stores a reference counter and acts as a cache.
 */
class ObjectRefs extends Counter
{
    /**
     * array of objects that reference this field 
     * @var array<string, BaseObject>
     */
    protected array $objects; 
    
    /** true if the objects array is fully loaded */
    protected bool $isLoaded = false;
    
    /** The class of the object that is referenced */
    protected string $refclass;
    
    /** The name of the field in the referenced object that references our parent object */
    protected ?string $reffield; 
    
    /** True if our object is referenced as a polymorphic field */
    protected bool $parentPoly;
    
    /** @var BaseObject[] array of references that have been added */
    protected array $refs_added = array();
    
    /** @var BaseObject[] array of references that have been deleted */
    protected array $refs_deleted = array();
    
    /** Returns RETURN_OBJECTS as the basic type of value stored in this field */
    public static function GetReturnType(){ return RETURN_OBJECTS; }
    
    /** Returns REFSTYPE_SINGLE as the type of reference the referenced objects have to us */
    public static function GetRefsType(){ return REFSTYPE_SINGLE; }
    
    /**
     * Creates a new object reference array field
     * @see ObjectRefs::$refclass
     * @see ObjectRefs::$reffield
     * @see ObjectRefs::$parentPoly
     */
    public function __construct(string $refclass, ?string $reffield = null, bool $parentPoly = false)
    {
        $this->refclass = $refclass; $this->reffield = $reffield; $this->parentPoly = $parentPoly;
    }
    
    /** @see ObjectRefs::$refclass */
    public function GetRefClass() : string { return $this->refclass; }
    
    /** @see ObjectRefs::$reffield */
    public function GetRefField() : string { return $this->reffield; }

    /**
     * Load the array of objects referencing this field
     * 
     * If offset or limit are not null, the array will not be fully loaded and will need to be queried again
     * @param int $limit max number of objects to load
     * @param int $offset number of objects to skip loading
     * @return array<string, BaseObject> objects indexed by their ID
     */
    public function GetObjects(?int $limit = null, ?int $offset = null) : array
    {
        if (!$this->isLoaded) { $this->LoadObjects($limit, $offset); return $this->objects; }
        
        else return array_slice($this->objects, $offset??0, $limit);
    }
 
    /** Populate the objects array, merging with changes */
    protected function LoadObjects(?int $limit = null, ?int $offset = null) : void
    {
        $this->objects = array();
        if ($limit !== null && $limit <= 0) return;
        if ($offset !== null && $offset <= 0) $offset = 0;

        $this->InnerLoadObjects($limit, $offset);
        $this->isLoaded = ($limit === null && $offset === null);
        
        if ($limit === null || count($this->objects) < $limit)
        {
            if ($offset !== null) $offset = max(0, $offset-count($this->objects));
            
            $this->MergeWithObjectChanges();
            
            if ($limit || $offset) $this->objects = array_slice($this->objects, $offset, $limit);            
        }        
    }
    
    /** Perform the inner/core object array loading query using a WHERE references us on the target class */
    protected function InnerLoadObjects(?int $limit = null, ?int $offset = null) : void
    {
        $myval = $this->parentPoly ? ObjectPoly::GetObjectDBValue($this->parent) : $this->parent->ID();
        $q = new QueryBuilder(); $q->Where($q->Equals($this->reffield, $myval));
        $this->objects = $this->refclass::LoadByQuery($this->database, $q->Limit($limit)->Offset($offset));
    }
    
    /** Merge changed references with the object array from the DB */
    protected function MergeWithObjectChanges() : void
    {
        foreach ($this->refs_added as $object) $this->objects[$object->ID()] = $object;
        foreach ($this->refs_deleted as $object) unset($this->objects[$object->ID()]);
    }
    
    /** Deletes all objects that reference this field in a single query */
    public function DeleteObjects() : void
    {
        if (!$this->GetValue()) return;
        
        $this->GetRefClass()::DeleteByObject($this->database, $this->reffield, $this->parent, $this->parentPoly);
        
        foreach ($this->refs_added as $obj) $obj->Delete(); 
        
        $this->isLoaded = true; $this->objects = array();
    }
    
    /**
     * Add the given object to this field's object array
     * @param BaseObject $object the object to add
     * @param bool $notification if true, this is a notification from another object that references us
     * @return bool true if this field was modified
     */
    public function AddObject(BaseObject $object, bool $notification) : bool
    {
        $modified = false;
        
        if (($idx = array_search($object, $this->refs_deleted, true)) !== false)
        {
            unset($this->refs_deleted[$idx]);
            parent::Delta(1); $modified = true;
        }

        if (!in_array($object, $this->refs_added, true))
        {
            array_push($this->refs_added, $object); 
            parent::Delta(); $modified = true;
        }
                
        if (isset($this->objects))
            $this->objects[$object->ID()] = $object; 

        return $modified;
    }
    
    /**
     * Deletes the given object from this field's object array
     * @param BaseObject $object the object to remove
     * @param bool $notification if true, this is a notification from another object that references us
     * @return bool true if this field was modified
     */
    public function RemoveObject(BaseObject $object, bool $notification) : bool
    {
        $modified = false;

        if (($idx = array_search($object, $this->refs_added, true)) !== false)
        {
            unset($this->refs_added[$idx]);
            parent::Delta(-1); $modified = true;
        }
        
        if (!in_array($object, $this->refs_deleted, true))
        {
            array_push($this->refs_deleted, $object); 
            parent::Delta(-1); $modified = true;
        }
        
        if (isset($this->objects))
            unset($this->objects[$object->ID()]);

        return $modified;
    }
}

/**
 * A field that represents a many-to-many relationship with another object.
 * 
 * Like ObjectRefs this field type is mainly syntactic sugar but has the added
 * benefit of automatically managing the sub-objects that join the two classes together.
 * Example - joining together Accounts and Groups requires a GroupMembership join object.
 * The class is basically an ObjectRefs to a collection of join objects, but that uses
 * an SQL JOIN query to load the actual joined class in LoadObjects()
 */
class ObjectJoin extends ObjectRefs
{
    /** The name of the class that we are joined to */
    protected string $joinclass;
    
    /** Returns REFSTYPE_MANY as the type of reference the referenced objects have to us */
    public static function GetRefsType(){ return REFSTYPE_MANY; }
    
    /** @see ObjectJoin::$joinclass */
    public function GetJoinClass() : string { return $this->joinclass; }
    
    /**
     * Construct a new object join field
     * 
     * The join objects that join us to the joined class must reference the destination
     * using the same field name that our class uses to reference the join objects.
     * The join objects must reference objects using their IDs.
     * @example Account groups -> GroupMembership accounts, GroupMembership groups <- Group id
     * @example Group accounts -> GroupMembership groups, GroupMembership accounts <- Account id
     * @see ObjectRefs::$refclass
     * @see ObjectJoin::$joinclass
     * @see ObjectRefs::$reffield
     */
    public function __construct(string $refclass, string $joinclass, string $reffield)
    {
        parent::__construct($refclass, $reffield);    
        $this->joinclass = $joinclass;
    }
    
    /** Perform the inner/core object array loading query using WHERE the join class references us and JOIN the target class */
    protected function InnerLoadObjects(?int $limit = null, ?int $offset = null) : void
    {
        $q = new QueryBuilder(); $key = $this->database->GetClassTableName($this->joinclass).'.'.$this->reffield;

        $q->Where($q->Equals($key, $this->parent->ID()))
            ->Join($this->database, $this->joinclass, $this->myfield, $this->refclass, 'id');
        
        $this->objects = $this->refclass::LoadByQuery($this->database, $q->Limit($limit)->Offset($offset));
    }
    
    /** Return the actual join object used to join us to the given object */
    public function GetJoinObject(BaseObject $joinobj) : ?JoinObject
    {
        return ($this->joinclass)::TryLoadJoin($this->database, $this, $joinobj);
    }
    
    /**
     * Also creates a new join object joining us to the given object
     * @see ObjectRefs::AddObject()
     */
    public function AddObject(BaseObject $object, bool $notification) : bool
    {
        if ($notification) return parent::AddObject($object, $notification);
        
        ($this->joinclass)::CreateJoin($this->database, $this, $object); return false;
    }
    
    /**
     * Also deletes the join object joining us to the given object
     * @see ObjectRefs::RemoveObject()
     */
    public function RemoveObject(BaseObject $object, bool $notification) : bool
    {
        if ($notification) return parent::RemoveObject($object, $notification);
        
        $joinobj = ($this->joinclass)::TryLoadJoin($this->database, $this, $object);
        
        if ($joinobj !== null) $joinobj->Delete();
        
        return false;
    }
}
