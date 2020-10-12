<?php namespace Andromeda\Core\Database\FieldTypes; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/JoinUtils.php"); use Andromeda\Core\Database\JoinUtils;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
use Andromeda\Core\Database\ObjectTypeException;

class SpecialColumnException extends Exceptions\ServerException { public $message = "DB_INVALID_SPEICAL_COLUMN"; }
class TempAlreadyModifiedException extends Exceptions\ServerException { public $message = "SET_TEMP_ALREADY_MODIFIED"; }

const OPERATOR_SETEQUAL = 0; const OPERATOR_INCREMENT = 1;
const RETURN_SCALAR = 0; const RETURN_OBJECT = 1; const RETURN_OBJECTS = 2;

class Scalar
{
    protected $myfield; protected $tempvalue; protected $realvalue; protected $delta = 0;
    
    public static function GetOperatorType(){ return OPERATOR_SETEQUAL; }
    public static function GetReturnType(){ return RETURN_SCALAR; }

    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield, ?string $value)
    {
        $this->database = $database; 
        $this->parent = $parent; 
        $this->myfield = $myfield; 
        $this->tempvalue = $value; 
        $this->realvalue = $value;
    }
    
    public function GetMyField() : string { return $this->myfield; }
    
    public function GetValue() { return $this->tempvalue; }
    public function GetRealValue() { return $this->realvalue; }
    
    public function GetDelta() : int { return $this->delta; }
    public function ResetDelta() : self { $this->delta = 0; return $this; }

    public function GetDBValue() { return ($this->realvalue === false) ? 0 : $this->realvalue; }
    
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
    public static function GetOperatorType(){ return OPERATOR_INCREMENT; }
    
    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield, ?string $value)
    {
        parent::Initialize($database, $parent, $myfield, $value ?? 0);
    }
    
    public function Delta(int $delta = 1) : bool 
    { 
        if ($delta === 0) return false;
        $this->tempvalue += $delta; 
        $this->realvalue += $delta; 
        $this->delta += $delta; return true;
    }
        
    public function GetDBValue() { return $this->delta; }
}

class JSON extends Scalar
{
    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield, ?string $value) 
    {
        parent::Initialize($database, $parent, $myfield, $value);
        $value = Utilities::JSONDecode($value);
        $this->realvalue = $value; $this->tempvalue = $value;
    }
    
    public function GetDBValue() : string { return Utilities::JSONEncode($this->realvalue); }
}

class ObjectRef extends Scalar
{
    protected $database; protected $object;
    protected $refclass; protected $reffield;   
    
    public static function GetReturnType(){ return RETURN_OBJECT; }

    public function __construct(string $refclass, ?string $reffield = null, bool $refmany = true)
    {
        $this->refclass = $refclass; $this->reffield = $reffield; $this->refmany = $refmany;
    }
    
    public function GetBaseClass() : ?string { return $this->refclass; }
    public function GetRefClass() : ?string { return $this->refclass; }
    public function GetRefField() : ?string { return $this->reffield; }
    public function GetRefIsMany() : bool { return $this->refmany; }
    
    public function GetObject() : ?BaseObject
    {
        if ($this->realvalue === null) return null;
        
        if (!isset($this->object))
        {
            $this->object = $this->GetRefClass()::LoadByID($this->database, $this->realvalue);
        }
        return $this->object;
    }
    
    public function SetObject(?BaseObject $object) : bool
    { 
        if ($object === $this->object) return false;
        
        if ($object !== null && !is_a($object, $this->GetBaseClass())) 
            throw new ObjectTypeException();
        
        if ($object !== null) $this->realvalue = $object->ID(); else $this->realvalue = null;
        
        $this->object = $object; $this->delta++; return true;
    }
}

class ObjectPoly extends ObjectRef
{
    public function __construct(string $refclass, ?string $reffield = null, bool $refmany = true)
    {
        $this->baseclass = $refclass;
        parent::__construct($refclass, $reffield, $refmany);        
    }
    
    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield, ?string $value)
    {
        parent::Initialize($database, $parent, $myfield, $value);

        if ($value === null) return;
        
        $value = explode('*',$value);
        $this->realvalue = $value[0];
        $this->tempvalue = $value[0];
        $this->refclass = $value[1];
    }
    
    public function GetBaseClass() : ?string { return $this->baseclass; }
    
    public function GetDBValue() : ?string 
    { 
        if ($this->realvalue === null) return null; 
        else return $this->realvalue.'*'.$this->refclass; 
    }
    
    public function SetObject(?BaseObject $object) : bool
    {
        if (!parent::SetObject($object)) return false;
        
        $this->refclass = ($object === null) ? null : get_class($object);
        
        return true;
    }
}

const REFSTYPE_SINGLE = 0; const REFSTYPE_MANY = 1;

class ObjectRefs extends Counter
{
    protected $database; protected $objects; 
    protected $parent; protected $refclass; protected $reffield;
    
    protected $refs_added = array(); protected $refs_deleted = array();
    
    public static function GetReturnType(){ return RETURN_OBJECTS; }
    public static function GetRefsType(){ return REFSTYPE_SINGLE; }
    
    public function __construct(string $refclass, ?string $reffield = null)
    {
        $this->refclass = $refclass; $this->reffield = $reffield;
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
        $criteria = array("`".$this->reffield."`" => $this->parent->ID());
        $this->objects = $this->refclass::LoadManyMatchingAll($this->database, $criteria);    
        
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
    protected $parent; protected $joinclass;
    
    public static function GetRefsType(){ return REFSTYPE_MANY; }
    
    public function GetJoinClass() : string { return $this->joinclass; }
    
    public function __construct(string $refclass, ?string $reffield, string $joinclass)
    {
        parent::__construct($refclass, $reffield);
        $this->joinclass = $joinclass;
    }
    
    protected function LoadObjects()
    {
        $joinstr = ObjectDatabase::BuildJoinQuery($this->joinclass, $this->myfield, $this->refclass, 'id');
        
        $criteria = array( ObjectDatabase::GetClassTableName($this->joinclass).'.'.$this->reffield => $this->parent->ID() );
        $this->objects = $this->refclass::LoadManyMatchingAll($this->database, $criteria, false, null, $joinstr);
        
        $this->MergeWithObjectChanges();
    }
    
    public function GetJoinObject(BaseObject $joinobj) : ?BaseObject
    {
        return JoinUtils::LoadJoinObject($this->database, $this, $this->parent, $joinobj);
    }
    
    public function AddObject(BaseObject $object, bool $notification) : bool
    {
        if ($notification) return parent::AddObject($object, $notification);
        
        JoinUtils::CreateJoin($this->database, $this, $this->parent, $object); return false;
    }
    
    public function RemoveObject(BaseObject $object, bool $notification) : bool
    {
        if ($notification) return parent::RemoveObject($object, $notification);
        
        JoinUtils::DeleteJoin($this->database, $this, $this->parent, $object); return false;
    }
}
