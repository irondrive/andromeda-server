<?php declare(strict_types=1); namespace Andromeda\Core\Database\FieldTypes; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, Exceptions, ObjectDatabase};

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
    protected bool $alwaysSave;
    
    /** number of times the field is modified */
    protected int $delta = 0;
    
    /**
     * @param string $name field name in DB
     * @param bool $saveOnRollback if true, save even on rollback
     */
    public function __construct(string $name, bool $saveOnRollback = false)
    {
        $this->name = $name;
        $this->alwaysSave = $saveOnRollback;
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
    
    /** @return string field name in the DB */
    public function GetName() : string { return $this->name; }

    /** @return int number of times modified */
    public function GetDelta() : int { return $this->delta; }
    
    /** @return bool true if was modified from DB */
    public function isModified() : bool { return $this->delta > 0; }
    
    /** Returns true if this field should be saved on rollback */
    public function isAlwaysSave() : bool { return $this->alwaysSave; }
    
    /**
     * Initializes the field's value from the DB
     * @param ?scalar $value database value
     * @return $this
     */
    public abstract function InitDBValue($value);
    
    /**
     * Returns the field's DB value
     * @return ?scalar
     */
    public abstract function GetDBValue();

    /** 
     * Resets this field's delta 
     * @return $this 
     */
    public function SetUnmodified() : self { $this->delta = 0; return $this; }
    
    /** Unsets the field's value so it cannot be used */
    public abstract function Uninitialize() : void;
    
    /** Notify the DB that this field was modified */
    protected function NotifyModified() : void
    {
        if (isset($this->database))
        {
            if ($this->database->isReadOnly())
                throw new Exceptions\DatabaseReadOnlyException();
            
            $this->database->notifyModified($this->parent);
        }
    }
    
    /** True if given DB values are always strings */
    protected function isDBValueString() : bool
    {
        return $this->database->GetInternal()->DataAlwaysStrings();
    }
    
    /** 
     * Gets a type mismatch exception for the given value 
     * @param scalar|object $value
     */
    protected function GetTypeMismatchException($value) : Exceptions\FieldDataTypeMismatch
    {
        return new Exceptions\FieldDataTypeMismatch($this->name.' '.
            (is_object($value)?get_class($value):gettype($value)));
    }
}

/** A common Uninitialize function for scalar types */
trait ScalarCommon
{
    public function Uninitialize() : void
    {
        unset($this->default);
        unset($this->tempvalue);
        unset($this->realvalue);
        unset($this->delta);
    }
}

