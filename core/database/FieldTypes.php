<?php namespace Andromeda\Core\Database\FieldTypes; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/JoinUtils.php"); use Andromeda\Core\Database\JoinUtils;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
use Andromeda\Core\Database\ObjectTypeException;

class SpecialColumnException extends Exceptions\ServerException { public $message = "DB_INVALID_SPEICAL_COLUMN"; }
class TempAlreadyModifiedException extends Exceptions\ServerException { public $message = "SET_TEMP_ALREADY_MODIFIED"; }

const OPERATOR_SETEQUAL = 0; const OPERATOR_INCREMENT = 1;
const RETURN_SCALAR = 0; const RETURN_OBJECT = 1; const RETURN_OBJECTS = 2;

class Scalar
{
    protected string $myfield; 
    protected $tempvalue;
    protected $realvalue; 
    protected int $delta = 0; 
    protected bool $alwaysSave = false;
    
    protected ObjectDatabase $database;
    
    public static function GetOperatorType(){ return OPERATOR_SETEQUAL; }
    public static function GetReturnType(){ return RETURN_SCALAR; }
    
    public function __construct($defvalue = null, bool $alwaysSave = false)
    {
        $this->alwaysSave = $alwaysSave;
        $this->tempvalue = $defvalue;
        $this->realvalue = $defvalue;
        $this->delta = ($defvalue !== null);
    }

    public function Initialize(ObjectDatabase $database, BaseObject $parent, string $myfield) : void
    {
        $this->database = $database; 
        $this->parent = $parent; 
        $this->myfield = $myfield;
    }
    
    public function InitValue(?string $value) : void
    {
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
    }
    
    public function GetMyField() : string { return $this->myfield; }
    public function GetAlwaysSave() : bool { return $this->alwaysSave; }
    
    public function GetValue(bool $allowTemp = true) { return $allowTemp ? $this->tempvalue : $this->realvalue; }

    public function GetDelta() : int { return $this->delta; }
    public function ResetDelta() : self { $this->delta = 0; return $this; }

    public function GetDBValue() 
    { 
        if (is_bool($this->realvalue)) 
            return intval($this->realvalue);
        
        return $this->realvalue; 
    }
    
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
    
    public function __construct(bool $alwaysSave = false)
    {
        parent::__construct(0, $alwaysSave);
        $this->ResetDelta();
    }
    
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value ?? 0);
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
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value);
        if ($value) $value = Utilities::JSONDecode($value);
        $this->realvalue = $value; $this->tempvalue = $value;
    }
    
    public function GetDBValue() : string { return Utilities::JSONEncode($this->realvalue); }
}

class ObjectRef extends Scalar
{
    protected ?BaseObject $object;
    
    protected string $refclass; 
    protected ?string $reffield;   
    
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
        $id = $this->GetValue(); if ($id === null) return null;
        
        if (!isset($this->object)) $this->object = $this->GetRefClass()::TryLoadByID($this->database, $id);
        
        return $this->object;
    }
    
    public function SetObject(?BaseObject $object) : bool
    { 
        if (isset($this->object) && $object === $this->object) return false;
        
        if ($object !== null && !is_a($object, $this->GetBaseClass())) 
            throw new ObjectTypeException();

        $this->SetValue( ($object !== null) ? $object->ID() : null );
        
        $this->object = $object; $this->delta++; return true;
    }
    
    public function DeleteObject() : void
    {
        $id = $this->GetValue(); if ($id === null) return;        
        $this->GetRefClass()::DeleteByID($this->database, $id);        
        if (isset($this->object) && $this->object) $this->object->Delete();
    }
}

class ObjectPoly extends ObjectRef
{
    protected ?string $realclass = null;
    
    public function __construct(string $refclass, ?string $reffield = null, bool $refmany = true)
    {
        parent::__construct($refclass, $reffield, $refmany);        
    }
    
    private static function ShortClass(string $class) : string
    {
        return implode('\\',array_slice(explode('\\', $class),1)); 
    }
    
