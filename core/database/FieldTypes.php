<?php namespace Andromeda\Core\Database\Fields; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php");use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/BaseObject.php");use Andromeda\Core\Database\BaseObject;

class Scalar
{
    protected $value; protected $delta = 0; const SPECIAL = null;
    
    public function __construct($value) { $this->value = $value; }
    public function GetValue() { return $this->value; }
    public function GetDelta() : int { return $this->delta; }
    
    public function ToString() { if ($this->value === false) return 0; else return $this->value; }
    
    public function SetValue($value) : void { if ($value != $this->value) { $this->delta++; $this->value = $value; } }
}

class Counter extends Scalar
{
    const SPECIAL = "counter";
    public function Delta($delta) : void { $this->value += $delta; $this->delta += $delta; }
    public function ToString() { return $this->delta; }
}

class JSON extends Scalar
{
    const SPECIAL = "json";
    public function __construct(string $value) { $this->value = Utilities::JSONDecode($value); }
    public function ToString() : string { return Utilities::JSONEncode($this->value); }
}

class ObjectPointer
{
    private $object; private $pointer; private $delta = 0;
    private $database; private $refclass; private $reffield;    

    public function __construct(ObjectDatabase $database, ?string $pointer, ?string $refclass, ?string $reffield = null) { 
        $this->pointer = $pointer; $this->refclass = $refclass; $this->reffield = $reffield; $this->database = $database; }
    
    public function GetDelta() : int { return $this->delta; }
    public function GetValue() : ?string { return $this->pointer; }
    public function GetPointer() : ?string { return $this->pointer; }
    public function GetRefClass() : ?string { return $this->refclass; }
    public function GetRefField() : ?string { return $this->reffield; }
    
    public function SetObject(?BaseObject $object) : void 
    { 
        if ($object !== null) $this->pointer = $object->ID(); else $this->pointer = null;
        
        $this->object = $object; $this->delta++; 
    }
    
    public function ToString() : ?string { return $this->pointer; }
    
    public function GetObject() : ?BaseObject
    { 
        if ($this->pointer === null) return null;
        
        if (!isset($this->object))
        {
            $class = "Andromeda\\".$this->refclass;
            
            $this->object = $class::LoadByID($this->database, $this->pointer);
        }        
        return $this->object;
    }
}

class ObjectRefs
{
    protected $objects; protected $database;
    protected $myclass; protected $myfield; protected $myid;
    protected $refclass; protected $reffield;
    
    private $refs_added = array(); private $refs_deleted = array();

    public function __construct(ObjectDatabase $database, string $myclass, string $myid, string $refclass, string $reffield, string $myfield) 
    {
        $this->database = $database; 
        $myclass = explode('\\',$myclass); unset($myclass[0]); $this->myclass = implode('\\',$myclass);
        $this->myfield = $myfield; $this->myid = $myid; 
        $this->refclass = $refclass; $this->reffield = $reffield; 
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
        $refclass = "Andromeda\\".$this->refclass; $reffield = $this->reffield."*object*".$this->myclass."*".$this->myfield;
        $this->objects = $refclass::LoadManyMatchingAll($this->database, array("$reffield"=>$this->myid));        
        $this->SyncNotifies();
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
    
    private function SyncNotifies()
    {
        foreach ($this->refs_added as $object) $this->objects[$object->ID()] = $object;
        foreach ($this->refs_deleted as $object) unset($this->objects[$object->ID()]);
        $this->refs_added = array(); $this->refs_deleted = array();
    }
}


