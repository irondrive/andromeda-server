<?php namespace Andromeda\Core\Database\Fields; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php");use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/BaseObject.php");use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

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
            
            else throw new SpecialColumnException("Class ".static::class." Column $key");
        }
    }
    
    public abstract function GetDBValue();
    public abstract function GetMyField() : string;
    public abstract function GetColumnName() : string;
}

class Scalar extends Field
{
    protected $myfield; protected $value; protected $delta = 0; const SPECIAL = null;
    
    public function __construct($value, string $myfield) { $this->myfield = $myfield; $this->value = $value; }
    
    public function GetValue() { return $this->value; }
    public function GetDelta() : int { return $this->delta; }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, self::SPECIAL);
        return implode('*',array_filter($header));
    }
    
    public function GetMyField() : string { return $this->myfield; }
    
    public function GetDBValue() { if ($this->value === false) return 0; else return $this->value; }
    
    public function SetValue($value, bool $temp = false) : void 
    { 
        if ($value === $this->value) return;
        if ($temp && $this->delta !== 0) throw new TempAlreadyModifiedException();

        if (!$temp) $this->delta++; 
        $this->value = $value; 
    }
    
    public function EraseValue() : void
    {
        sodium_memzero($this->value);
    }
}

class Counter extends Scalar
{
    const SPECIAL = "counter";
    public function Delta($delta) : void { $this->value += $delta; $this->delta += $delta; }
    public function GetDBValue() { return $this->delta; }
}

class JSON extends Scalar
{
    const SPECIAL = "json";
    
    public function __construct(string $value, string $myfield) 
    { 
        parent::__construct($value, $myfield); 
        $this->value = Utilities::JSONDecode($value); 
    }
    
    public function GetDBValue() : string { return Utilities::JSONEncode($this->value); }
}

class ObjectPointer extends Field
{
    protected $database; protected $object; protected $pointer; protected $delta = 0;
    protected $myfield; protected $refclass; protected $reffield;      
    
    public function __construct(ObjectDatabase $database, $value, array $header)
    {
        if (count($header) < 3) throw new SpecialColumnException(implode('*',$header));
        
        $this->database = $database; $this->pointer = $value; 
        $this->myfield = $header[0]; $this->refclass = $header[2]; $this->reffield = $header[3] ?? null;
    }
    
    public function GetDelta() : int { return $this->delta; }
    public function GetPointer() : ?string { return $this->pointer; }
    public function GetMyField() : string { return $this->myfield; }
    public function GetRefClass() : string { return $this->refclass; }
    public function GetRefField() : ?string { return $this->reffield; }
    
    public function GetDBValue() : ?string { return $this->pointer; }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "object", $this->refclass, $this->reffield);
        return implode('*',array_filter($header));
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
    
    public function SetObject(?BaseObject $object) : void 
    { 
        if ($object === $this->object) return;
        
        if ($object !== null) $this->pointer = $object->ID(); else $this->pointer = null;
        
        $this->object = $object; $this->delta++; 
    }
}

class ObjectPolyPointer extends ObjectPointer
{
    public function __construct(ObjectDatabase $database, $value, array $header)
    {        
        $this->database = $database;
        $this->myfield = $header[0]; $this->reffield = $header[2] ?? null;
        
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
        $header = array($this->myfield, "objectpoly", $this->reffield);
        return implode('*',array_filter($header));
    }
    
    public function SetObject(?BaseObject $object) : void
    {
        if ($object === $this->object) return;
        
        parent::SetObject($object);
        
        $this->refclass = ($object === null) ? null : ObjectDatabase::GetShortClassName(get_class($object));
    }
}

class ObjectRefs extends Field
{
    protected $objects; protected $database;
    protected $myclass; protected $myfield; protected $myid;
    protected $refclass; protected $reffield;
    
    protected $refs_added = array(); protected $refs_deleted = array();
    
    public function __construct(ObjectDatabase $database, $value, array $header, BaseObject $parent)
    {
        if (count($header) < 4) throw new SpecialColumnException(implode('*',$header));
        
        $this->database = $database;
        
        $this->myclass = ObjectDatabase::GetShortClassName(get_class($parent));
        
        $this->myfield = $header[0]; $this->myid = $parent->ID(); $this->refclass = $header[2]; $this->reffield = $header[3]; 
    }
    
    public function GetDBValue() { return null; }
    
    public function GetColumnName() : string
    {
        $header = array($this->myfield, "objectrefs", $this->refclass, $this->reffield);
        return implode('*',array_filter($header));
    }
        
    public function GetRefClass() : string { return $this->refclass; }
    public function GetRefField() : string { return $this->reffield; }
    public function GetMyClass() : string { return $this->myclass; }
    public function GetMyField() : string { return $this->myfield; }

    public function GetObjects() : array
    {
        if (!isset($this->objects)) $this->LoadObjects();
        return $this->objects;
    }
    
    protected function LoadObjects()
    {
        $class = ObjectDatabase::GetFullClassName($this->refclass); $reffield = $this->reffield."*object*".$this->myclass."*".$this->myfield;
        $this->objects = $class::LoadManyMatchingAll($this->database, array("$reffield"=>$this->myid));        
        foreach ($this->refs_added as $object) $this->objects[$object->ID()] = $object;
        foreach ($this->refs_deleted as $object) unset($this->objects[$object->ID()]);
        $this->refs_added = array(); $this->refs_deleted = array();
    }
    
    public function AddObject(BaseObject $object, bool $notification)
    {
        if (!isset($this->objects))
        {
            if (!in_array($object, $this->refs_added))
            {
                array_push($this->refs_added, $object);
            }
        }
        else if (!in_array($object, $this->objects))
        {
            $this->objects[$object->ID()] = $object;
        }
    }
    
    public function RemoveObject(BaseObject $object, bool $notification)
    {
        if (!isset($this->objects))
        {
            if (!in_array($object, $this->refs_deleted))
            {
                array_push($this->refs_deleted, $object);
            }
        }
        else if (in_array($object, $this->objects))
        {
            unset($this->objects[$object->ID()]);
        }
    }
}


