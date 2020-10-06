<?php namespace Andromeda\Core\Database\Fields; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\JoinObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
use Andromeda\Core\Database\ObjectTypeException;

class SpecialColumnException extends Exceptions\ServerException { public $message = "DB_INVALID_SPEICAL_COLUMN"; }
class TempAlreadyModifiedException extends Exceptions\ServerException { public $message = "SET_TEMP_ALREADY_MODIFIED"; }

abstract class Field
{
    public static function Init(ObjectDatabase $database, BaseObject $parent, string $key, $value) : Field
    {
        if (strpos($key,'*') === false) return new Scalar($value, $key);
        
        else
        {
            $header = explode('*',$key); $key = $header[0]; $special = $header[1];
            
            if      ($special == "json")    return new JSON($value, $key);
            else if ($special == "counter") return new Counter($value, $key);
            
            else if ($special == "object")     return new ObjectPointer($database, $value, $header);
            else if ($special == "objectpoly") return new ObjectPolyPointer($database, $value, $header);
            else if ($special == "objectrefs") return new ObjectRefs($database, $value, $header, $parent);
            else if ($special == "objectjoin") return new ObjectJoin($database, $value, $header, $parent);
            
            else throw new SpecialColumnException("Class ".$parent->GetDBClass()." Column $key");
        }
    }
    
    public abstract function GetDBValue();
    public abstract function GetColumnName() : string;
    
    protected $myfield; public function GetMyField() : string { return $this->myfield; }
    protected $delta = 0; public function GetDelta() : int { return $this->delta; }
    public function ResetDelta() : self { $this->delta = 0; return $this; }
}

class Scalar extends Field
{
    protected $tempvalue; protected $realvalue; protected $delta = 0; const SPECIAL = null;
    
    public function __construct($value, string $myfield) { 
        $this->myfield = $myfield; $this->tempvalue = $value; $this->realvalue = $value; }
    
    public function GetValue() { return $this->tempvalue; }
    public function GetRealValue() { return $this->realvalue; }

    public function GetColumnName() : string
    {
        $header = array($this->myfield, static::SPECIAL);
        return '`'.implode('*',array_filter($header)).'`';
    }
    
    public function GetDBValue() { if ($this->realvalue === false) return 0; else return $this->realvalue; }
    
    public function SetValue($value, bool $temp = false) : bool
    {
        $this->tempvalue = $value;

        if (!$temp && $value !== $this->realvalue)
        {
            $this->realvalue = $value; $this->delta++; return true;
        }
        
        return false;
    }
    
    public function EraseValue() : void
    {
        if (function_exists('sodium_memzero'))
        {
            if (isset($this->tempvalue)) sodium_memzero($this->tempvalue);
            if (isset($this->realvalue)) sodium_memzero($this->realvalue);
        }
    }            
}

class Counter extends Scalar
{
    const SPECIAL = "counter";
    public function Delta(int $delta = 1) : bool 
    { 
        if ($delta === 0) return false;
        $this->tempvalue += $delta; $this->realvalue += $delta; 
        $this->delta += $delta; return true;
    }
        
    public function GetDBValue() { return $this->delta; }
}

class JSON extends Scalar
{
    const SPECIAL = "json";
    
    public function __construct(string $value, string $myfield) 
    { 
        parent::__construct($value, $myfield); 
        $value = Utilities::JSONDecode($value); 
        $this->tempvalue = $value; $this->realvalue = $value;
    }
    
    public function GetDBValue() : string { return Utilities::JSONEncode($this->realvalue); }
}

class ObjectPointer extends Field
{
    protected $database; protected $object; protected $pointer;
    protected $refclass; protected $reffield;      
    
    public function __construct(ObjectDatabase $database, $value, array $header)
    {
        if (count($header) < 3) throw new SpecialColumnException(implode('*',$header));
        
        $this->database = $database; $this->pointer = $value; 
        $this->myfield = $header[0]; $this->refclass = $header[2]; $this->reffield = $header[3] ?? null;
    }
    
    public function GetPointer() : ?string { return $this->pointer; }
    public function GetRefClass() : ?string { return $this->refclass; }
    public function GetRefField() : ?string { return $this->reffield; }
    
    public function GetDBValue() : ?string { return $this->pointer; }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "object", $this->refclass, $this->reffield);
        return '`'.implode('*',array_filter($header)).'`';
    }
    
    public function GetObject() : ?BaseObject
    {
        if ($this->pointer === null) return null;
        
        if (!isset($this->object))
        {
            $class = ObjectDatabase::GetFullClassName($this->refclass);
            
            $this->object = $class::LoadByID($this->database, $this->pointer);
        }
        return $this->object;
    }
    
    public function SetObject(?BaseObject $object) : bool
    { 
        if ($object === $this->object) return false;
        
        $class = ObjectDatabase::GetFullClassName($this->refclass);
        if ($object !== null && !is_a($object, $class)) 
            throw new ObjectTypeException();
        
        if ($object !== null) $this->pointer = $object->ID(); else $this->pointer = null;
        
        $this->object = $object; $this->delta++; return true;
    }
}

