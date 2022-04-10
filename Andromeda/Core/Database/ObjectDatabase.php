<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/BaseObject.php");
require_once(ROOT."/Core/Database/FieldTypes.php");

/** Andromeda cannot rollback and then commit/save since database/objects state is not sufficiently reset */
class SaveAfterRollbackException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SAVE_AFTER_ROLLBACK", $details);
    }
}

/** Exception indicating that the requested class does not match the loaded object */
class ObjectTypeException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DBOBJECT_TYPE_MISMATCH", $details);
    }
}

/** Exception indicating that multiple objects were loaded for by-unique query */
class MultipleUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("MULTIPLE_UNIQUE_OBJECTS", $details);
    }
}

/** Exception indicating the given unique key is not registered for this class */
class UnknownUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_UNIQUE_KEY", $details);
    }
}

/** Exception indicating that null was given as a unique key value */
class NullUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("NULL_UNIQUE_VALUE", $details);
    }
}

/** Base exception indicating that something went wrong due to concurrency, try again */
abstract class ConcurrencyException extends Exceptions\ServiceUnavailableException { }

/** Exception indicating that the update failed to match any objects */
class UpdateFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_UPDATE_FAILED", $details);
    }
}

/** Exception indicating that the row insert failed */
class InsertFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_INSERT_FAILED", $details);
    }
}

