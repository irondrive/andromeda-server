<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/Database.php");
require_once(ROOT."/core/database/QueryBuilder.php");

/** Exception indicating that the requested class does not match the loaded object */
class ObjectTypeException extends DatabaseException         { public $message = "DBOBJECT_TYPE_MISMATCH"; }

/** Exception indicating that multiple objects were loaded for by unique query */
class DuplicateUniqueKeyException extends DatabaseException { public $message = "MULTIPLE_UNIQUE_OBJECTS"; }

/** Exception indicating that the desired row was not written to */
class RowWriteFailedException extends DatabaseException { public $message = "ROW_WRITE_FAILED"; }

/**
 * Provides the basic interfaces between BaseObject and the underlying PDO database
 *
 * Basic functions include loading (caching), updating, creating, and deleting objects.
 * This class should only be used internally for BaseObjects.
 */
class ObjectDatabase extends Database
{
    /** @var array<string, array<string, BaseObject>> array of loaded objects indexed by class then ID */
    private array $objects = array();

    /** @return array<string, string> list of ID arrays indexed by class */
    public function getLoadedObjectIDs() : array
    { 
        return array_map(function(array $cobjs){
            return array_keys($cobjs);
        }, $this->objects);
    }

    /**
     * Loops through every objects and saves them to the DB
     * @return $this
     */
    public function saveObjects(bool $onlyMandatory = false) : self
    {
        foreach ($this->objects as $objs) foreach ($objs as $obj) 
            $obj->Save($onlyMandatory);
        
        return $this;
    }
    
