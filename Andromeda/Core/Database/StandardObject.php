<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/Database/BaseObject.php");

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
     * @return $this
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
     * @return static
     */
    protected static function BaseCreate(ObjectDatabase $database) : self {
        return parent::BaseCreate($database)->SetDate('created'); }
    
    /**
     * Gets the value of the given feature field as an int (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function GetFeatureInt(string $name, bool $allowTemp = true) : int { 
        return (int)($this->GetScalar("features__$name", $allowTemp)); }
    
    /**
     * Gets the value of the given feature field as an int (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function TryGetFeatureInt(string $name, bool $allowTemp = true) : ?int 
    { 
        $val = $this->TryGetScalar("features__$name", $allowTemp); 
        return ($val === null) ? null : (int)($val); 
    }
    
    /**
     * Gets the value of the given feature field as a bool (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function GetFeatureBool(string $name, bool $allowTemp = true) : bool {
        return (bool)($this->GetScalar("features__$name", $allowTemp)); }
        
    /**
     * Gets the value of the given feature field as a bool (used for config)
     * @see BaseObject::GetScalar()
     */
    protected function TryGetFeatureBool(string $name, bool $allowTemp = true) : ?bool
    {
        $val = $this->TryGetScalar("features__$name", $allowTemp);
        return ($val === null) ? null : (bool)($val);
    }
    
    /**
     * Sets the value of the given feature field to the given (?int) value
     * @see BaseObject::SetScalar()
     * @return $this
     */
    protected function SetFeatureInt(string $name, ?int $value, bool $temp = false) : self { 
        return $this->SetScalar("features__$name", $value, $temp); }
        
    /**
     * Sets the value of the given feature field to the given (?bool) value
     * @see BaseObject::SetScalar()
     * @return $this
     */
    protected function SetFeatureBool(string $name, ?bool $value, bool $temp = false) : self {
        return $this->SetScalar("features__$name", $value, $temp); }
        
    /** Returns true if the given feature has been modified */
    protected function isFeatureModified(string $name) : bool
    {
        return $this->GetScalarDelta("features__$name") > 0;
    }
    
    /**
     * Gets the value of the given counter field
     * @see BaseObject::GetScalar()
     */
    protected function GetCounter(string $name) : int { 
        return $this->GetScalar("counters__$name"); }
    
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
    protected function TryGetCounterLimit(string $name) : ?int 
    { 
        $field = "counters_limits__$name";        
        return array_key_exists($field, $this->scalars) 
            ? $this->TryGetScalar($field) : null;
    }
    
    /**
     * Sets the value of the given counter limit field
     * @see BaseObject::SetScalar()
     * @return $this
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
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {        
        if (($limit = $this->TryGetCounterLimit($field)) !== null)
        {
            $value = $this->CountObjectRefs($field);
            if ($value >= $limit) throw new CounterOverLimitException($field);
        }

        return parent::AddObjectRef($field, $object, $notification);
    }

    /**
     * Gets an array of the values of all fields matching a prefix
     * @param ?string $prefix the prefix to match fields against, or null to get all
     * @return array mapping field names (stripped of their prefix) to their values
     * @see BaseObject::TryGetScalar
     */
    protected function GetAllScalars(?string $prefix) : array
    {
        $output = array(); 
        foreach ($this->scalars as $key=>$val)
        {
            if ($prefix !== null)
            {
                $keyarr = explode("__",$key,2); 
                if ($keyarr[0] === $prefix)
                {
                    $output[$keyarr[1]] = $val->GetValue();
                }
            }
            else $output[$key] = $val->GetValue();
        }
        return $output;
    }
    
}
