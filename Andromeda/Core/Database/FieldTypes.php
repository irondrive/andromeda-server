<?php namespace Andromeda\Core\Database\FieldTypes; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, ConcurrencyException};
require_once(ROOT."/Core/Database/Database.php"); use Andromeda\Core\Database\DatabaseReadOnlyException;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the given counter exceeded its limit */
class CounterOverLimitException extends Exceptions\ClientDeniedException { public $message = "COUNTER_EXCEEDS_LIMIT"; }

/** Exception indicating the given value is the wrong type for this field */
class FieldDataTypeMismatch extends Exceptions\ServerException { public $message = "FIELD_DATA_TYPE_MISMATCH"; }

/** Exception indicating that loading via a foreign key link failed */
class ForeignKeyException extends ConcurrencyException { public $message = "DB_FOREIGN_KEY_FAILED"; }

/** Base class representing a database column ("field") */
abstract class BaseField
{
    /** database reference */
    protected ObjectDatabase $database;
    
    /** parent object reference */
    protected BaseObject $parent;

    /** name of the field/column in the DB */
    protected string $name;
    
    /** if true, save even on rollback */
    protected bool $saveOnRollback;
    
    /** number of times the field is modified */
    protected int $delta = 0;
    
    /**
     * @param string $name field name in DB
     * @param bool $saveOnRollback if true, save even on rollback
     */
    public function __construct(string $name, bool $saveOnRollback = false)
    {
        $this->name = $name;
        $this->saveOnRollback = $saveOnRollback;
    }

    /** Returns the unique ID of this field */
    public function ID() : string
    {
        return $this->parent->ID().':'.$this->name;
    }
    
    /**
     * @param BaseObject $parent parent object reference
     * @return $this
     */
    public function SetParent(BaseObject $parent) : self
    {
        $this->database = $parent->GetDatabase();
        $this->parent = $parent; return $this;
    }
    
    /**
     * Returns an exception for the given bad value type
     * @param mixed $value bad value
     * @return FieldDataTypeMismatch
     */
    protected function BadValue($value) : FieldDataTypeMismatch
    {
        return new FieldDataTypeMismatch($this->name.' '.gettype($value));
    }
    
    /** @return string field name in the DB */
    final public function GetName() : string { return $this->name; }

    /** @return int number of times modified */
    public function GetDelta() : int { return $this->delta; }
    
    /** @return bool true if was modified from DB */
    public function isModified() : bool { return $this->delta > 0; }
    
    /** Returns true if this field should be saved on rollback */
    public function isSaveOnRollback() : bool { return $this->saveOnRollback; }
    
    /**
     * Initializes the field's value from the DB
     * @param mixed $value database value
     * @return $this
     */
    public abstract function InitDBValue($value);
    
    /**
     * Returns the field's DB value
     * @return ?scalar
     */
    public abstract function GetDBValue();
    
    /** 
     * Returns the field's DB value and resets its delta
     * @return ?scalar
     */
    public function SaveDBValue()
    {
        $retval = $this->GetDBValue();
        $this->delta = 0;
        return $retval;
    }
    
    /** Restores this field to its initial value */
    public abstract function RestoreDefault() : void;
}

/** 
 * The typed template of a base field
 * @template T 
 */
trait BaseBaseT
{
    /** @var T value, possibly temporary only */
    protected $tempvalue;
    /** @var T value, non-temporary (DB) */
    protected $realvalue;
    /** @var T hardcoded default value */
    protected $default;

    /**
     * Type-checks the input value
     * @param mixed $value input value
     * @param bool $isInit true if this is from the DB
     * @return T the type-checked value
     */
    protected abstract function CheckValue($value, bool $isInit);
    
    /**
     * Initializes the field's value from the DB
     * @param mixed $value database value
     * @return $this
     */
    public function InitDBValue($value) : self
    {
        $value = $this->CheckValue($value, true);
        
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }
}

/**
 * The maybe-null typed template of a base field
 * @template T
 */
abstract class BaseNullT extends BaseField
{
    /** @use BaseBaseT<?T> */
    use BaseBaseT;
    
    /**
     * @param T $default default value, default null
     */
    public function __construct(string $name, bool $saveOnRollback = false, $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->default = $this->CheckValue($default, false);
        
        $this->RestoreDefault();
    }
    
    /** @return ?scalar */
    public function GetDBValue() { return $this->realvalue; }
    
    public function RestoreDefault() : void
    {
        $this->tempvalue = $this->default;
        $this->realvalue = $this->default;
        
        if ($this->default !== null) $this->delta = 1;
    }
    
