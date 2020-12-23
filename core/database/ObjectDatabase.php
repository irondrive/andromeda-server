<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/Database.php");
require_once(ROOT."/core/database/QueryBuilder.php");

class ObjectTypeException extends DatabaseException         { public $message = "DBOBJECT_TYPE_MISMATCH"; }
class DuplicateUniqueKeyException extends DatabaseException { public $message = "DUPLICATE_DBOBJECT_UNIQUE_VALUES"; }

class ObjectDatabase extends Database
{
    private array $objects = array();     /* array[id => BaseObject] */
    private array $modified = array();    /* array[id => BaseObject] */
    
    public function isModified(BaseObject $obj) : bool 
    { 
        return array_key_exists($obj->ID(), $this->modified); 
    }
    
    public function setModified(BaseObject $obj) : void
    {
        $this->modified[$obj->ID()] = $obj;
    }
     
    public function getLoadedObjects() : array
    { 
        return array_map(function($e){ return get_class($e); }, $this->objects);
    }
    
    public function saveObjects(bool $isRollback = false) : self
    {
        foreach ($this->modified as $object) 
            $object->Save($isRollback);
        return $this;
    }

    public function rollback(bool $canSave = false) : void
    {
        parent::rollBack();
        
        if ($canSave)
        {
            $this->SaveObjects(true);
            parent::commit();
        }
    }

    public static function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class::GetDBClass()); unset($class[0]);
        return "a2_objects_".strtolower(implode('_', $class));
    }
    
    private function Rows2Objects(array $rows, string $class) : array
    {
        $output = array(); 

        foreach ($rows as $row)
        {
            $id = $row['id'];
            
            if (in_array($id, array_keys($this->objects), true))
                $output[$id] = $this->objects[$id];                
            else 
            { 
                $object = new $class($this, $row);
                $output[$id] = $object; $this->objects[$id] = $object; 
            }
        }
        
        return $output;
    }
    
    private function TryPreloadObjectByID(string $class, string $id) : ?BaseObject
    {
        if (array_key_exists($id, $this->objects))
        {
            if (!is_a($this->objects[$id],$class))
            {
                throw new ObjectTypeException("Expected $class, got a ".get_class($this->objects[$id]));
            }
            else return $this->objects[$id];
        } 
        else return null;
    }
    
    public function TryLoadObjectByUniqueKey(string $class, string $field, string $value) : ?BaseObject
    {
        if ($field == 'id' && ($obj = $this->TryPreloadObjectByID($class, $value)) !== null) return $obj;
        
        $query = new QueryBuilder(); $query->Where($query->Equals($field,$value));
        
        $objects = $this->LoadObjectsByQuery($class, $query);
        
        if (!count($objects)) return null;
        else return array_values($objects)[0];
    }    
    
    public function LoadObjectsByQuery(string $class, QueryBuilder $query) : array
    {
        $table = static::GetClassTableName($class); 
        
        $querystr = "SELECT $table.* FROM $table ".$query->GetText();
        
        $result = $this->query($querystr, Database::QUERY_READ, $query->GetData());
        
        $objects = $this->Rows2Objects($result, $class);
        
        return array_filter($objects, function($obj){ return !$obj->isDeleted(); });
    }
    
    public function DeleteObjectsByQuery(string $class, QueryBuilder $query, bool $notify = false) : self
    {
        $table = static::GetClassTableName($class);
        
        $querystr = "DELETE FROM $table ".$query->GetText().(!$notify ? " RETURNING *":"");
        
        $qtype = Database::QUERY_WRITE | (!$notify ? Database::QUERY_READ : 0);
        $result = $this->query($querystr, $qtype, $query->GetData());
        
        if (!$notify) foreach ($this->Rows2Objects($result, $class) as $obj)
        {
            $obj->Delete(); unset($this->modified[$obj->ID()]);
        }
        
        return $this;
    }
    
    protected function DeleteObjectByID(string $class, BaseObject $object) : self
    {
        $q = new QueryBuilder(); return $this->DeleteObjectsByQuery($class, $q->Where($q->Equals('id',$object->ID())), true);
    }
    
    public function SaveObject(string $class, BaseObject $object, array $values, array $counters) : self
    {
        unset($this->modified[$object->ID()]);

        if ($object->isCreated() && $object->isDeleted()) return $this;        
        if ($object->isCreated()) return $this->SaveNewObject($class, $object, $values, $counters);
        if ($object->isDeleted()) return $this->DeleteObjectByID($class, $object);
        
        $criteria = array(); $data = array('id'=>$object->ID()); $i = 0;
        
        foreach ($values as $key=>$value) {
            array_push($criteria, "$key = :d$i");
            $data["d$i"] = $value; $i++;
        }; 
        
        foreach ($counters as $key=>$counter) {
            array_push($criteria, "$key = $key + :d$i");
            $data["d$i"] = $counter; $i++;
        }; 
        
        if (!count($criteria)) return $this;
        
        $criteria_string = implode(', ',$criteria);
        $table = static::GetClassTableName($class);            
        $query = "UPDATE $table SET $criteria_string WHERE id=:id";    
        $this->query($query, Database::QUERY_WRITE, $data);    
        
        return $this;
    }
    
    private function SaveNewObject(string $class, BaseObject $object, array $values, array $counters) : self
    {
        $columns = array(); $indexes = array(); $data = array(); $i = 0;

        $values['id'] = $object->ID();
        $values = array_merge($values, $counters);
        
        foreach ($values as $key=>$value) {
            array_push($columns, $key); 
            array_push($indexes, $value !== null ? ":d$i" : "NULL");
            if ($value !== null) $data["d$i"] = $value; $i++;
        }
        
        $table = static::GetClassTableName($class);
        $columns_string = implode(',',$columns); $indexes_string = implode(',',$indexes);
        $query = "INSERT INTO $table ($columns_string) VALUES ($indexes_string)";
        $this->query($query, Database::QUERY_WRITE, $data);
        
        return $this;
    }

    public function CreateObject(string $class) : BaseObject
    {
        $data = array('id' => Utilities::Random($class::IDLength));       
        return array_values($this->Rows2Objects(array($data), $class))[0];
    }
}

