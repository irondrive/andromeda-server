<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/BaseObject.php");
require_once(ROOT."/Core/Database/FieldTypes.php");

/** Andromeda cannot rollback and then commit/save since database/objects state is not sufficiently reset */
class SaveAfterRollbackException extends DatabaseException { public $message = "SAVE_AFTER_ROLLBACK"; }

/** Exception indicating the class does not have a base table and no children (improper setup) */
class MissingTableException extends DatabaseException { public $message = "CLASS_MISSING_TABLE"; }

/** Exception indicating that the requested class does not match the loaded object */
class ObjectTypeException extends DatabaseException { public $message = "DBOBJECT_TYPE_MISMATCH"; }

/** Exception indicating that multiple objects were loaded for by-unique query */
class UniqueKeyException extends DatabaseException { public $message = "MULTIPLE_UNIQUE_OBJECTS"; }

/** Base exception indicating that something went wrong due to concurrency, try again */
abstract class ConcurrencyException extends Exceptions\ClientException { public $code = 503; }

/** Exception indicating that the update failed to match any objects */
class UpdateFailedException extends ConcurrencyException { public $message = "DB_OBJECT_UPDATE_FAILED"; }

/** Exception indicating that the row insert failed */
class InsertFailedException extends ConcurrencyException { public $message = "DB_OBJECT_INSERT_FAILED"; }

/** Exception indicating that the row delete failed */
class DeleteFailedException extends ConcurrencyException { public $message = "DB_OBJECT_DELETE_FAILED"; }

/**
 * Provides the interfaces between BaseObject and the underlying PDO database
 *
 * Basic functions include loading, caching, updating, creating, and deleting objects.
 * This class should only be used internally by BaseObjects.
 */
class ObjectDatabase
{
    /** PDO database reference */
    private Database $db;

    public function __construct(Database $db) { $this->db = $db; }
    
    /** Returns the internal database instance */
    public function GetInternal() : Database { return $this->db; }
    
    private bool $rolledBack = false;
    
    /** Commits the internal database */
    public function commit() : void
    {
        $this->db->commit();
    }
    
    /** Rolls back the internal database */
    public function rollback() : void
    {
        $this->rolledBack = true;
        $this->db->rollback();
    }
    
    /** @see Database::isReadOnly() */
    public function isReadOnly() : bool 
    { 
        return $this->db->isReadOnly();
    }
    
    /** array of objects that were created
     * @var array<string, BaseObject> */
    private array $created = array();
    
    /** array of objects that were modified
     * @var array<string, BaseObject> */
    private array $modified = array();
    
    /** 
     * Notify the DB that the given object needs to be inserted 
     * @return $this
     */
    public function notifyCreated(BaseObject $obj) : self
    {
        $this->created[$obj->ID()] = $obj; return $this;
    }

    /** 
     * Notify the DB that the given object needs to be updated 
     * @return $this
     */
    public function notifyModified(BaseObject $obj) : self
    {
        $this->modified[$obj->ID()] = $obj; return $this;
    }

    /**
     * Create/update all objects that notified us as needing it
     * @param bool $isRollback true if this is a rollback
     * @return $this
     */
    public function SaveObjects(bool $isRollback = false) : self
    {
        if (!$isRollback)
        {
            if ($this->rolledBack) throw new SaveAfterRollbackException();
            
            // insert new objects first for foreign keys
            foreach ($this->created as $obj) $obj->Save();
        }
        
        foreach ($this->modified as $obj) $obj->Save($isRollback);
        
        $this->created = array();
        $this->modified = array();
        
        return $this;
    }
    