    /**
     * Returns the field's type-checked value (maybe null)
     * @param bool $allowTemp if true, the value can be temporary
     * @return T the type-checked value
     */
    public function TryGetValue(bool $allowTemp = true)
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
}

/**
 * The non-null typed template of a base field
 * @template T
 */
abstract class BaseT extends BaseField
{
    /** @use BaseBaseT<T> */
    use BaseBaseT;
    
    /**
     * @param T $default default value, default none
     */
    public function __construct(string $name, bool $saveOnRollback = false, $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        if ($default !== null)
        {
            $this->default = $this->CheckValue($default, false);
            
            $this->RestoreDefault();
        }
    }
    
    /** @return scalar */
    public function GetDBValue() { return $this->realvalue; }
    
    /** Restores this field to its default value */
    public function RestoreDefault() : void
    {
        if (isset($this->default))
        {
            $this->tempvalue = $this->default;
            $this->realvalue = $this->default;
            $this->delta = 1;
        }
        else
        {
            unset($this->tempvalue);
            unset($this->realvalue);
            $this->delta = 0;
        }
    }
    
    /**
     * Returns the field's type-checked value
     * @param bool $allowTemp if true, the value can be temporary
     * @return T the type-checked value
     */
    public function GetValue(bool $allowTemp = true)
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized() { return isset($this->tempvalue); }
}

/** @template T */
trait BaseSettableT
{
    /**
     * Sets the field's value
     * @param T $value typed value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue($value, bool $isTemp = false) : bool
    {
        $value = $this->CheckValue($value, false);
        
        $this->tempvalue = $value;
        
        if (!$isTemp && (!isset($this->realvalue) || $value !== $this->realvalue))
        {
            if (isset($this->database))
            {
                if ($this->database->isReadOnly())
                    throw new DatabaseReadOnlyException();
            
                $this->database->notifyModified($this->parent);
            }
                
            $this->realvalue = $value; $this->delta++; return true;
        }
        
        return false;
    }
}

/**
 * A possibly-null field that can have its value set directly
 * @template T
 * @extends BaseNullT<?T>
 */
abstract class SettableNullT extends BaseNullT 
{ 
    /** @use BaseSettableT<T> */
    use BaseSettableT; 
}

/**
 * A non-null field that can have its value set directly
 * @template T
 * @extends BaseT<T>
 */
abstract class SettableT extends BaseT
{
    /** @use BaseSettableT<T> */
    use BaseSettableT;
}

/** 
 * A possibly-null string
 * @extends SettableNullT<?string> 
 */
class NullStringType extends SettableNullT
{
    /**
     * @param mixed $value
     * @return ?string
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value === '' || ($value !== null && !is_string($value)))
            throw parent::BadValue($value);
        
        return $value;
    }
}

/** 
 * A non-null (and non-empty) string
 * @extends SettableT<string> 
 */
class StringType extends SettableT
{
    /**
     * @param mixed $value
     * @return string
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value === null || !is_string($value) || $value === '')
            throw parent::BadValue($value);
        
        return $value;
    }
}

/** 
 * A possibly-null boolean
 * @extends SettableNullT<?bool> 
 */
class NullBoolType extends SettableNullT
{
    /**
     * @param mixed $value
     * @return ?bool
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($isInit && is_int($value)) $value = (bool)$value;
        
        if ($value !== null && !is_bool($value))
            throw parent::BadValue($value);
        
        return $value;
    }
    
    /** @return ?int */
    public function GetDBValue() : ?int 
    { 
        $val = parent::GetDBValue();
        return ($val !== null) ? (int)$val : null;
    }
}

/** 
 * A non-null boolean
 * @extends SettableT<bool> 
 */
class BoolType extends SettableT
{
    /**
     * @param mixed $value
     * @return bool
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($isInit && is_int($value)) $value = (bool)$value;
        
        if ($value === null || !is_bool($value))
            throw parent::BadValue($value);
            
        return $value;
    }
    
    /** @return int */
    public function GetDBValue() : int
    { 
        return (int)parent::GetDBValue(); 
    }
}

/** 
 * A possibly-null integer
 * @extends SettableNullT<?int> 
 */
class NullIntType extends SettableNullT
{
    /**
     * @param mixed $value
     * @return ?int
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value !== null && !is_int($value))
            throw parent::BadValue($value);
            
        return $value;
    }
}

/** 
 * A non-null integer
 * @extends SettableT<int> 
 */
class IntType extends SettableT
{
    /**
     * @param mixed $value
     * @return int
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value === null || !is_int($value))
            throw parent::BadValue($value);
        
        return $value;
    }
}

/** 
 * A possibly-null float
 * @extends SettableNullT<?float> 
 */
class NullFloatType extends SettableNullT
{
    /**
     * @param mixed $value
     * @return ?float
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value !== null && !is_float($value))
            throw parent::BadValue($value);
        
        return $value;
    }
}

/** 
 * A non-null float
 * @extends SettableT<float> 
 */
class FloatType extends SettableT
{
    /**
     * @param mixed $value
     * @return float
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value === null || !is_float($value))
            throw parent::BadValue($value);
        
        return $value;
    }
}

trait BaseDate
{
    /** Sets the value to the current timestamp */
    public function SetDateNow() : bool
    {
        return parent::SetValue(Main::GetInstance()->GetTime());
    }
}

/** A field that stores a possibly-null timestamp */
class NullDate extends NullFloatType { use BaseDate; }

/** A field that stores a non-null timestamp (default now) */
class Date extends FloatType 
{ 
    use BaseDate;