/** A possibly-null string */
class NullStringType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected ?string $tempvalue;
    /** non-temporary (DB) */
    protected ?string $realvalue;

    /** @param ?string $default default value, default null */
    public function __construct(string $name, bool $saveOnRollback = false, ?string $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->tempvalue = $default;
        $this->realvalue = $default;
        $this->delta = ($default !== null) ? 1 : 0;
    }

    public function InitDBValue($value) : self
    {
        if ($value !== null && !is_string($value))
            throw $this->GetTypeMismatchException($value);

        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : ?string { return $this->realvalue; }
    
    /**
     * Returns the field's value (maybe null)
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function TryGetValue(bool $allowTemp = true) : ?string
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /**
     * Sets the field's value
     * @param ?string $value string value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(?string $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && $value !== $this->realvalue)
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A non-null string */
class StringType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected string $tempvalue;
    /** non-temporary (DB) */
    protected string $realvalue;

    /** @param string $default default value, default none */
    public function __construct(string $name, bool $saveOnRollback = false, ?string $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        if ($default !== null)
        {
            $this->tempvalue = $default;
            $this->realvalue = $default;
            $this->delta = 1;
        }
    }

    public function InitDBValue($value) : self
    {
        if ($value === null || !is_string($value))
            throw $this->GetTypeMismatchException($value ?? "null");
        
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : string { return $this->realvalue; }

    /**
     * Returns the field's value
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function GetValue(bool $allowTemp = true) : string
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized(bool $allowTemp = true) : bool
    {
        return $allowTemp ? isset($this->tempvalue) : isset($this->realvalue);
    }
    
    /**
     * Sets the field's value
     * @param string $value string value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(string $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && (!isset($this->realvalue) || $value !== $this->realvalue))
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A possibly-null boolean */
class NullBoolType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected ?bool $tempvalue;
    /** non-temporary (DB) */
    protected ?bool $realvalue;

    /** @param ?bool $default default value, default null */
    public function __construct(string $name, bool $saveOnRollback = false, ?bool $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->tempvalue = $default;
        $this->realvalue = $default;
        $this->delta = ($default !== null) ? 1 : 0;
    }

    public function InitDBValue($value) : self
    {
        if ($value !== null)
        {
            if (!is_int($value) && !$this->isDBValueString())
                throw $this->GetTypeMismatchException($value);
            else $value = (bool)$value; // always cast
        }

        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : ?int 
    {
        return ($this->realvalue === null) 
            ? $this->realvalue : (int)$this->realvalue;
    }

    /**
     * Returns the field's value (maybe null)
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function TryGetValue(bool $allowTemp = true) : ?bool
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /**
     * Sets the field's value
     * @param ?bool $value bool value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(?bool $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && $value !== $this->realvalue)
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A non-null boolean */
class BoolType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected bool $tempvalue;
    /** non-temporary (DB) */
    protected bool $realvalue;

    /** @param bool $default default value, default none */
    public function __construct(string $name, bool $saveOnRollback = false, ?bool $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        if ($default !== null)
        {
            $this->tempvalue = $default;
            $this->realvalue = $default;
            $this->delta = 1;
        }
    }

    public function InitDBValue($value) : self
    {
        $isStr = $this->isDBValueString();
        if ($value === null || (!$isStr && !is_int($value)))
            throw $this->GetTypeMismatchException($value ?? "null");
        else $value = (bool)$value; // always cast
    
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : int { return (int)$this->realvalue; }

    /**
     * Returns the field's value
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function GetValue(bool $allowTemp = true) : bool
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized(bool $allowTemp = true) : bool
    {
        return $allowTemp ? isset($this->tempvalue) : isset($this->realvalue);
    }
    
    /**
     * Sets the field's value
     * @param bool $value bool value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(bool $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && (!isset($this->realvalue) || $value !== $this->realvalue))
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A possibly-null integer */
class NullIntType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected ?int $tempvalue;
    /** non-temporary (DB) */
    protected ?int $realvalue;

    /** @param ?int $default default value, default null */
    public function __construct(string $name, bool $saveOnRollback = false, ?int $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->tempvalue = $default;
        $this->realvalue = $default;
        $this->delta = ($default !== null) ? 1 : 0;
    }

    public function InitDBValue($value) : self
    {
        if ($value !== null)
        {
            if ($this->isDBValueString())
                $value = (int)$value;
            else if (!is_int($value))
                throw $this->GetTypeMismatchException($value);
        }
        
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : ?int { return $this->realvalue; }

    /**
     * Returns the field's value (maybe null)
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function TryGetValue(bool $allowTemp = true) : ?int
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /**
     * Sets the field's value
     * @param ?int $value int value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(?int $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && $value !== $this->realvalue)
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A non-null integer */
class IntType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected int $tempvalue;
    /** non-temporary (DB) */
    protected int $realvalue;

    /** @param int $default default value, default none */
    public function __construct(string $name, bool $saveOnRollback = false, ?int $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        if ($default !== null)
        {
            $this->tempvalue = $default;
            $this->realvalue = $default;
            $this->delta = 1;
        }
    }

    public function InitDBValue($value) : self
    {
        $isStr = $this->isDBValueString();
        if ($value === null || (!$isStr && !is_int($value)))
            throw $this->GetTypeMismatchException($value ?? "null");
        else $value = (int)$value;

        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : int { return $this->realvalue; }
    
    /**
     * Returns the field's value
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function GetValue(bool $allowTemp = true) : int
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized(bool $allowTemp = true) : bool
    {
        return $allowTemp ? isset($this->tempvalue) : isset($this->realvalue);
    }
    
    /**
     * Sets the field's value
     * @param int $value int value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(int $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && (!isset($this->realvalue) || $value !== $this->realvalue))
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A possibly-null float */
class NullFloatType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected ?float $tempvalue;
    /** non-temporary (DB) */
    protected ?float $realvalue;

    /** @param ?float $default default value, default null */
    public function __construct(string $name, bool $saveOnRollback = false, ?float $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->tempvalue = $default;
        $this->realvalue = $default;
        $this->delta = ($default !== null) ? 1 : 0;
    }

    public function InitDBValue($value) : self
    {
        if ($value !== null)
        {
            if ($this->isDBValueString())
                $value = (float)$value;
            else if (!is_float($value))
                throw $this->GetTypeMismatchException($value);
        }
        
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : ?float { return $this->realvalue; }

    /**
     * Returns the field's value (maybe null)
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function TryGetValue(bool $allowTemp = true) : ?float
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /**
     * Sets the field's value
     * @param ?float $value float value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(?float $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && $value !== $this->realvalue)
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A non-null float */
class FloatType extends BaseField
{
    use ScalarCommon;
    
    /** possibly temporary only */
    protected float $tempvalue;
    /** non-temporary (DB) */
    protected float $realvalue;

    /** @param float $default default value, default none */
    public function __construct(string $name, bool $saveOnRollback = false, ?float $default = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        if ($default !== null)
        {
            $this->tempvalue = $default;
            $this->realvalue = $default;
            $this->delta = 1;
        }
    }

    public function InitDBValue($value) : self
    {
        $isStr = $this->isDBValueString();
        if ($value === null || (!$isStr && !is_float($value)))
            throw $this->GetTypeMismatchException($value ?? "null");
        else $value = (float)$value;
    
        $this->tempvalue = $value;
        $this->realvalue = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : float { return $this->realvalue; }

    /**
     * Returns the field's value
     * @param bool $allowTemp if true, the value can be temporary
     */
    public function GetValue(bool $allowTemp = true) : float
    {
        return $allowTemp ? $this->tempvalue : $this->realvalue;
    }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized(bool $allowTemp = true) : bool
    {
        return $allowTemp ? isset($this->tempvalue) : isset($this->realvalue);
    }
    
    /**
     * Sets the field's value
     * @param float $value float value
     * @param bool $isTemp if true, only temp (don't save)
     * @return bool true if the field was modified
     */
    public function SetValue(float $value, bool $isTemp = false) : bool
    {
        $this->tempvalue = $value;
        
        if (!$isTemp && (!isset($this->realvalue) || $value !== $this->realvalue))
        {
            $this->NotifyModified();
            
            $this->realvalue = $value;
            $this->delta++;
            return true;
        }
        
        return false;
    }
}

/** A field that stores a possibly-null timestamp */
class NullTimestamp extends NullFloatType
{ 
    /** Sets the value to the current timestamp */
    public function SetTimeNow() : bool
    {
        return parent::SetValue($this->parent->GetDatabase()->GetTime());
    }
}

/** A field that stores a non-null timestamp (default now) */
class Timestamp extends FloatType 
{
    /** Sets the value to the current timestamp */
    public function SetTimeNow() : bool
    {
        return parent::SetValue($this->parent->GetDatabase()->GetTime());
    }
}

/**  A field that stores a thread-safe integer counter */
class Counter extends BaseField
{
    private ?NullIntType $limit = null;
    
    protected int $value;
    
    /** @param ?NullIntType $limit optional counter limiting field */
    public function __construct(string $name, bool $saveOnRollback = false, ?NullIntType $limit = null)
    {
        parent::__construct($name, $saveOnRollback);
        
        $this->value = 0;
        $this->delta = 0; // implicit
        $this->limit = $limit;
    }

    public function InitDBValue($value) : self
    {
        $isStr = $this->isDBValueString();
        if ($value === null || (!$isStr && !is_int($value)))
            throw $this->GetTypeMismatchException($value ?? "null");
        else $value = (int)$value;
        
        $this->value = $value;
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : int { return $this->delta; }
    
    public function Uninitialize() : void
    {
        unset($this->value);
        unset($this->delta);
    }
    
    /** Returns the field's total count value */
    public function GetValue() : int { return $this->value; }

    /**
     * Checks if the given delta would exceed the limit (if it exists)
     * @param int $delta delta to check
     * @param bool $throw if true, throw, else return
     * @throws Exceptions\CounterOverLimitException if $throw and the limit is exceeded
     * @return bool if $throw, return false if the limit was exceeded
     */
    public function CheckDelta(int $delta = 1, bool $throw = true) : bool
    {
        if ($delta > 0 && $this->limit !== null)
        {
            $limit = $this->limit->TryGetValue();
            
            if ($limit !== null && $this->value + $delta > $limit)
            {
                if (!$throw) return false;
                
                throw new Exceptions\CounterOverLimitException($this->name);
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

        if (!$ignoreLimit) $this->CheckDelta($delta);
        
        $this->NotifyModified();
        
        $this->value += $delta;
        $this->delta += $delta;
        return true;
    }
}

/** 
 * A field that stores a JSON-encoded array (or null) 
 * @template T of array
 */
class NullJsonArray extends BaseField
{
    /** @var ?T */
    protected ?array $value = null;

    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value !== null && !is_string($value))
            throw $this->GetTypeMismatchException($value);
        
        if ($value !== null && $value !== "")
            $this->value = Utilities::JSONDecode($value);
        
        $this->delta = 0;
        
        return $this;
    }
    
    public function GetDBValue() : ?string
    {
        if ($this->value === null) return null;
        
        return Utilities::JSONEncode($this->value);
    }
    
    public function Uninitialize() : void 
    { 
        unset($this->value);
        unset($this->delta);
    }
    
    /** 
     * Returns the field's array value 
     * @return ?T
     */
    public function TryGetArray() : ?array { return $this->value; }

    /**
     * Sets the field's value
     * @param ?T $value array value
     * @return bool true if the field was modified
     */
    public function SetArray(?array $value) : bool
    {
        if ($value === null && $this->value === null) return false;
        
        $this->NotifyModified();
        
        $this->value = $value;
        $this->delta++;
        
        return true;
    }
}

/** 
 * A field that stores a JSON-encoded array 
 * @template T of array
 */
class JsonArray extends BaseField
{
    /** @var T */
    protected array $value;

    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value === null || !is_string($value))
            throw $this->GetTypeMismatchException($value ?? "null");
        
        if ($value !== "")
            $this->value = Utilities::JSONDecode($value);
        
        $this->delta = 0;
        
        return $this;
    }

    public function GetDBValue() : string
    { 
        return Utilities::JSONEncode($this->value); 
    }
    
    public function Uninitialize() : void 
    { 
        unset($this->value);
        unset($this->delta);
    }
    
    /** 
     * Returns the field's array value 
     * @return T
     */
    public function GetArray() : array { return $this->value; }

    /**
     * Sets the field's value
     * @param T $value array value
     * @return bool true if the field was modified
     */
    public function SetArray(array $value) : bool
    {
        $this->NotifyModified();
        
        $this->value = $value;
        $this->delta++;
        
        return true;
    }
}

/**
 * A field stores a possibly-null reference to another object via its ID
 * @template T of BaseObject
 */
class NullObjectRefT extends BaseField
{
    /** ID reference */
    protected ?string $objId;
    
    /** 
     * field class
     * @var class-string<T>
     */
    protected string $class;

    /**
     * @param class-string<T> $class object class
     * @param string $name
     */
    public function __construct(string $class, string $name)
    {
        parent::__construct($name);
        
        $this->objId = null;
        $this->class = $class;
    }
    
    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value !== null && !is_string($value))
            throw $this->GetTypeMismatchException($value);
        
        $this->objId = $value;
        $this->delta = 0;
        
        return $this;
    }
    
    public function GetDBValue() : ?string { return $this->objId; }
    
    public function Uninitialize() : void
    {
        unset($this->objId);
        unset($this->class);
        unset($this->delta);
    }

    /** 
     * Returns the field's object (maybe null) 
     * @return ?T
     */
    public function TryGetObject() : ?BaseObject
    {
        if ($this->objId === null) return null;
        
        $obj = $this->database->TryLoadUniqueByKey($this->class, 'id', $this->objId);
        
        if ($obj !== null) return $obj;
        throw new Exceptions\ForeignKeyException($this->class);
    }
    
    /** Returns the ID of the object pointed to by this field */
    public function TryGetObjectID() : ?string { return $this->objId; }
    
    /**
     * Sets the field's value
     * @param ?T $value object value
     * @return bool true if the field was modified
     */
    public function SetObject(?BaseObject $value) : bool
    {
        if ($value === null)
        {
            if ($this->objId === null) 
                return false;
            
            $this->objId = null;
            $this->delta++;
            return true;
        }
        
        if ($value->ID() === $this->objId) return false;
        
        if (!($value instanceof $this->class))
            throw $this->GetTypeMismatchException($value);
            
        $this->objId = $value->ID();
        $this->delta++;
        return true;
    }
}

/**
 * A field stores a possibly-null reference to another object via its ID
 * @template T of BaseObject
 */
class ObjectRefT extends BaseField
{
    /** ID reference */
    protected string $objId;
    
    /** 
     * field class 
     * @var class-string<T>
     */
    protected string $class;
    
    /**
     * @param class-string<T> $class object class
     * @param string $name
     */
    public function __construct(string $class, string $name)
    {
        parent::__construct($name);
        
        $this->class = $class;
    }
    
    /** @return $this */
    public function InitDBValue($value) : self
    {
        if ($value === null || !is_string($value))
            throw $this->GetTypeMismatchException($value ?? "null");
        
        $this->objId = $value;
        $this->delta = 0;
        
        return $this;
    }
    
    public function GetDBValue() : string { return $this->objId; }

    public function Uninitialize() : void
    {
        unset($this->objId);
        unset($this->class);
        unset($this->delta);
    }
    
    /**
     * Returns the field's object
     * @return T
     */
    public function GetObject() : BaseObject
    {
        $obj = $this->database->TryLoadUniqueByKey($this->class, 'id', $this->objId);
        
        if ($obj !== null) return $obj;
        throw new Exceptions\ForeignKeyException($this->class);
    }
    
    /** Returns the ID of the object pointed to by this field */
    public function GetObjectID() : string { return $this->objId; }
    
    /** Returns true if this field's value is initialized */
    public function isInitialized() : bool { return isset($this->objId); }
    
    /**
     * Sets the field's value
     * @param T $value object value
     * @return bool true if the field was modified
     */
    public function SetObject(BaseObject $value) : bool
    {
        if (isset($this->objId) && $value->ID() === $this->objId) return false;
        
        if (!($value instanceof $this->class))
            throw $this->GetTypeMismatchException($value);
        
        $this->objId = $value->ID();
        $this->delta++;
        return true;
    }
}