    /**
     * Return the database table name for a class
     * @template T of BaseObject
     * @param class-string<T> $class the class name for the table
     */
    public function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class); unset($class[0]);
        $table = "a2obj_".strtolower(implode('_', $class));
        
        return $this->db->UsePublicSchema() ? "public.$table" : $table;
    }

    /**
     * Counts objects using the given query (ignores limit/offset)
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @return int count of objects
     */
    public function CountObjectsByQuery(string $class, QueryBuilder $query) : int
    {
        $classes = $class::GetTableClasses();
        $subquery = empty($classes);
        
        if (!$subquery)
        {
            $topclass = $classes[array_key_last($classes)];
            $subquery = ($class !== $topclass && !$topclass::HasTypedRows());
        }
        
        if ($subquery) // count foreach child
        {
            if (($childmap = $class::GetChildMap()) === null)
                throw new MissingTableException($class);
            
            $count = 0; foreach ($childmap as $child)
            {
                if ($class === $child) throw new MissingTableException($class);
                $count += $this->CountObjectsByQuery($child, $query);
            }
            return $count;
        }
        else // count from the table directly
        {
            $query = clone $query;
            
            $topclass = $classes[array_key_last($classes)];
            
            // if not the top table, filter only the type we want
            if ($topclass !== $class)
            {
                $eqstr = $topclass::GetWhereChild($query, $class);
                $query->Where($this->GetClassTableName($topclass).'.'.$eqstr);
            }
            
            $table = $this->GetClassTableName($topclass);
            
            $querystr = "SELECT ".($prop = "COUNT($table.id)")." FROM $table ".$query->GetText();
            
            return $this->db->read($querystr, $query->GetData())[0][$prop];
        }
    }

    /**
     * Loads an array of objects using the given query
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @param ?class-string<T> $limClass the class to limit/offset by, default $class
     * @return array<string, T> array of objects indexed by their IDs
     */
    public function LoadObjectsByQuery(string $class, QueryBuilder $query, ?string $limClass = null) : array
    {
        $limClass ??= $class;
        
        // if this is a base class, need to load for each child with a table
        $objects = array(); $castRows = self::CanLoadByUpcast($class);
        
        if (!$castRows && ($childmap = $class::GetChildMap()) !== null)
        {
            $hasSelf = false; foreach ($childmap as $child)
            {
                if ($child === $class) { $hasSelf = true; continue; }
                $objs = $this->LoadObjectsByQuery($child, $query, $limClass);
                foreach ($objs as $obj) $objects[$obj->ID()] = $obj; // merge
            }
            
            // if self is in the child map then the base is also concrete
            if (!$hasSelf) return $objects;
        }
        
        $query = clone $query;
        
        // now we do an actual load for this class
        $querystr = "SELECT * ".$this->BaseFromQuery($class, $query, false, !$castRows, $limClass);
        
        foreach ($this->db->read($querystr, $query->GetData()) as $row)
        {
            $object = $this->ConstructObject($class, $row, $castRows);
            
            $objects[$object->ID()] = $object;
        }

        return $objects;
    }

    /**
     * Immediately delete objects matching the given query
     * The objects will be loaded and have their notifyDeleted() run
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects to delete
     * @param QueryBuilder $query the query used to match objects
     * @param ?class-string<T> $limClass the class to limit/offset by, default $class
     * @return int number of deleted objects
     */
    public function DeleteObjectsByQuery(string $class, QueryBuilder $query, ?string $limClass = null) : int
    {
        $limClass ??= $class;
        
        if (!$this->db->SupportsRETURNING())
        {
            // if we can't use RETURNING, just load the objects and delete individually
            $objs = $this->LoadObjectsByQuery($class, $query, $limClass);
            
            foreach ($objs as $obj) $this->DeleteObject($obj);
            
            return count($objs);
        }
        
        // if this is a base class, need to delete for each child
        $count = 0; $castRows = self::CanLoadByUpcast($class);
        
        if (!$castRows && ($childmap = $class::GetChildMap()) !== null)
        {
            $hasSelf = false; foreach ($childmap as $child)
            {
                if ($child === $class) { $hasSelf = true; continue; }
                $count += $this->DeleteObjectsByQuery($child, $query, $limClass);
            }
            
            // if self is in the child map then the base is also concrete
            if (!$hasSelf) return $count;
        }
        
        $query = clone $query;
        
        // now we do an actual delete/load for this class
        $querystr = "DELETE ".$this->BaseFromQuery($class, $query, true, !$castRows, $limClass)." RETURNING *";
        
        $rows = $this->db->readwrite($querystr, $query->GetData());
        
        foreach ($rows as $row)
        {
            $object = $this->ConstructObject($class, $row, $castRows);
            
            $this->RemoveObject($object);
        }
        
        return $count + count($rows);
    }
    
    /**
     * Returns true iff the given class should be loaded by upcasting rows
     * to child classes rather than doing per-child queries
     * @param class-string<BaseObject> $class class to be loaded
     * @return bool true iff every child has no table or children (is final)
     */
    private static function CanLoadByUpcast(string $class) : bool
    {
        $childmap = $class::GetChildMap();
        if ($childmap === null) return false;
        
        $tables = $class::GetTableClasses();
        if (empty($tables)) return false;
        
        if (!$class::HasTypedRows()) return false;
        
        foreach ($childmap as $child)
        {
            $subquery = ($class !== $child) &&                
                ($child::GetChildMap() !== null || // has children
                count($tables) !== count($child::GetTableClasses())); // has table
            
            if ($subquery) return false;
        }
        return true;
    }

    /**
     * Returns a from query with the proper table joining and type selections
     * @template T of BaseObject
     * @param class-string<T> $class class name to build a query for
     * @param QueryBuilder $query pre-existing query to add to (may modify!)
     * @param bool $fromTables true if we need table names before FROM
     * @param bool $selectType if true, allow selecting where child type
     * @param ?class-string<T> $limClass the class we want to limit/offset by
     * @return string compiled SQL query
     */
    private function BaseFromQuery(string $class, QueryBuilder $query, bool $fromTables, bool $selectType, ?string $limClass = null) : string
    {
        $classes = $class::GetTableClasses();
        if (empty($classes)) throw new MissingTableException($class);
        $topclass = $classes[array_key_last($classes)];

        // join together every base class table
        $bclass = null; foreach ($classes as $pclass)
        {
            if ($bclass !== null) // join to base to child
                $query->Join($this, $pclass,'id', $bclass,'id');
            $bclass = $pclass;
        }
        
        // if not the top table, filter only the type we want
        if ($selectType && $topclass::HasTypedRows())
        {
            $eqstr = $topclass::GetWhereChild($query, $class);
            $query->Where($this->GetClassTableName($topclass).'.'.$eqstr);
        }
        
        // add the list of tables if requested (required for DELETE..JOIN)
        $selstr = ""; if ($fromTables && count($classes) > 1)
        {
            $selstr = implode(', ',array_map(function(string $sclass){
                return $this->GetClassTableName($sclass); }, $classes)).' ';
        }

        $fromstr = $this->GetClassTableName($classes[0]);
        
        // we want the limit/offset to apply to the class being loaded as, 
        // not the current child table... use an ugly subquery to accomplish this
        if ($limClass !== null && $class !== $limClass &&
            ($query->GetLimit() !== null || $query->GetOffset() !== null))
        {
            $subquery = new QueryBuilder();
            
            $subquery->Where($query->GetWhere()); $query->Where(null);
            $subquery->Limit($query->GetLimit()); $query->Limit(null);
            $subquery->Offset($query->GetOffset()); $query->Offset(null);
            
            $query->Where("$fromstr.id IN (SELECT id FROM (SELECT id FROM $fromstr $subquery) AS t)");
        }
    
        return $selstr."FROM $fromstr ".$query->GetText();
    }
    
    /** master array of objects in memory to prevent duplicates
     * @var array<class-string<BaseObject>, array<string, BaseObject>> */
    private array $objectsByBase = array();
    
    /**
     * Returns an array of loaded object info for debugging, by class
     * @return array<class-string<BaseObject>, array<string, class-string<BaseObject>>>
     */
    public function getLoadedObjects() : array
    {
        $retval = array(); 
        foreach ($this->objectsByBase as $bclass=>$objs)
            foreach ($objs as $obj)
        {
            $retval[$bclass][$obj->ID()] = get_class($obj);
        }
        return $retval;
    }
    
    /** Returns the total count of loaded objects */
    public function getLoadedCount() : int
    {
        $retval = 0;
        foreach ($this->objectsByBase as $objs)
            $retval += count($objs);
        return $retval;
    }
    
    /**
     * Constructs a new object from a row if not already loaded
     * @template T of BaseObject
     * @param class-string<T> $class final row class to instantiate
     * @param array<mixed> $row row of data from DB
     * @param bool $upcast if true, upcast rows before constructing
     * @throws ObjectTypeException if already loaded a different type
     * @return T instantiated object
     */
    private function ConstructObject(string $class, array $row, bool $upcast) : BaseObject
    {
        $id = (string)$row['id'];
        
        $tables = $class::GetTableClasses();
        if (!empty($tables)) $base = $tables[0];
        else throw new MissingTableException($class);
        
        $this->objectsByBase[$base] ??= array();
        
        // if this object is already loaded, don't replace it
        if (array_key_exists($id, $this->objectsByBase[$base]))
            $retobj = $this->objectsByBase[$base][$id];
        else
        {
            if ($upcast) $class = $class::GetRowClass($row);
            
            $retobj = new $class($this, $row);
            $this->objectsByBase[$base][$id] = $retobj;
        }
        
        if (!($retobj instanceof $class))
            throw new ObjectTypeException("$retobj not $class");
        
        return $retobj;
    }
    
    /**
     * Removes the object from our arrays and calls notifyDeleted()
     * @param BaseObject $object object that has been deleted
     */
    private function RemoveObject(BaseObject $object) : void
    {
        $id = $object->ID();
        
        unset($this->created[$id]);
        unset($this->modified[$id]);
        
        $base = $object::GetTableClasses()[0];
        unset($this->objectsByBase[$base][$id]);
        
        $this->UnsetAllObjectKeyFields($object);
        
        $object->notifyDeleted();
    }

    /**
     * Immediately deletes a single object from the database
     * @param BaseObject $object the object to delete
     * @throws DeleteFailedException if nothing is deleted
     * @return $this
     */
    public function DeleteObject(BaseObject $object) : self
    {
        $query = new QueryBuilder();
        $basetbl = $this->GetClassTableName($object::GetTableClasses()[0]);
        $query->Where($query->Equals($basetbl.'.id',$object->ID()));
        
        $class = get_class($object);
        $querystr = "DELETE ".$this->BaseFromQuery($class, $query, true, false);
        
        if ($this->db->write($querystr, $query->GetData()) !== 1)
            throw new DeleteFailedException($class);
            
        $this->RemoveObject($object); return $this;
    }
    
    /**
     * Updates the given object in the database
     * @param BaseObject $object object to update
     * @param array<class-string<BaseObject>, FieldTypes\BaseField[]> $fieldsByClass 
        fields to save of object for each table class, order derived->base
     * @throws UpdateFailedException if the update row fails
     * @return $this
     */
    public function UpdateObject(BaseObject $object, array $fieldsByClass) : self
    {
        foreach ($fieldsByClass as $class=>$fields)
        {
            $data = array('id'=>$object->ID()); 
            $sets = array(); $i = 0;
            
            foreach ($fields as $field)
            {
                $key = $field->GetName();
                $val = $field->SaveDBValue();
                
                if ($field instanceof FieldTypes\Counter)
                {
                    $sets[] = "$key=$key+:d$i";
                    $data['d'.$i++] = $val;
                }
                else if ($val !== null) 
                {
                    $sets[] = "$key=:d$i";
                    $data['d'.$i++] = $val;
                }
                else $sets[] = "$key=NULL";
            }
            
            if (empty($sets)) continue; // nothing to update
            
            $setstr = implode(', ',$sets);
            $table = $this->GetClassTableName($class);
            $query = "UPDATE $table SET $setstr WHERE id=:id";
            
            if ($this->db->write($query, $data) !== 1)
                throw new UpdateFailedException($class);
            
            $this->UnsetObjectKeyFields($object, $fields);
            $this->SetObjectKeyFields($object, $fields);
        }
        
        unset($this->created[$object->ID()]);
        unset($this->modified[$object->ID()]);
        
        return $this;
    }
    
    /**
     * Inserts the given object to the database
     * @param BaseObject $object object to insert
     * @param array<class-string<BaseObject>, FieldTypes\BaseField[]> $fieldsByClass 
          fields to save of object for each table class, order derived->base
     * @throws InsertFailedException if the insert row fails
     * @return $this
     */
    public function InsertObject(BaseObject $object, array $fieldsByClass) : self
    {
        foreach (array_reverse($fieldsByClass) as $class=>$fields)
        {
            $columns = array(); $indexes = array();
            $data = array(); $i = 0;
            
            foreach ($fields as $field)
            {
                $key = $field->GetName();
                $val = $field->SaveDBValue();
                
                $columns[] = $key;
                
                if ($val !== null)
                {
                    $indexes[] = ':d'.$i;
                    $data['d'.$i++] = $val;
                }
                else $indexes[] = 'NULL';
            }
            
            $colstr = implode(',',$columns);
            $idxstr = implode(',',$indexes);
            $table = $this->GetClassTableName($class);
            $query = "INSERT INTO $table ($colstr) VALUES ($idxstr)";

            if ($this->db->write($query, $data) !== 1)
                throw new InsertFailedException($class);
            
            $this->SetObjectKeyFields($object, $fields);
        }
        
        $base = $object::GetTableClasses()[0];
        $this->objectsByBase[$base][$object->ID()] = $object;
        
        unset($this->created[$object->ID()]);
        unset($this->modified[$object->ID()]);
        
        return $this;
    }
    
    /** Everything BELOW this point places a basic
     * key-cache layer on top of the ObjectDatabase */
    
    /** Array of class, then non-unique key, then value to object array
     * @var array<class-string<BaseObject>, array<string, array<string, array<string, BaseObject>>>> */
    private array $objectsByKey = array();

    /** Array of object to property key/val being used in objectsByKey
     * @var array<string, array<string, string>> */
    private array $objectsKeyValues = array();
    
    /** array of class, then unique key, then value to object or null
     * @var array<class-string<BaseObject>, array<string, array<string, ?BaseObject>>> */
    private array $uniqueByKey = array();
    
    /** array of object to property key/val being used in uniqueByKey
     * @var array<string, array<string, string>> */
    private array $uniqueKeyValues = array();
    
    /** array of class and property key to its base class
     * @var array<class-string<BaseObject>, array<string, class-string<BaseObject>>> */
    private array $keyBaseClasses = array();
    
    /**
     * Converts a scalar value to a string
     * Want ""/false to be distinct from null
     * @param ?scalar $value index data value
     */
    private static function ValueToIndex($value) : string
    {
        return ($value !== null) ? 'k'.$value : '';
    }
    
    /**
     * Counts objects matching the given key/value (uses cache if loaded)
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return int object count
     */
    public function CountObjectsByKey(string $class, string $key, $value) : int
    {
        $validx = self::ValueToIndex($value);
        
        if (array_key_exists($validx, $this->objectsByKey[$class][$key]))
            return count($this->objectsByKey[$class][$key][(string)$validx]);
        else
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $validx));
            return $this->CountObjectsByQuery($class, $q);
        }
    }

    /**
     * Loads and caches objects matching the given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return array<string, T> loaded objects indexed by ID
     */
    public function LoadObjectsByKey(string $class, string $key, $value) : array
    {
        $validx = self::ValueToIndex($value);
        $this->objectsByKey[$class] ??= array();
        $this->objectsByKey[$class][$key] ??= array();
        
        if (!array_key_exists($validx, $this->objectsByKey[$class][$key]))
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $objs = $this->LoadObjectsByQuery($class, $q);
            
            $this->SetNonUniqueKeyObjects($class, $key, $validx, $objs);
            
            foreach ($objs as $obj)
                $this->objectsKeyValues[(string)$obj][$key] = $validx;
        }

        return $this->objectsByKey[$class][$key][$validx];
    }
    
    /**
     * Deletes objects matching the given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return int number of deleted objects
     */
    public function DeleteObjectsByKey(string $class, string $key, $value) : int
    {
        $validx = self::ValueToIndex($value);
        $this->objectsByKey[$class] ??= array();
        $this->objectsByKey[$class][$key] ??= array();
        
        if (array_key_exists($validx, $this->objectsByKey[$class][$key]))
        {
            $count = count($objs = &$this->objectsByKey[$class][$key][$validx]);
            
            foreach ($objs as $obj) $this->DeleteObject($obj);
        }
        else
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $count = $this->DeleteObjectsByQuery($class, $q);
        }
        
        $this->objectsByKey[$class][$key][$validx] = array();
        
        return $count;
    }

    /**
     * Loads and caches a unique object matching the given unique key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @throws UniqueKeyException if > 1 object is loaded
     * @return ?T loaded object or null
     */
    public function TryLoadUniqueByKey(string $class, string $key, $value) : ?BaseObject
    {
        $validx = self::ValueToIndex($value);
        $this->uniqueByKey[$class] ??= array();
        $this->uniqueByKey[$class][$key] ??= array();
        
        if (!array_key_exists($validx, $this->uniqueByKey[$class][$key]))
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $objs = $this->LoadObjectsByQuery($class, $q);
            if (count($objs) > 1) throw new UniqueKeyException("$class $key");
            
            $obj = (count($objs) === 1) ? array_values($objs)[0] : null;
            
            $this->SetUniqueKeyObject($class, $key, $validx, $obj);
            $this->uniqueKeyValues[(string)$obj][$key] = $validx;
        }

        return $this->uniqueByKey[$class][$key][$validx];
    }
    
    /**
     * Deletes a unique object matching the given unique key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @throws UniqueKeyException if > 1 object is deleted
     * @return bool true if an object was deleted
     */
    public function DeleteUniqueByKey(string $class, string $key, $value) : bool
    {
        $validx = self::ValueToIndex($value);
        $this->uniqueByKey[$class] ??= array();
        $this->uniqueByKey[$class][$key] ??= array();
        
        if (array_key_exists($validx, $this->uniqueByKey[$class][$key]))
        {
            $obj = $this->uniqueByKey[$class][$key][$validx];
            $count = ($obj !== null) ? 1 : 0;
            if ($obj !== null) $this->DeleteObject($obj);
        }
        else
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $count = $this->DeleteObjectsByQuery($class, $q);
            if ($count > 1) throw new UniqueKeyException("$class $key");
        }
        
        $this->uniqueByKey[$class][$key][$validx] = null; return $count > 0;
    }

    /**
     * Sets the given array of objects for a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx value of the key field
     * @param T[] $objs array of objects to set
     * @param ?class-string<T> $bclass base class for property
     */
    private function SetNonUniqueKeyObjects(string $class, string $key, string $validx, array $objs, ?string $bclass = null) : void
    {
        $bclass ??= $class;
        $this->keyBaseClasses[$class][$key] = $bclass;
        $this->objectsByKey[$class][$key][$validx] = $objs;
        
        if (($childmap = $class::GetChildMap()) !== null)
            foreach ($childmap as $child)
        {
            if ($child !== $class)
            {
                $objs2 = array_filter($objs, function(BaseObject $obj)use($child){ return is_a($obj, $child); });
                $this->SetNonUniqueKeyObjects($child, $key, $validx, $objs2, $bclass);
            }    
        }
    }
    
    /**
     * Adds the given object to a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx value of the key field
     * @param T $obj object to add to the array
     * @param ?class-string<T> $bclass base class for property
     */
    private function AddNonUniqueKeyObject(string $class, string $key, string $validx, BaseObject $obj, ?string $bclass = null) : void
    {
        $bclass ??= $class;
        $this->keyBaseClasses[$class][$key] = $bclass;
        $this->objectsByKey[$class][$key][$validx][$obj->ID()] = $obj;
        
        if (($childmap = $class::GetChildMap()) !== null)
            foreach ($childmap as $child)
        {
            if ($child !== $class)
            {
                if ($obj !== null && is_a($obj, $child))
                    $this->AddNonUniqueKeyObject($child, $key, $validx, $obj, $bclass);
            }
        }
    }
    
    /**
     * Sets the given object to a unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx value of the key field
     * @param ?T $obj object to set as the cached object or null
     * @param ?class-string<T> $bclass base class for property
     */
    private function SetUniqueKeyObject(string $class, string $key, string $validx, ?BaseObject $obj, ?string $bclass = null) : void
    {
        $bclass ??= $class;
        $this->keyBaseClasses[$class][$key] = $bclass;
        $this->uniqueByKey[$class][$key][$validx] = $obj;
        
        if (($childmap = $class::GetChildMap()) !== null)
            foreach ($childmap as $child)
        {
            if ($child !== $class)
            {
                $obj2 = ($obj !== null && is_a($obj, $child)) ? $obj : null;
                $this->SetUniqueKeyObject($child, $key, $validx, $obj2, $bclass);
            }
        }
    }
    
    /**
     * Adds the given object's fields to any existing key caches
     * @param BaseObject $object object being created
     * @param FieldTypes\BaseField[] $fields field values
     */
    private function SetObjectKeyFields(BaseObject $object, array $fields) : void
    {
        $objstr = (string)$object;
        $class = get_class($object);
        
        $this->objectsByKey[$class] ??= array();
        $this->uniqueByKey[$class] ??= array();
        
        foreach ($fields as $field)
        {
            $key = $field->GetName();
            $value = $field->GetDBValue();
            $validx = self::ValueToIndex($value);
            
            if (array_key_exists($key, $this->objectsByKey[$class]) &&
                array_key_exists($validx, $this->objectsByKey[$class][$key]))
                // must have loaded for this value since there could be others (non-unique)
            {
                $this->objectsKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->AddNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
            
            if ($value !== null && array_key_exists($key, $this->uniqueByKey[$class]))
            // don't need to have loaded for this value since there can only be one (unique)
            {
                $this->uniqueKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key] ?? $class;
                $this->SetUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
    }
    
    /**
     * Removes an object from a non-unique key cache
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx value of the key field
     * @param T $object object being removed
     */
    private function RemoveNonUniqueKeyObject(string $class, string $key, string $validx, BaseObject $object) : void
    {
        unset($this->objectsByKey[$class][$key][$validx][$object->ID()]);
        
        if (($childmap = $class::GetChildMap()) !== null)
            foreach ($childmap as $child)
        {
            if ($child !== $class)
                $this->RemoveNonUniqueKeyObject($child, $key, $validx, $object);
        }
    }
    
    /**
     * Unsets the value of a unique key cache (to null)
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     * @param string $key name of the key field
     * @param string $validx value of the key field
     */
    private function UnsetUniqueKeyObject(string $class, string $key, string $validx) : void
    {
        $this->uniqueByKey[$class][$key][$validx] = null;
        
        if (($childmap = $class::GetChildMap()) !== null)
            foreach ($childmap as $child)
        {
            if ($child !== $class)
                $this->UnsetUniqueKeyObject($child, $key, $validx);
        }
    }

    /**
     * Removes the given object's fields from any existing key-based caches
     * Uses the cached "old-value" for the fields rather than their current value
     * @param BaseObject $object object being created
     * @param FieldTypes\BaseField[] $fields field values
     */
    private function UnsetObjectKeyFields(BaseObject $object, array $fields) : void
    {
        $objstr = (string)$object;
        $class = get_class($object);
        
        $isObjects = array_key_exists($objstr, $this->objectsKeyValues);
        $isUnique = array_key_exists($objstr, $this->uniqueKeyValues);
        
        if (!$isObjects && !$isUnique) return;
        
        foreach ($fields as $field)
        {
            $key = $field->GetName();
            
            if ($isObjects && array_key_exists($key, $this->objectsByKey[$class]))
            {
                $validx = $this->objectsKeyValues[$objstr][$key];
                unset($this->objectsKeyValues[$objstr][$key]);
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
            
            if ($isUnique && array_key_exists($key, $this->uniqueByKey[$class]))
            {
                $validx = $this->uniqueKeyValues[$objstr][$key];
                unset($this->uniqueKeyValues[$objstr][$key]);
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->UnsetUniqueKeyObject($bclass, $key, $validx);
            }
        }
    }
    
    /**
     * Removes the given object (all fields) from the key-based caches
     * @param BaseObject $object object to remove
     */
    private function UnsetAllObjectKeyFields(BaseObject $object) : void
    {
        $objstr = (string)$object;
        $class = get_class($object);
        
        if (array_key_exists($objstr, $this->objectsKeyValues)) // isObjects
        {
            foreach ($this->objectsKeyValues[$objstr] as $key=>$validx)
            {
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
        
        if (array_key_exists($objstr, $this->uniqueKeyValues)) // isUnique
        {
            foreach ($this->uniqueKeyValues[$objstr] as $key=>$validx)
            {
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->UnsetUniqueKeyObject($bclass, $key, $validx);
            }
        }
        
        unset($this->objectsKeyValues[$objstr]);
        unset($this->uniqueKeyValues[$objstr]);
    }
    
    /**
     * Loads a unique object by its ID
     * @template T of BaseObject
     * @param class-string<T> $class class to load
     * @param string $id the ID of the object
     * @return T|null object or null if not found
     */
    public function TryLoadByID(string $class, string $id) : ?BaseObject
    {
        return $this->TryLoadUniqueByKey($class, 'id', $id);
    }
    
    /**
     * Loads objects with the given object referenced by the given field
     * @template T of BaseObject
     * @param class-string<T> $class class to load
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @return array<string, T> array of objects indexed by their IDs
     */
    public function LoadObjectsByObject(string $class, string $field, BaseObject $object) : array
    {
        return $this->LoadObjectsByKey($class, $field, $object->ID());
    }
    
    /**
     * Loads a unique object with the given object referenced by the given field
     * @template T of BaseObject
     * @param class-string<T> $class class to load
     * @param string $field The name of the field to check
     * @param BaseObject $object the object referenced by the field
     * @return T|null
     */
    public function TryLoadUniqueByObject(string $class, string $field, BaseObject $object) : ?BaseObject
    {
        return $this->TryLoadUniqueByKey($class, $field, $object->ID());
    }
}