    /** Return the database table name for a class */
    public function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class::GetDBClass()); unset($class[0]);
        $retval = "a2obj_".strtolower(implode('_', $class));
        if ($this->UsePublicSchema()) $retval = "public.$retval";
        return $retval;
    }
    
    /**
     * Converts database rows into objects
     * @param array $rows rows from the DB, each becoming an object
     * @param string $class the class of object represented by the rows
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    private function Rows2Objects(array $rows, string $class) : array
    {
        $output = array(); 

        foreach ($rows as $row)
        {
            $id = $row['id'];
            
            $dbclass = $class::GetDBClass(); 
            $this->objects[$dbclass] ??= array();
            
            // if this object is already loaded, don't replace it
            if (array_key_exists($id, $this->objects[$dbclass]))
                $output[$id] = $this->objects[$dbclass][$id];
            else 
            {
                $oclass = $class::GetObjClass($row);
                $object = new $oclass($this, $row);
                
                $output[$id] = $object; 
                
                $this->objects[$dbclass][$id] = $object; 
            }
        }
        
        return $output;
    }
    
    /**
     * Attempt to fetch an object from the cache by its ID
     * @param string $class the class of the desired object
     * @param string $id the ID of the object to load
     * @throws ObjectTypeException if the object exists but is a different class
     * @return BaseObject|NULL the object from the cache
     */
    private function TryPreloadObjectByID(string $class, string $id) : ?BaseObject
    {
        $dbclass = $class::GetDBClass(); 
        $this->objects[$dbclass] ??= array();
        
        if (array_key_exists($id, $this->objects[$dbclass]))
        {
            if (!is_a($this->objects[$dbclass][$id],$class))
            {
                throw new ObjectTypeException("$id not $class");
            }
            else return $this->objects[$dbclass][$id];
        } 
        else return null;
    }
    
    /**
     * Attempt to load a unique object by the value of a field
     * 
     * Will try to fetch the object from the cache if loading by ID
     * @param string $class the desired class of the object
     * @param string $field the field to check
     * @param string $value the value of the field to match
     * @throws DuplicateUniqueKeyException if this returns > 1 object
     * @return BaseObject|NULL the object returned by the database
     */
    public function TryLoadObjectByUniqueKey(string $class, string $field, string $value) : ?BaseObject
    {
        if ($field == 'id' && ($obj = $this->TryPreloadObjectByID($class, $value)) !== null) return $obj;
        
        $query = new QueryBuilder(); $query->Where($query->Equals($field,$value));
        
        $objects = $this->LoadObjectsByQuery($class, $query);
        
        $count = count($objects); if (!$count) return null;
        else if ($count > 1) throw new DuplicateUniqueKeyException();
        
        return array_values($objects)[0];
    }    
    
    /**
     * Counts objects using the given query
     * @param string $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @return int count of objects
     */
    public function CountObjectsByQuery(string $class, QueryBuilder $query) : int
    {
        $table = $this->GetClassTableName($class);
        
        $querystr = "SELECT COUNT($table.id) FROM $table ".$query->GetText();
        
        return array_values($this->query($querystr, Database::QUERY_READ, $query->GetData())[0])[0];
    }
    
    /**
     * Loads an array of objects using the given query
     * @param string $class the class of the objects
     * @param QueryBuilder $query the query used to match objects
     * @return array<string, BaseObject> array of objects indexed by their IDs
     */
    public function LoadObjectsByQuery(string $class, QueryBuilder $query) : array
    {
        $table = $this->GetClassTableName($class); 
        
        $querystr = "SELECT $table.* FROM $table ".$query->GetText();
        
        $result = $this->query($querystr, Database::QUERY_READ, $query->GetData());
        
        $objects = $this->Rows2Objects($result, $class);
        
        return array_filter($objects, function($obj){ return !$obj->isDeleted(); });
    }
    
    /**
     * Delete objects matching the given query
     * 
     * The objects will be loaded when deleted and their Delete() will run
     * @param string $class the class of the objects to delete
     * @param QueryBuilder $query the query used to match objects
     * @return int number of deleted objects
     */
    public function DeleteObjectsByQuery(string $class, QueryBuilder $query) : int
    {
        if (!$this->SupportsRETURNING())
        {
            // if we can't use RETURNING, just load the objects and delete individually
            $objs = $this->LoadObjectsByQuery($class, $query);
            
            foreach ($objs as $obj) $obj->Delete(); return count($objs);
        }
        
        $table = $this->GetClassTableName($class);
        
        $querystr = "DELETE FROM $table ".$query->GetText()." RETURNING *";

        $qtype = Database::QUERY_WRITE | Database::QUERY_READ;
        $result = $this->query($querystr, $qtype, $query->GetData());
        
        $objs = $this->Rows2Objects($result, $class);
        
        // notify all objects of deletion
        foreach ($objs as $obj) $obj->setDeleted()->Delete();
        
        return count($objs);
    }
    
    /**
     * Deletes a single object from the database (only to be called by the object itself)
     * 
     * The delete query happens immediately, not waiting for the object to be saved.
     * @param BaseObject $object the object to delete
     * @return $this
     */
    public function DeleteObject(BaseObject $object) : self
    {        
        $q = new QueryBuilder(); $q->Where($q->Equals('id',$object->ID()));  
        
        $table = $this->GetClassTableName(get_class($object));        
        $querystr = "DELETE FROM $table ".$q->GetText();
        
        if ($this->query($querystr, Database::QUERY_WRITE, $q->GetData()) !== 1)
            throw new RowWriteFailedException();
        
        return $this;
    }
    
    /**
     * UPDATEs an object in the database with the given data
     * @param BaseObject $object the object to update
     * @param array $values columns to be overwritten using =
     * @param array $counters columns to be incremented using +=
     * @return $this
     */
    public function SaveObject(BaseObject $object, array $values, array $counters) : self
    {    
        if ($object->isCreated()) return $this->SaveNewObject($object, array_merge($values, $counters));
        
        $criteria = array(); $data = array('id'=>$object->ID()); $i = 0;
        
        foreach ($values as $key=>$value) {
            $criteria[] = "$key = :d$i";
            $data["d$i"] = $value; $i++;
        }; 
        
        foreach ($counters as $key=>$counter) {
            $criteria[] = "$key = $key + :d$i";
            $data["d$i"] = $counter; $i++;
        }; 
        
        if (!count($criteria)) return $this;
        
        $criteria_string = implode(', ',$criteria);
        $table = $this->GetClassTableName(get_class($object));            
        $query = "UPDATE $table SET $criteria_string WHERE id=:id";    
        
        if ($this->query($query, Database::QUERY_WRITE, $data) !== 1)
            throw new RowWriteFailedException();
        
        return $this;
    }
    
    /**
     * INSERTs and new object into the database with the given data
     * @param BaseObject $object the created object to be saved
     * @param array $values the values of each column
     * @return $this
     */
    private function SaveNewObject(BaseObject $object, array $values) : self
    {
        $columns = array(); $indexes = array(); $data = array(); $i = 0;

        $values['id'] = $object->ID();
        
        foreach ($values as $key=>$value) {
            $columns[] = $key; 
            $indexes[] = ($value !== null ? ":d$i" : "NULL");
            if ($value !== null) $data["d$i"] = $value; $i++;
        }
        
        $table = $this->GetClassTableName(get_class($object));
        $columns_string = implode(',',$columns); $indexes_string = implode(',',$indexes);
        $query = "INSERT INTO $table ($columns_string) VALUES ($indexes_string)";
        
        if ($this->query($query, Database::QUERY_WRITE, $data) !== 1)
            throw new RowWriteFailedException();
        
        return $this;
    }

    /**
     * Creates a new object of the given class
     * 
     * The object will not actually exist in the DB until Save() is called,
     * and its fields will have only the defaults given in its field template
     * @param string $class the desired class of the new object
     * @return BaseObject the newly created object
     */
    public function CreateObject(string $class) : BaseObject
    {
        $data = array('id' => Utilities::Random($class::IDLength));       
        return array_values($this->Rows2Objects(array($data), $class))[0];
    }
}