class ObjectPolyPointer extends ObjectPointer
{
    public function __construct(ObjectDatabase $database, $value, array $header)
    {        
        $this->database = $database;
        $this->myfield = $header[0]; 
        $this->refbaseclass = $header[2] ?? null;
        $this->reffield = $header[3] ?? null;
        
        if ($value === null) return;
        
        $value = explode('*',$value); $this->pointer = $value[0]; $this->refclass = $value[1];
    }
    
    public function GetDBValue() : ?string 
    { 
        if ($this->pointer === null) return null; 
        else return $this->pointer.'*'.$this->refclass; 
    }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "objectpoly", $this->refbaseclass, $this->reffield);
        
        if ($this->reffield === null) $header = array_filter($header);
        
        return '`'.implode('*', $header).'`';
    }
    
    public function SetObject(?BaseObject $object) : bool
    {
        if (!parent::SetObject($object, $notification)) return false;
        
        $this->refclass = ($object === null) ? null : 
            ObjectDatabase::GetShortClassName(get_class($object));
        
        return true;
    }
}

class ObjectRefs extends Counter
{
    protected $database; protected $objects; 
    protected $myclass; protected $myid;
    protected $refclass; protected $reffield;
    
    protected $refs_added = array(); protected $refs_deleted = array();
    
    public function __construct(ObjectDatabase $database, $value, array $header, BaseObject $parent)
    {
        if (count($header) < 4) throw new SpecialColumnException(implode('*',$header));
        
        $this->database = $database; parent::__construct($value ?? 0, $header[0]);     

        $this->myclass = ObjectDatabase::GetShortClassName(get_class($parent));
        
        $this->myid = $parent->ID(); $this->refclass = $header[2]; $this->reffield = $header[3]; 
    }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "objectrefs", $this->refclass, $this->reffield);
        return '`'.implode('*',array_filter($header)).'`';
    }
        
    public function GetRefClass() : string { return $this->refclass; }
    public function GetRefField() : string { return $this->reffield; }

    public function GetObjects() : array
    {
        if (!isset($this->objects)) $this->LoadObjects();
        return $this->objects;
    }
    
    protected function LoadObjects()
    {
        $class = ObjectDatabase::GetFullClassName($this->refclass); 
        
        $reffield = array($this->reffield, "object", $this->myclass, $this->myfield);
        $reffield = implode('*',array_filter($reffield));

        $criteria = array("`$reffield`" => $this->myid);
        $this->objects = $class::LoadManyMatchingAll($this->database, $criteria);    
        
        $this->MergeWithObjectChanges();
    }
    
    protected function MergeWithObjectChanges()
    {
        foreach ($this->refs_added as $object) $this->objects[$object->ID()] = $object;
        foreach ($this->refs_deleted as $object) unset($this->objects[$object->ID()]);
        $this->refs_added = array(); $this->refs_deleted = array();
    }
    
    public function AddObject(BaseObject $object, bool $notification) : bool
    {
        if (!isset($this->objects))
        {
            if (!in_array($object, $this->refs_added))
            {
                array_push($this->refs_added, $object); 
                parent::Delta(); return true;
            }
        }
        else if (!in_array($object, $this->objects))
        {
            $this->objects[$object->ID()] = $object; 
            parent::Delta(); return true;
        }
        return false;
    }
    
    public function RemoveObject(BaseObject $object, bool $notification) : bool
    {
        if (!isset($this->objects))
        {
            if (!in_array($object, $this->refs_deleted))
            {
                array_push($this->refs_deleted, $object); 
                parent::Delta(-1); return true;
            }
        }
        else if (in_array($object, $this->objects))
        {
            unset($this->objects[$object->ID()]);
            parent::Delta(-1); return true;
        }
        return false;
    }
}

class ObjectJoin extends ObjectRefs
{
    public function __construct(ObjectDatabase $database, $value, array $header, BaseObject $parent)
    {
        $this->parent = $parent; parent::__construct($database, $value, $header, $parent);
    }
    
    protected function LoadObjects()
    {        
        $joinclass = ObjectDatabase::GetFullClassName($this->refclass);
        $joinobject = $this->database->CreateObject($joinclass, true, JoinObject::class);
        $destclass = $joinobject->GetObjectClassName($this->myfield);
        
        $joinfield = array($this->myfield, "object", $destclass, $this->reffield);
        $joinfield = "`".implode('*',array_filter($joinfield))."`";

        $destfield = array($this->reffield, "object", $this->myclass, $this->myfield);
        $destfield = "`".implode('*',array_filter($destfield))."`";

        $destclass = ObjectDatabase::GetFullClassName($destclass);
        $joinstr = ObjectDatabase::BuildJoinQuery($joinclass, $joinfield, $destclass, 'id');
        
        $criteria = array( $destfield => $this->myid );
        $this->objects = $destclass::LoadManyMatchingAll($this->database, $criteria, false, null, $joinstr);
        
        $this->MergeWithObjectChanges();
    }
    
    public function GetJoinObject(BaseObject $joinobj) : ?JoinObject
    {
        return JoinObject::LoadJoinObject($this->database, $this, $this->parent, $joinobj);
    }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "objectjoin", $this->refclass, $this->reffield);
        return '`'.implode('*',array_filter($header)).'`';
    }
}