    public function InitValue(?string $value) : void
    {
        parent::InitValue($value);
        if ($value === null) return;
        
        $value = explode('*',$value);
        parent::InitValue($value[0]);
        $this->realclass = "Andromeda\\".$value[1];
    }
    
    public function GetBaseClass() : ?string { return $this->refclass; }
    public function GetRefClass() : ?string { return $this->realclass; }
    
    public static function GetIDTypeDBValue(string $id, string $type) : string { return $id.'*'.$type; }
    
    public static function GetObjectDBValue(?BaseObject $obj) : ?string
    {
        return ($obj === null) ? null : $obj->ID().'*'.static::ShortClass(get_class($obj));
    }
    
    public function GetDBValue() : ?string 
    { 
        if ($this->GetValue() === null) return null; 
        
        return $this->GetValue().'*'.static::ShortClass($this->realclass); 
    }
    
    public function SetObject(?BaseObject $object) : bool
    {
        if (!parent::SetObject($object)) return false;
        
        $this->realclass = ($object === null) ? null : get_class($object);
        
        return true;
    }
}

const REFSTYPE_SINGLE = 0; const REFSTYPE_MANY = 1;

class ObjectRefs extends Counter
{
    protected array $objects; 
    protected bool $isLoaded = false;
    
    protected BaseObject $parent; 
    protected string $refclass; 
    protected string $reffield;
    protected bool $parentPoly;
    
    protected array $refs_added = array();
    protected array $refs_deleted = array();
    
    public static function GetReturnType(){ return RETURN_OBJECTS; }
    public static function GetRefsType(){ return REFSTYPE_SINGLE; }
    
    public function __construct(string $refclass, ?string $reffield = null, bool $parentPoly = false)
    {
        $this->refclass = $refclass; $this->reffield = $reffield; $this->parentPoly = $parentPoly;
    }
    
    public function GetRefClass() : string { return $this->refclass; }
    public function GetRefField() : string { return $this->reffield; }

    public function GetObjects(?int $limit = null, ?int $offset = null) : array
    {
        if (!$this->isLoaded) { $this->LoadObjects($limit, $offset); return $this->objects; }
        
        else return array_slice($this->objects, $offset??0, $limit);
    }
    
    protected function InnerLoadObjects(?int $limit = null, ?int $offset = null) : void
    {
        $myval = $this->parentPoly ? ObjectPoly::GetObjectDBValue($this->parent) : $this->parent->ID();
        $q = new QueryBuilder(); $q->Where($q->Equals($this->reffield, $myval));
        $this->objects = $this->refclass::LoadByQuery($this->database, $q->Limit($limit)->Offset($offset));
    }
    
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
    
    public function DeleteObjects() : void
    {
        if (!$this->GetValue()) return;
        
        $this->GetRefClass()::DeleteByObject($this->database, $this->reffield, $this->parent, $this->parentPoly);
        
        foreach ($this->refs_added as $obj) $obj->Delete();
    }
    
    protected function MergeWithObjectChanges() : void
    {
        foreach ($this->refs_added as $object) $this->objects[$object->ID()] = $object;
        foreach ($this->refs_deleted as $object) unset($this->objects[$object->ID()]);
    }
    
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

class ObjectJoin extends ObjectRefs
{
    protected BaseObject $parent; 
    protected string $joinclass;
    
    public static function GetRefsType(){ return REFSTYPE_MANY; }
    
    public function GetJoinClass() : string { return $this->joinclass; }
    
    public function __construct(string $refclass, ?string $reffield, string $joinclass)
    {
        parent::__construct($refclass, $reffield);
        $this->joinclass = $joinclass;
    }
    
    protected function InnerLoadObjects(?int $limit = null, ?int $offset = null) : void
    {
        $q = new QueryBuilder(); $key = $this->database->GetClassTableName($this->joinclass).'.'.$this->reffield;
        $q->Where($q->Equals($key, $this->parent->ID()))->Join($this->database, $this->joinclass, $this->myfield, $this->refclass, 'id');
        $this->objects = $this->refclass::LoadByQuery($this->database, $q->Limit($limit)->Offset($offset));
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
