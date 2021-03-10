<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/BaseObject.php");

/** Exception indicating that the counter exceeded its limit */
class CounterOverLimitException extends Exceptions\ClientDeniedException { 
    public function __construct(string $message){ $this->message = "COUNTER_EXCEEDS_LIMIT: $message"; } }

/** Extends BaseObject with helpers for some commonly-used interfaces */
abstract class StandardObject extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array('dates__created' => null);
    }
    
    /** 
     * Returns the timestamp value stored in the given date field 
     * @see BaseObject::GetScalar()
     */
    protected function GetDate(string $name) : float { return $this->GetScalar("dates__$name"); }
    
    /**
     * Returns the timestamp value stored in the given date field
     * @see BaseObject::TryGetScalar()
     */
    protected function TryGetDate(string $name) : ?float { return $this->TryGetScalar("dates__$name"); } 
    
    /**
     * Sets the value of the given date field to the given value
     * @param string $name the name of the date field to set
     * @param ?float $value the value of the timestamp, or null to use the current time
     * @see BaseObject::SetScalar()
     */
    protected function SetDate(string $name, ?float $value = null) : self
    { 
        return $this->SetScalar("dates__$name", $value ?? Main::GetInstance()->GetTime()); 
    }
    
    /** Returns the timestamp when this object was created */
    public function GetDateCreated() : float { return $this->GetDate('created'); }
    
    /**
     * Create the object by setting its created date
     * @see BaseObject::BaseCreate()
     */
    protected static function BaseCreate(ObjectDatabase $database) : self {
        return parent::BaseCreate($database)->SetDate('created'); }
    
    /**
     * Gets the value of the given feature field (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function GetFeature(string $name) : int { 
        return intval($this->GetScalar("features__$name")); }
    
    /**
     * Gets the value of the given feature field (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function TryGetFeature(string $name) : ?int 
    { 
        $val = $this->TryGetScalar("features__$name"); 
        return ($val === null) ? null : intval($val); 
    }
    
    /**
     * Sets the value of the given feature field to the given value
     * @see BaseObject::SetScalar()
     */
    protected function SetFeature(string $name, ?int $value, bool $temp = false) : self { 
        return $this->SetScalar("features__$name", $value, $temp); }
    
    
    /** Returns true if the given feature has been modified */
    protected function isFeatureModified(string $name) : bool
    {
        return boolval($this->GetScalarDelta("features__$name"));
    }
    
    /**
     * Gets the value of the given counter field
     * @see BaseObject::GetScalar()
     */
    protected function GetCounter(string $name) : int { 
        return $this->GetScalar("counters__$name"); }
    
    /**
     * Gets the value of the given counter field
     * @see BaseObject::TryGetScalar()
     */
    protected function TryGetCounter(string $name) : ?int { 
        return $this->TryGetScalar("counters__$name"); }
    
    /**
     * Gets the value of the given counter limit field
     * @see BaseObject::GetScalar()
     */
    protected function GetCounterLimit(string $name) : int { 
        return $this->GetScalar("counters_limits__$name"); }
    
    /**
     * Gets the value of the given counter limit field
     * @see BaseObject::TryGetScalar()
     */
    protected function TryGetCounterLimit(string $name) : ?int { 
        return $this->TryGetScalar("counters_limits__$name"); }
    
    /**
     * Sets the value of the given counter limit field
     * @see BaseObject::SetScalar()
     */
    protected function SetCounterLimit(string $name, ?int $value, bool $temp = false) : self { 
        return $this->SetScalar("counters_limits__$name", $value, $temp); }
    
    /**
     * Checks whether the given counter plus a delta would exceed the limit
     * @param string $name the name of the counter and counter limit field to check
     * @param int $delta the value to try incrementing the counter by
     * @param bool $except if true, throw an exception instead of returning false
     * @return bool if true, the limit exists and is not exceeded
     * @throws CounterOverLimitException if $except and retval is false
     */
    protected function CheckCounter(string $name, int $delta = 0, bool $except = true) : bool
    {
        if (($limit = $this->TryGetCounterLimit($name)) !== null)
        {
            if ($this->GetCounter($name) + $delta > $limit) 
            {
                if (!$except) return false;
                else throw new CounterOverLimitException($name);
            }
        } 
        return true;
    }
    
    /**
     * Increment a counter by the given value
     * @param string $name the name of the counter field to increment
     * @param int $delta the value to increment by
     * @param bool $ignoreLimit if true, ignore the counter's limit
     * @throws CounterOverLimitException if the counter's limit exists and is exceeded
     * @see BaseObject::DeltaScalar()
     * @return $this
     */
    protected function DeltaCounter(string $name, int $delta = 1, bool $ignoreLimit = false) : self
    {
        if (!$ignoreLimit && $delta > 0) $this->CheckCounter($name, $delta);
            
        return parent::DeltaCounter("counters__$name",$delta); 
    }
    
    /**
     * Adds an object reference, checking for a limit on the number of references
     * @throws CounterOverLimitException if the limit exists and is exceeded
     * @see BaseObject::AddObjectRef()
     */
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : BaseObject
    {        
        if (($limit = $this->TryGetCounterLimit($field)) !== null)
        {
            $value = $this->CountObjectRefs($field);
            if ($value >= $limit) throw new CounterOverLimitException($field);
        }

        return parent::AddObjectRef($field, $object, $notification);
    }
    
    /**
     * Gets an array of all date field values
     * @see StandardObject::GetAllScalars
     */
    protected function GetAllDates(callable $vfunc = null) : array { 
        return $this->GetAllScalars('dates',$vfunc); }
    
    /**
     * Gets an array of all feature field values
     * @see StandardObject::GetAllScalars
     */
    protected function GetAllFeatures(callable $vfunc = null) : array {
        return $this->GetAllScalars('features',$vfunc); }
    
    /**
     * Gets an array of all counter and objectrefs field values
     * @see StandardObject::GetAllScalars
     */
    protected function GetAllCounters(callable $vfunc = null) : array
    { 
        $counters = $this->GetAllScalars('counters',$vfunc); 
        foreach (array_keys($this->objectrefs) as $refskey) 
            $counters["refs_$refskey"] = $this->objectrefs[$refskey]->GetValue();
        return $counters;
    }
    
    /**
     * Gets an array of all counter limit field values
     * @see StandardObject::GetAllScalars
     */
    protected function GetAllCounterLimits(callable $vfunc = null) : array { 
        return $this->GetAllScalars('counters_limits',$vfunc); }

    /**
     * Gets an array of the values of all fields matching a prefix
     * @param ?string $prefix the prefix to match fields against, or null to get all
     * @param callable $vfunc the function to map to each field, or TryGetScalar if null
     * @return array mapping field names (stripped of their prefix) to their values
     * @see BaseObject::TryGetScalar
     */
    protected function GetAllScalars(?string $prefix, callable $vfunc = null) : array
    {
        $output = array(); 
        foreach (array_keys($this->scalars) as $key)
        {
            if ($prefix !== null)
            {
                $keyarr = explode("__",$key,2); 
                if ($keyarr[0] === $prefix)
                {
                    $output[$keyarr[1]] = $vfunc ? $vfunc($key) : $this->TryGetScalar($key);
                }
            }
            else $output[$key] = $this->TryGetScalar($key);
        }
        return $output;
    }
    
}
