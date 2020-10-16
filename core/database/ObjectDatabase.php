<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Database;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class ObjectTypeException extends Exceptions\ServerException            { public $message = "DBOBJECT_TYPE_MISMATCH"; }
class DuplicateUniqueKeyException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBOBJECT_UNIQUE_VALUES"; }

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
        if ($obj->isDeleted()) return;
        $this->modified[$obj->ID()] = $obj;
    }
     
    public function getLoadedObjects() : array
    { 
        return array_map(function($e){ return get_class($e); }, $this->objects);
    }
    
    public function saveObjects(bool $isRollback = false) : void
    {
        foreach ($this->modified as $object) $object->Save($isRollback);
    }
    
    public function commit(bool $dryrun = false) : void
    {
        $this->SaveObjects();        
        if (!$dryrun) parent::commit(); 
        else parent::rollBack();
    }
    
    public function rollback() : void
    {
        parent::rollBack();
        $this->SaveObjects(true);
        parent::commit();
    }

    public static function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class); unset($class[0]);
        return '`'.Config::PREFIX."objects_".strtolower(implode('_', $class)).'`';
    }
    
    private function Rows2Objects(array $rows, string $class) : array
    {
        $output = array(); 

        foreach ($rows as $row)
        {
            $id = $row['id'];
            
            if (in_array($id, array_keys($this->objects)))
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
    
    public function LoadObjectsByQuery(string $class, string $query, array $criteria, ?int $limit = null) : array
    {           
        $table = self::GetClassTableName($class); 
        
        $query = "SELECT $table.* FROM $table $query".($limit !== null ? " LIMIT $limit" : "");
        
        $result = $this->query($query, $criteria, Database::QUERY_READ);
        
        return $this->Rows2Objects($result, $class);
    }
    
    public function DeleteObjectsByQuery(string $class, string $query, array $criteria, ?int $limit = null, bool $notify = false) : self
    {
        $table = self::GetClassTableName($class);
        
        $query = "DELETE FROM $table $query".($limit !== null ? " LIMIT $limit":"").(!$notify ? " RETURNING *":"");
        
        $qtype = Database::QUERY_WRITE | (!$notify ? Database::QUERY_READ : 0);
        $result = $this->query($query, $criteria, $qtype);

        if (!$notify) foreach ($this->Rows2Objects($result, $class) as $obj) 
        {
            $obj->Delete(); unset($this->modified[$obj->ID()]);
        }
        
        return $this;
    }
    
    public static function BuildJoinQuery(string $joinclass, string $joinclassprop, string $destclass, string $classprop) : string
    {
        $joinclass = self::GetClassTableName($joinclass); $destclass = self::GetClassTableName($destclass);
        return "JOIN $joinclass ON $joinclass.$joinclassprop = $destclass.$classprop ";        
    }    
    
    public static function BuildMatchAllWhereQuery(array &$data, ?array $values, bool $like = false) : string
    {
        $criteria = array(); $i = 0; $s = $like ? 'LIKE' : '=';
        
        if ($values !== null) foreach (array_keys($values) as $key) {
            array_push($criteria, "$key ".($values[$key] !== null ? "$s :dat$i" : "IS NULL"));
            if ($values[$key] !== null) $data["dat$i"] = $values[$key]; $i++;
        };
        
        $criteria_string = implode(' AND ',$criteria);
        return ($criteria_string?"WHERE $criteria_string":"");
    }
    
    public static function BuildMatchAnyWhereQuery(array &$data, string $key, array $values, bool $like = false) : string
    {
        $criteria = array(); $i = 0; $s = $like ? 'LIKE' : '=';
        
        foreach ($values as $value) {
            array_push($criteria, "$key ".($value !== null ? "$s :dat$i" : "IS NULL"));
            if ($value !== null) $data["dat$i"] = $value; $i++;
        }
        
        $criteria_string = implode(' OR ', $criteria);
        return ($criteria_string?"WHERE $criteria_string":"");
    }
    
    public function TryLoadObjectByUniqueKey(string $class, string $field, string $value) : ?BaseObject
    {        
        if ($field == 'id' && ($obj = $this->TryPreloadObjectByID($class, $value)) !== null) return $obj;

        $data = array(); $query = self::BuildMatchAllWhereQuery($data, array($field=>$value));
        $objects = $this->LoadObjectsByQuery($class, $query, $data);

        if (!count($objects)) return null;
        else return array_values($objects)[0];
    }
    
    public function LoadObjectsMatchingAny(string $class, string $field, array $values, bool $like = false, 
                                           ?int $limit = null, ?string $joinstr = null) : array
    {
        $data = array(); $query = ($joinstr??"").self::BuildMatchAnyWhereQuery($data, $field, $values, $like);       
        return $this->LoadObjectsByQuery($class, $query, $data, $limit);
    }
    
    public function LoadObjectsMatchingAll(string $class, ?array $values, bool $like = false, 
                                           ?int $limit = null, ?string $joinstr = null) : array
    {        
        $data = array(); $query = ($joinstr??"").self::BuildMatchAllWhereQuery($data, $values, $like);
        return $this->LoadObjectsByQuery($class, $query, $data, $limit);
    }
    
    public function DeleteObjectsMatchingAny(string $class, string $field, array $values, bool $like = false, 
                                             ?int $limit = null, bool $notify = false) : self
    {
        $data = array(); $query = self::BuildMatchAnyWhereQuery($data, $field, $values, $like);
        return $this->DeleteObjectsByQuery($class, $query, $data, $limit, $notify);
    }
    
    public function DeleteObjectsMatchingAll(string $class, ?array $values, bool $like = false, 
                                             ?int $limit = null, bool $notify = false) : self
    {
        $data = array(); $query = self::BuildMatchAllWhereQuery($data, $values, $like);
        return $this->DeleteObjectsByQuery($class, $query, $data, $limit, $notify);
    }
    
    public function SaveObject(string $class, BaseObject $object, array $values, array $counters) : self
    {
        unset($this->modified[$object->ID()]);

        if ($object->isCreated() && $object->isDeleted()) return $this;        
        if ($object->isCreated()) return $this->SaveNewObject($class, $object, $values, $counters);
        if ($object->isDeleted()) return $this->DeleteObjectsMatchingAll($class, array('id'=>$object->ID()), false, null, true);
        
        $criteria = array(); $data = array('id'=>$object->ID()); $i = 0;
        
        foreach (array_keys($values) as $key) {
            array_push($criteria, "$key = :dat$i");
            $data["dat$i"] = $values[$key]; $i++;
        }; 
        
        foreach (array_keys($counters) as $key) {
            array_push($criteria, "$key = $key + :dat$i");
            $data["dat$i"] = $counters[$key]; $i++;
        }; 
        
        if (!count($criteria)) return $this;
        
        $criteria_string = implode(', ',$criteria);
        $table = self::GetClassTableName($class);            
        $query = "UPDATE $table SET $criteria_string WHERE id=:id";    
        $this->query($query, $data, Database::QUERY_WRITE);    
        
        return $this;
    }
    
    private function SaveNewObject(string $class, BaseObject $object, array $values, array $counters) : self
    {
        $columns = array(); $indexes = array(); $data = array(); $i = 0;

        $values['id'] = $object->ID();
        $values = array_merge($values, $counters);
        
        foreach (array_keys($values) as $key) {
            array_push($columns, $key); 
            array_push($indexes, $values[$key] !== null ? ":dat$i" : "NULL");
            if ($values[$key] !== null) $data["dat$i"] = $values[$key]; $i++;
        }
        
        $table = self::GetClassTableName($class);
        $columns_string = implode(',',$columns); $indexes_string = implode(',',$indexes);
        $query = "INSERT INTO $table ($columns_string) VALUES ($indexes_string)";
        $this->query($query, $data, Database::QUERY_WRITE);
        
        return $this;
    }

    public function CreateObject(string $class) : BaseObject
    {
        $data = array('id' => Utilities::Random(BaseObject::IDLength));       
        return array_values($this->Rows2Objects(array($data), $class))[0];
    }
}