    public function __construct(string $name, bool $saveOnRollback = false)
    {
        $def = Main::GetInstance()->GetTime();
        parent::__construct($name, $saveOnRollback, $def);
    }
}

/** 
 * A field that stores a thread-safe integer counter
 * @extends BaseT<int> 
 */
class Counter extends BaseT
{
    private ?NullIntType $limit = null;
    
    /**
     * @param string $name
     * @param bool $saveOnRollback
     * @param ?NullIntType $limit optional counter limiting field
     */
    public function __construct(
        string $name, bool $saveOnRollback = false, 
        ?NullIntType $limit = null)
    {
        parent::__construct($name, $saveOnRollback, 0);
        
        $this->delta = 0; // implicit
        $this->limit = $limit;
    }
    
    /**
     * @param mixed $value
     * @return int
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($value === null || !is_int($value))
            throw parent::BadValue($value);
        
        return $value;
    }
    
    public function GetDBValue() : int { return $this->delta; }

    /**
     * Checks if the given delta would exceed the limit (if it exists)
     * @param int $delta delta to check
     * @param bool $throw if true, throw, else return
     * @throws CounterOverLimitException if $throw and the limit is exceeded
     * @return bool if $throw, return false if the limit was exceeded
     */
    public function CheckDelta(int $delta = 1, bool $throw = true) : bool
    {
        if ($delta > 0 && $this->limit !== null)
        {
            $limit = $this->limit->TryGetValue();
            
            if ($limit !== null && $this->tempvalue + $delta > $limit)
            {
                if ($throw) 
                    throw new CounterOverLimitException($this->name); 
                else return false;
            }
        }
        return true;
    }
    
    /**
     * Increments the counter by the given value
     * @param int $delta amount to increment
     * @param bool $ignoreLimit true to ignore the limit
     * @return bool true if the field was modified
     */
    public function DeltaValue(int $delta = 1, bool $ignoreLimit = false) : bool
    {
        if ($delta === 0) return false;
        
        if ($this->database->isReadOnly())
            throw new DatabaseReadOnlyException();
        
        $this->database->notifyModified($this->parent);
        
        if (!$ignoreLimit) $this->CheckDelta($delta);
        
        $this->tempvalue += $delta;
        $this->realvalue += $delta;
        $this->delta += $delta;
        return true;
    }
}

/** 
 * A field that stores a JSON-encoded array
 * @extends SettableT<array> 
 */
class JsonArray extends SettableT
{
    /**
     * @param mixed $value
     * @return array<mixed>
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($isInit) 
        {
            if ($value === null || !is_string($value)) 
                throw parent::BadValue($value);
            $value = Utilities::JSONDecode($value);
        }
        
        if ($value === null || !is_array($value))
            throw parent::BadValue($value);
        
        return $value;
    }
    
    public function GetDBValue() : string 
    {
        return Utilities::JSONEncode(parent::GetDBValue()); 
    }
}

/**
 * A field that stores a JSON-encoded array or null
 * @extends SettableNullT<?array>
 */
class NullJsonArray extends SettableNullT
{
    /**
     * @param mixed $value
     * @return array<mixed>
     */
    protected function CheckValue($value, bool $isInit)
    {
        if ($isInit && $value !== null)
        {
            if (!is_string($value))
                throw parent::BadValue($value);
            $value = Utilities::JSONDecode($value);
        }
        
        if ($value !== null && !is_array($value))
            throw parent::BadValue($value);
        
        return $value;
    }
    
