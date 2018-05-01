<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CounterOverLimitException extends Exceptions\ServerException    { public $message = "COUNTER_EXCEEDS_LIMIT"; }

interface ClientObject { public function GetClientObject(int $level = 0) : array; }

abstract class StandardObject extends BaseObject
{
    protected function GetDate(string $name) : ?int                { return $this->GetScalar("dates__$name"); }
    protected function TryGetDate(string $name) : ?int             { return $this->TryGetScalar("dates__$name"); } 
    
    protected function SetDate(string $name, int $value = null) : self
    { 
        if ($value === null) $value = time(); 
        return $this->SetScalar("dates__$name", $value); 
    }
    
    public function GetDateCreated() : int { return $this->GetDate('created'); }    
    protected static function BaseCreate(ObjectDatabase $database) {
        return parent::BaseCreate($database)->SetDate('created'); }
    
    protected function GetFeature(string $name) : ?int             { return $this->GetScalar("features__$name"); }
    protected function TryGetFeature(string $name) : ?int          { return $this->TryGetScalar("features__$name"); }
    protected function ExistsFeature(string $name) : ?int          { return $this->ExistsScalar("features__$name"); }
    protected function SetFeature(string $name, ?int $value) : self   { return $this->SetScalar("features__$name", $value); }
    protected function EnableFeature(string $name) : self             { return $this->SetScalar("features__$name", 1); }
    protected function DisableFeature(string $name) : self            { return $this->SetScalar("features__$name", 0); }
    
    protected function GetCounter(string $name) : ?int             { return $this->GetScalar("counters__$name"); }
    protected function TryGetCounter(string $name) : ?int          { return $this->TryGetScalar("counters__$name"); }
    protected function ExistsCounter(string $name) : ?int          { return $this->ExistsScalar("counters__$name"); }
    protected function GetCounterLimit(string $name) : ?int        { return $this->GetScalar("counters_limits__$name"); }
    protected function TryGetCounterLimit(string $name) : ?int     { return $this->TryGetScalar("counters_limits__$name"); }
    protected function ExistsCounterLimit(string $name) : ?int     { return $this->ExistsScalar("counters_limits__$name"); }
    protected function SetCounterLimit(string $name, ?int $value) : self  { return $this->SetScalar("counters_limits__$name", $value); }
    
    protected function DeltaCounter(string $name, int $delta = 1) : self
    { 
        if (($limit = $this->TryGetCounterLimit($name)) !== null) 
        {
            $value = $this->GetCounter($name) + $delta;
            if ($value > $limit) throw new CounterOverLimitException($name); 
        } 
            
        return $this->DeltaScalar("counters__$name",$delta); 
    }
    
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : BaseObject
    {        
        if (($limit = $this->TryGetCounterLimit($field)) !== null)
        {
            $value = count($this->GetObjectRefs($field));
            if ($value === $limit) throw new CounterOverLimitException($field);
        }
        
        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function GetAllDates() : array        { return $this->GetAllScalars('dates'); }
    protected function GetAllFeatures() : array     { return $this->GetAllScalars('features'); }
    protected function GetAllCounters() : array     { return $this->GetAllScalars('counters'); }
    protected function GetAllCounterLimits() : array { return $this->GetAllScalars('counters_limits'); }
    
    private function GetAllScalars(string $prefix) : array
    {
        $output = array(); 
        foreach (array_keys($this->scalars) as $key)
        {
            $keyarr = explode("__",$key); 
            if ($keyarr[0] === $prefix)
            {
                $realkey = implode('__',array_slice($keyarr, 0, 2));
                $output[$keyarr[1]] = $this->TryGetScalar($realkey);
            }                
        }
        return $output;
    } 
    
}

class DuplicateSingletonException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBSINGLETON"; }

abstract class SingletonObject extends StandardObject
{
    private static $instances = array();
    
    /* PHP hackery: singletonobject keeps its own private static array of instances
     * When a subclass runs load, it uses its own class name to index the global array of instances
     * Can't just have a single $instance because php doesn't consider it as part of the subclass 
     * the static keyword can refer to the subclass but self *only* refers to the base class */
    
    public static function Load(ObjectDatabase $database) : self
    {
        if (array_key_exists(static::class, self::$instances)) 
            return self::$instances[static::class];
        
        $objects = self::LoadManyMatchingAll($database, null);
        if (count($objects) > 1) throw new DuplicateSingletonException();
        else if (count($objects) == 0) throw new ObjectNotFoundException();
        
        else return (self::$instances[static::class] = array_values($objects)[0]);
    }
}