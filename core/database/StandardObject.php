<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php");
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CounterOverLimitException extends Exceptions\ClientDeniedException { 
    public function __construct(string $message){ $this->message = "COUNTER_EXCEEDS_LIMIT: $message"; } }

abstract class StandardObject extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'dates__created' => null
        ));
    }
    
    protected function GetDate(string $name) : ?int                { return $this->GetScalar("dates__$name"); }
    protected function TryGetDate(string $name) : ?int             { return $this->TryGetScalar("dates__$name"); } 
    
    protected function SetDate(string $name, ?int $value = null) : self
    { 
        return $this->SetScalar("dates__$name", $value ?? time()); 
    }
    
    public function GetDateCreated() : int { return $this->GetDate('created'); }    
    protected static function BaseCreate(ObjectDatabase $database) {
        return parent::BaseCreate($database)->SetDate('created'); }
    
    protected function GetFeature(string $name) : ?int             { return $this->GetScalar("features__$name"); }
    protected function TryGetFeature(string $name) : ?int          { return $this->TryGetScalar("features__$name"); }
    protected function ExistsFeature(string $name) : ?int          { return $this->ExistsScalar("features__$name"); }
    
    protected function SetFeature(string $name, ?int $value, bool $temp = false) : self   { return $this->SetScalar("features__$name", $value, $temp); }
    protected function EnableFeature(string $name, bool $temp = false) : self             { return $this->SetScalar("features__$name", 1, $temp); }
    protected function DisableFeature(string $name, bool $temp = false) : self            { return $this->SetScalar("features__$name", 0, $temp); }
    
    protected function GetCounter(string $name) : ?int             { return $this->GetScalar("counters__$name"); }
    protected function TryGetCounter(string $name) : ?int          { return $this->TryGetScalar("counters__$name"); }
    protected function ExistsCounter(string $name) : ?int          { return $this->ExistsScalar("counters__$name"); }
    protected function GetCounterLimit(string $name) : ?int        { return $this->GetScalar("counters_limits__$name"); }
    protected function TryGetCounterLimit(string $name) : ?int     { return $this->TryGetScalar("counters_limits__$name"); }
    protected function ExistsCounterLimit(string $name) : ?int     { return $this->ExistsScalar("counters_limits__$name"); }
    
    protected function SetCounterLimit(string $name, ?int $value, bool $temp = false) : self  { return $this->SetScalar("counters_limits__$name", $value, $temp); }
    
    protected function IsCounterOverLimit(string $name, int $delta = 0) : bool
    {
        if (($limit = $this->TryGetCounterLimit($name)) !== null)
        {
            $value = $this->GetCounter($name) + $delta;
            if ($value > $limit) return true;
        } 
        return false;
    }
    
    protected function DeltaCounter(string $name, int $delta = 1) : self
    { 
        if ($this->IsCounterOverLimit($name, $delta))
            throw new CounterOverLimitException($name); 
            
        return $this->DeltaScalar("counters__$name",$delta); 
    }
    
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : BaseObject
    {        
        if (($limit = $this->TryGetCounterLimit($field)) !== null)
        {
            $value = $this->CountObjectRefs($field);
            if ($value >= $limit) throw new CounterOverLimitException($field);
        }

        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function GetAllDates(callable $vfunc = null) : array    { return $this->GetAllScalars('dates',$vfunc); }
    protected function GetAllFeatures(callable $vfunc = null) : array { return $this->GetAllScalars('features',$vfunc); }
    
    protected function GetAllCounters(callable $vfunc = null) : array
    { 
        $counters = $this->GetAllScalars('counters',$vfunc); 
        foreach (array_keys($this->objectrefs) as $refskey) 
            $counters["refs_$refskey"] = $this->objectrefs[$refskey]->GetValue();
        return $counters;
    }
    
    protected function GetAllCounterLimits(callable $vfunc = null) : array { return $this->GetAllScalars('counters_limits',$vfunc); }

    private function GetAllScalars(string $prefix, callable $vfunc = null) : array
    {
        $output = array(); 
        foreach (array_keys($this->scalars) as $key)
        {
            $keyarr = explode("__",$key,2); 
            if ($keyarr[0] === $prefix)
            {
                $output[$keyarr[1]] = $vfunc ? $vfunc($key) : $this->TryGetScalar($key);
            }
        }
        return $output;
    }
    
}

class DuplicateSingletonException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBSINGLETON"; }

abstract class SingletonObject extends StandardObject
{
    private static $instances = array();

    public static function Load(ObjectDatabase $database) : self
    {
        if (array_key_exists(static::class, self::$instances)) 
            return self::$instances[static::class];
        
        $objects = static::LoadAll($database);
        if (count($objects) > 1) throw new DuplicateSingletonException();
        else if (count($objects) == 0) throw new ObjectNotFoundException();
        
        else return (self::$instances[static::class] = array_values($objects)[0]);
    }
}