/** Exception indicating that the row delete failed */
class DeleteFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_DELETE_FAILED", $details);
    }
}

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
        
        $countChildren = empty($classes); // no topclass
        
        if (!$countChildren)
        {
            $topclass = $classes[array_key_last($classes)];
            $countChildren = ($class !== $topclass && !$topclass::HasTypedRows());
        }
        
        if ($countChildren) // count foreach child
        {
            if (($childmap = $class::GetChildMap()) === null)
                throw new NoBaseTableException($class);
            
            $count = 0; foreach ($childmap as $child)
            {
                if ($class === $child) throw new NoBaseTableException($class);
                $count += $this->CountObjectsByQuery($child, $query);
            }
            return $count;
        }
        else // count from the table directly
        {
            $query = clone $query;

            $this->GetFromAndSetJoins($class, $query, false);
            
            // if not the top table, filter only the type we want
            if (($topclass = $classes[array_key_last($classes)]) !== $class)
                $query->Where($topclass::GetWhereChild($this, $query, $class));

            $ftable = $this->GetClassTableName($classes[0]);
            $querystr = "SELECT ".($prop = "COUNT($ftable.id)")." FROM $ftable ".$query->GetText();
        
            return $this->db->read($querystr, $query->GetData())[0][$prop];
        }
    }

    /**
     * Loads an array of objects matching the given query
     * If using ORDER BY and there are child tables, the returned array may not be sorted!
     * But, the ORDER BY will apply correctly as far as LIMIT/OFFSET and WHAT gets loaded
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @param ?class-string<T> $baseClass the base class being loaded (internal!)
     * @return array<string, T> array of objects indexed by their IDs
     */
    public function LoadObjectsByQuery(string $class, QueryBuilder $query, ?string $baseClass = null) : array
    {
        $baseClass ??= $class;
        $childmap = $class::GetChildMap();
        $castRows = self::CanLoadByUpcast($class);
        
        $doChildren = !$castRows && $childmap !== null;
        $doSelf = !$doChildren;
        $objects = array();
        
        if ($doChildren) // need to load for each child with a table
        {
            foreach ($childmap as $child)
            {
                if ($child === $class) { $doSelf = true; continue; }
                $objs = $this->LoadObjectsByQuery($child, $query, $baseClass);
                foreach ($objs as $obj) $objects[$obj->ID()] = $obj; // merge
            }
        }
        
        if ($doSelf) // if concrete, do an actual load for this class
        {
            $selfQuery = clone $query;
            
            $this->SetTypeFiltering($class, $baseClass, $selfQuery, !$castRows);
            $selstr = $this->GetFromAndSetJoins($class, $selfQuery, true);
            $querystr = 'SELECT '.$selstr.' '.$selfQuery->GetText();
            
            foreach ($this->db->read($querystr, $selfQuery->GetData()) as $row)
            {
                $object = $this->ConstructObject($class, $row, $castRows);
                $objects[$object->ID()] = $object;
            }
        }

        return $objects;
    }

    /**
     * Immediately delete objects matching the given query
     * The objects will be loaded and have their notifyDeleted() run
     * If using ORDER BY and there are child tables, objects may not be deleted in order!
     * But, the ORDER BY will apply correctly as far as LIMIT/OFFSET and WHAT gets deleted
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects to delete
     * @param QueryBuilder $query the query used to match objects
     * @param ?class-string<T> $baseClass the base class being loaded (internal!)
     * @return int number of deleted objects
     */
    public function DeleteObjectsByQuery(string $class, QueryBuilder $query, ?string $baseClass = null) : int
    {
        $baseClass ??= $class;
        
        if (!$this->db->SupportsRETURNING())
        {
            // if we can't use RETURNING, just load the objects and delete individually
            $objs = $this->LoadObjectsByQuery($class, $query, $baseClass);
            foreach ($objs as $obj) $this->DeleteObject($obj);
            
            return count($objs);
        }
        
        $childmap = $class::GetChildMap();
        $castRows = self::CanLoadByUpcast($class);
        
        $doChildren = !$castRows && $childmap !== null;
        $doSelf = !$doChildren;
        $count = 0;
        
        if ($doChildren) // need to delete for each child with a table
        {
            foreach ($childmap as $child)
            {
                if ($child === $class) { $doSelf = true; continue; }
                $count += $this->DeleteObjectsByQuery($child, $query, $baseClass);
            }
        }
        
        if ($doSelf) // if concrete, do an actual delete for this class
        {
            $selfQuery = clone $query;
            
            $this->SetTypeFiltering($class, $baseClass, $selfQuery, !$castRows);
            $selstr = $this->GetFromAndSetJoins($class, $selfQuery, false);
            $querystr = 'DELETE '.$selstr.' '.$selfQuery->GetText().' RETURNING *';
            
            $rows = $this->db->readwrite($querystr, $selfQuery->GetData());
            $count += count($rows);
            
            foreach ($rows as $row)
            {
                $object = $this->ConstructObject($class, $row, $castRows);
                $this->RemoveObject($object);
            }
        }
        
        return $count;
    }

    /**
     * Loads a unique object matching the given query
     * @see ObjectDatabase::LoadObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param QueryBuilder $query the query used to match objects
     * @throws MultipleUniqueKeyException if > 1 object is loaded
     * @return ?T loaded object or null
     */
    public function TryLoadUniqueByQuery(string $class, QueryBuilder $query) : ?BaseObject // TODO unit test
    {
        $objs = $this->LoadObjectsByQuery($class, $query);
        if (count($objs) > 1) throw new MultipleUniqueKeyException("$class");
        return (count($objs) === 1) ? array_values($objs)[0] : null;
    }
    
    
    /**
     * Deletes a unique object matching the given query
     * @see ObjectDatabase::DeleteObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param QueryBuilder $query the query used to match objects
     * @throws MultipleUniqueKeyException if > 1 object is loaded
     * @return bool true if an object was deleted
     */
    public function TryDeleteUniqueByQuery(string $class, QueryBuilder $query) : bool // TODO unit test
    {
        $count = $this->DeleteObjectsByQuery($class, $query);
        if ($count > 1) throw new MultipleUniqueKeyException("$class");
        return $count !== 0;
    }

    /**
     * Returns true iff the given class should be loaded by upcasting rows
     * to child classes rather than doing per-child queries
     * @param class-string<BaseObject> $class class to be loaded
     * @return bool true iff every child has no table or children (is final)
     */
    private static function CanLoadByUpcast(string $class) : bool
    {
        if (!$class::HasTypedRows()) return false;
        
        $childmap = $class::GetChildMap();
        if ($childmap === null) return false;
        
        $tables = $class::GetTableClasses();
        if (empty($tables)) return false;
        
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
     * Compiles JOINs for a class and returns the FROM table list
     * @template T of BaseObject
     * @param class-string<T> $class class name to build a query for
     * @param QueryBuilder $query pre-existing query to add JOINS to
     * @param bool $selectFields if true, add .* after SELECT table names
     * @return string (table list) FROM (base table)
     */
    private function GetFromAndSetJoins(string $class, QueryBuilder $query, bool $selectFields) : string
    {
        $classes = $class::GetTableClasses();
        if (empty($classes)) throw new NoBaseTableException($class);
        
        // specify which tables to select in case of extra joins
        $selstr = implode(', ',array_map(function(string $sclass)use($selectFields){
            return $this->GetClassTableName($sclass).($selectFields?'.*':''); }, $classes));
        
        // join together every base class table
        $bclass = null; foreach ($classes as $pclass)
        {
            if ($bclass !== null) // join base -> parent (child fields override)
                $query->Join($this, $pclass,'id', $bclass,'id');
            $bclass = $pclass;
        }
        
        return $selstr.' FROM '.$this->GetClassTableName($classes[0]);
    }
    
    /**
     * Sets/fixes the WHERE/LIMIT/OFFSET/ORDER for polymorphism
     * @template T of BaseObject
     * @param class-string<T> $class class name to build a query for
     * @param class-string<T> $bclass the base class the user requested
     * @param QueryBuilder $query pre-existing query to add to (may modify!)
     * @param bool $selectType if true, allow selecting where child type
     */
    private function SetTypeFiltering(string $class, string $bclass, QueryBuilder $query, bool $selectType) : void
    {       
        $classes = $class::GetTableClasses();
        if (empty($classes)) throw new NoBaseTableException($class);
        
        // we want the limit/offset to apply to the class being loaded as,
        // not the current child table... use a subquery to accomplish this
        if (($query->GetLimit() !== null || $query->GetOffset() !== null) &&
            ($class !== $bclass || ($class::HasTypedRows() && $selectType)))
        {            
            // the limited base class must have a table
            $bclasses = $bclass::GetTableClasses();
            if (empty($bclasses) || $bclass !== $bclasses[array_key_last($bclasses)])
                throw new NoBaseTableException($bclass);
            
            $subquery = clone $query;
            
            $query->Where(null)->Limit(null)->Offset(null)->OrderBy(null);

            $this->GetFromAndSetJoins($bclass, $subquery, false);
            $btable = $this->GetClassTableName($bclass);
            
            $query->Where("$btable.id IN (SELECT id FROM (SELECT $btable.id FROM $btable $subquery) AS t)");
        }
        
        // if not the top table, filter only the type we want
        $topclass = $classes[array_key_last($classes)];
        if ($selectType && $topclass::HasTypedRows())
            $query->Where($topclass::GetWhereChild($this, $query, $class));
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
        
        $base = $class::GetBaseTableClass();
        $this->objectsByBase[$base] ??= array();
        
        // if this object is already loaded, don't replace it
        if (array_key_exists($id, $this->objectsByBase[$base]))
            $retobj = $this->objectsByBase[$base][$id];
        else
        {
            if ($upcast) $class = $class::GetRowClass($row);
            
            $retobj = new $class($this, $row);
            $this->objectsByBase[$base][$id] = $retobj;
            
            $this->RegisterUniqueKeys($base);
            $this->SetUniqueKeysFromData($retobj, $row);
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
        
        $base = $object::GetBaseTableClass();
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
        $basetbl = $this->GetClassTableName($object::GetBaseTableClass());
        $query->Where($query->Equals($basetbl.'.id',$object->ID()));
        
        $class = get_class($object);
        $selstr = $this->GetFromAndSetJoins($class, $query, false);
        $querystr = 'DELETE '.$selstr.' '.$query;
        
        if ($this->db->write($querystr, $query->GetData()) !== 1)
            throw new DeleteFailedException($class);
            
        $this->RemoveObject($object); return $this;
    }
    
    /**
     * Updates the given object in the database
     * @param BaseObject $object object to update
     * @param array<class-string<BaseObject>, FieldTypes\BaseField[]> $fieldsByClass 
        fields to save of object for each table class, order derived->base (modified only)
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
                $val = $field->GetDBValue();
                $field->ResetDelta();
                
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
          fields to save of object for each table class, order derived->base (ALL)
     * @throws InsertFailedException if the insert row fails
     * @return $this
     */
    public function InsertObject(BaseObject $object, array $fieldsByClass) : self
    {
        $base = $object::GetBaseTableClass();
        
        $this->RegisterUniqueKeys($base);
        $this->objectsByBase[$base][$object->ID()] = $object;
        
        foreach (array_reverse($fieldsByClass) as $class=>$fields)
        {
            $columns = array(); $indexes = array();
            $data = array(); $i = 0;
            
            foreach ($fields as $field)
            {
                $key = $field->GetName();
                $val = $field->GetDBValue();
                
                if ($key === 'id' || $field->isModified())
                {
                    $field->ResetDelta();
                    $columns[] = $key;
                    $indexes[] = ':d'.$i;
                    $data['d'.$i++] = $val;
                }
            }
            
            $colstr = implode(',',$columns);
            $idxstr = implode(',',$indexes);
            $table = $this->GetClassTableName($class);
            $query = "INSERT INTO $table ($colstr) VALUES ($idxstr)";

            if ($this->db->write($query, $data) !== 1)
                throw new InsertFailedException($class);
            
            $this->SetObjectKeyFields($object, $fields);
        }
        
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
     * Converts a scalar value to a unique string
     * @param ?scalar $value index data value
     */
    private static function ValueToIndex($value) : string
    {
        // want ""/false to be distinct from null
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
     * Also caches results so it only calls the DB once for a given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return array<string, T> loaded objects indexed by ID
     */
    public function LoadObjectsByKey(string $class, string $key, $value) : array
    {
        $validx = self::ValueToIndex($value);
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
     * Also updates the cache so the DB is not called if this key/value is loaded
     * @template T of BaseObject
     * @param class-string<T> $class class name of the objects
     * @param string $key data key to match
     * @param ?scalar $value data value to match
     * @return int number of deleted objects
     */
    public function DeleteObjectsByKey(string $class, string $key, $value) : int
    {
        $validx = self::ValueToIndex($value);
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
     * Also caches results so it only calls the DB once for a given key/value
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @throws NullUniqueKeyException if $value is null
     * @throws UnknownUniqueKeyException if the key is not registered
     * @throws MultipleUniqueKeyException if > 1 object is loaded
     * @return ?T loaded object or null
     */
    public function TryLoadUniqueByKey(string $class, string $key, $value) : ?BaseObject
    {
        if ($value === null) throw new NullUniqueKeyException("$class $key"); /** @phpstan-ignore-line */
        
        $this->RegisterUniqueKeys($class);
        
        if (!array_key_exists($key, $this->uniqueByKey[$class]))
            throw new UnknownUniqueKeyException("$class $key");
        
        $validx = self::ValueToIndex($value);

        if (!array_key_exists($validx, $this->uniqueByKey[$class][$key]))
        {
            $q = new QueryBuilder(); $q->Where($q->Equals($key, $value));
            $objs = $this->LoadObjectsByQuery($class, $q);
            
            if (count($objs) > 1) throw new MultipleUniqueKeyException("$class $key");
            $obj = (count($objs) === 1) ? array_values($objs)[0] : null;
            
            $this->SetUniqueKeyObject($class, $key, $validx, $obj);
            $this->uniqueKeyValues[(string)$obj][$key] = $validx;
        }

        return $this->uniqueByKey[$class][$key][$validx];
    }
    
    /**
     * Deletes a unique object matching the given unique key/value
     * Also updates the cache so the DB is not called if this key/value is loaded
     * @template T of BaseObject
     * @param class-string<T> $class class name of the object
     * @param string $key data key to match
     * @param scalar $value data value to match
     * @throws NullUniqueKeyException if $value is null
     * @throws UnknownUniqueKeyException if the key is not registered
     * @throws MultipleUniqueKeyException if > 1 object is loaded
     * @return bool true if an object was deleted
     */
    public function TryDeleteUniqueByKey(string $class, string $key, $value) : bool // TODO unit test
    {
        if ($value === null) throw new NullUniqueKeyException("$class $key"); /** @phpstan-ignore-line */
        
        $this->RegisterUniqueKeys($class);
        
        if (!array_key_exists($key, $this->uniqueByKey[$class]))
            throw new UnknownUniqueKeyException("$class $key");
            
        $validx = self::ValueToIndex($value);

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
            if ($count > 1) throw new MultipleUniqueKeyException("$class $key");
        }
        
        $this->uniqueByKey[$class][$key][$validx] = null; 
        
        return $count !== 0;
    }
    
    /**
     * Sets the registered base class for a class key if not already set or lower than existing
     * @param string $class class to register the key for
     * @param string $key key name being registered
     * @param string $bclass key's base class to register
     */
    private function SetKeyBaseClass(string $class, string $key, string $bclass) : void
    {
        if (!array_key_exists($key, $this->keyBaseClasses[$class] ?? array()) ||
            is_a($this->keyBaseClasses[$class][$key], $bclass, true))
        {
            $this->keyBaseClasses[$class][$key] = $bclass;
        }
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
        $this->SetKeyBaseClass($class, $key, $bclass);
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
        $this->SetKeyBaseClass($class, $key, $bclass);
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
     * Registers a class's unique keys so caching can happen
     * @template T of BaseObject
     * @param class-string<T> $class class being cached (recurses on children)
     */
    private function RegisterUniqueKeys(string $class) : void
    {
        if (!array_key_exists($class, $this->uniqueByKey))
        {
            $this->uniqueByKey[$class] = array();
            
            foreach ($class::GetUniqueKeys() as $bclass=>$keys) 
                foreach ($keys as $key)
            {
                $this->SetKeyBaseClass($class, $key, $bclass);
                $this->uniqueByKey[$class][$key] = array();
                
                if (($childmap = $class::GetChildMap()) !== null)
                    foreach ($childmap as $child)
                {
                    if ($child !== $class)
                        $this->RegisterUniqueKeys($child);
                }
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
        $this->SetKeyBaseClass($class, $key, $bclass);
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
     * Adds the given object's row data to any existing unique key caches
     * @param BaseObject $object object being created
     * @param array<string, mixed> $data row from database
     */
    private function SetUniqueKeysFromData(BaseObject $object, array $data) : void
    {
        $objstr = (string)$object;
        $class = get_class($object);
        
        foreach ($data as $key=>$value)
        {
            if ($value !== null && array_key_exists($key, $this->uniqueByKey[$class]))
            {
                $validx = self::ValueToIndex($value);
                $this->uniqueKeyValues[$objstr][$key] = $validx;
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->SetUniqueKeyObject($bclass, $key, $validx, $object);
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
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->SetUniqueKeyObject($bclass, $key, $validx, $object);
            }
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
     * Removes the fields for the given object from any existing key-based caches
     * Uses the cached "old-value" for the fields rather than their current value
     * @param BaseObject $object object being created
     * @param FieldTypes\BaseField[] $fields field values
     */
    private function UnsetObjectKeyFields(BaseObject $object, array $fields) : void
    {
        $objstr = (string)$object;
        $class = get_class($object);

        $this->objectsKeyValues[$objstr] ??= array();
        $this->uniqueKeyValues[$objstr] ??= array();

        foreach ($fields as $field)
        {
            $key = $field->GetName();
            
            if (array_key_exists($key, $this->objectsKeyValues[$objstr]))
            {
                $validx = $this->objectsKeyValues[$objstr][$key];
                unset($this->objectsKeyValues[$objstr][$key]);
                
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
            
            if (array_key_exists($key, $this->uniqueKeyValues[$objstr]))
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
        
        if (array_key_exists($objstr, $this->objectsKeyValues))
        {
            foreach ($this->objectsKeyValues[$objstr] as $key=>$validx)
            {
                $bclass = $this->keyBaseClasses[$class][$key];
                $this->RemoveNonUniqueKeyObject($bclass, $key, $validx, $object);
            }
        }
        
        if (array_key_exists($objstr, $this->uniqueKeyValues))
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
     * Deletes all objects of a class
     * @see ObjectDatabase::LoadObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class class name
     * @return array<string, T> array of objects indexed by their IDs
     */
    public function LoadAll(string $class) : array
    {
        return $this->LoadObjectsByQuery($class, new QueryBuilder());
    }
    
    /**
     * Immediately delete all objects of class
     * @see ObjectDatabase::DeleteObjectsByQuery()
     * @template T of BaseObject
     * @param class-string<T> $class the class of the objects to delete
     * @return int number of deleted objects
     */
    public function DeleteAll(string $class) : int
    {
        return $this->DeleteObjectsByQuery($class, new QueryBuilder());
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