    public function GetDBValue() : ?string
    {
        $val = parent::GetDBValue();
        if ($val === null) return null;
        return Utilities::JSONEncode($val);
    }
}

/** @template T of BaseObject */
trait BaseObjectRefT
{
    /**
     * @param class-string<T> $class object class
     * @param string $name
     */
    public function __construct(string $class, string $name)
    {
        parent::__construct($name);
        
        $this->class = $class;
    }
    
    public function GetValue(bool $allowTemp = true)
    {
        if ($allowTemp && isset($this->tempvalue)) /** @phpstan-ignore-line */
            return $this->tempvalue;
            
        if (!$allowTemp && isset($this->realvalue)) /** @phpstan-ignore-line */
            return $this->realvalue;
        
        return $this->FetchObject();
    }

    public function SaveDBValue()
    {
        //temp/real are used to hold the dirty value        
        unset($this->tempvalue);
        unset($this->realvalue);
        return parent::SaveDBValue();
    }
}

/**
 * A field stores a possibly-null reference to another object
 * @template T of BaseObject
 * @extends SettableNullT<?T>
 */
class NullObjectRefT extends SettableNullT
{
    /** @use BaseObjectRefT<T> */
    use BaseObjectRefT;
    
    /** @var class-string<T> */
    private string $class;
    
    private ?string $objId = null;

    protected function CheckValue($value, bool $isInit)
    {
        // $isInit must be false, we override InitDBValue
        if ($value !== null && !($value instanceof $this->class))
            throw parent::BadValue($value);
        return $value;
    }
    
    /**
     * Initializes the field's value from the DB
     * @param mixed $value database value
     * @return $this
     */
    public function InitDBValue($value) : self
    {
        if ($value !== null && !is_string($value))
            throw parent::BadValue($value);
        
        $this->objId = $value;
        $this->delta = 0;
        return $this;
    }
    
    public function GetDBValue() : ?string { return $this->objId; }
    
    /** Returns the ID of the object pointed to by this field */
    public function TryGetObjectID() : ?string { return $this->objId; }
    
    /** 
     * Loads the reference from the DB
     * @return ?T loaded object
     */
    protected function FetchObject() : ?BaseObject
    {
        if ($this->objId === null) return null;
        
        $obj = $this->database->TryLoadUniqueByKey($this->class, 'id', $this->objId);
        
        if ($obj === null) throw new ForeignKeyException($this->class); else return $obj;
    }

    public function SetValue($value, bool $isTemp = false) : bool
    {
        $retval = parent::SetValue($value, $isTemp); // check type
        
        if (!$isTemp) $this->objId = ($value !== null) ? $value->ID() : null;
            
        return $retval;
    }
    
    public function RestoreDefault() : void
    {
        $this->tempvalue = null;
        $this->realvalue = null;
        $this->objId = null;
    }
}

/**
 * A field that stores a non-null reference to another object
 * @template T of BaseObject
 * @extends SettableT<T>
 */
class ObjectRefT extends SettableT
{
    /** @use BaseObjectRefT<T> */
    use BaseObjectRefT;
    
    /** @var class-string<T> */
    private string $class;
    
    private string $objId;

    protected function CheckValue($value, bool $isInit)
    {
        // $isInit must be false, we override InitDBValue
        if ($value === null || !($value instanceof $this->class))
            throw parent::BadValue($value);
        return $value;
    }

    /**
     * Initializes the field's value from the DB
     * @param mixed $value database value
     * @return $this
     */
    public function InitDBValue($value) : self
    {
        if ($value === null || !is_string($value))
            throw parent::BadValue($value);
        
        $this->objId = $value;
        $this->delta = 0;
        return $this;
    }
    
    public function GetDBValue() : string { return $this->objId; }
    
    /** Returns the ID of the object pointed to by this field */
    public function GetObjectID() : string { return $this->objId; }
    
    /**
     * Loads the reference from the DB
     * @return T loaded object
     */
    protected function FetchObject() : BaseObject
    {
        $obj = $this->database->TryLoadUniqueByKey($this->class, 'id', $this->objId);
        
        if ($obj === null) throw new ForeignKeyException($this->class); else return $obj;
    }

    public function SetValue($value, bool $isTemp = false) : bool
    {
        $retval = parent::SetValue($value, $isTemp); // check type
        
        if (!$isTemp) $this->objId = $value->ID();
        
        return $retval;
    }
    
    public function RestoreDefault() : void
    {
        unset($this->tempvalue);
        unset($this->realvalue);
        unset($this->objId);
    }
}
