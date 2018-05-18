<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Database;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

class ObjectTypeException extends Exceptions\ServerException            { public $message = "DBOBJECT_TYPE_MISMATCH"; }
class DuplicateUniqueKeyException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBOBJECT_UNIQUE_VALUES"; }
class UniqueKeyWithSpacesException extends Exceptions\ServerException   { public $message = "UNIQUE_KEY_LOAD_WITH_SPACE"; }

class ObjectDatabase extends Database
{
    private $objects = array();     /* array[id => BaseObject] */
    private $modified = array();    /* array[id => BaseObject] */
    private $uniques = array();     /* array[uniquekey => BaseObject] */
    private $columns = array();     /* array[class => array(fields)] */

    public function isModified(BaseObject $obj) : bool 
    { 
        return array_key_exists($obj->ID(), $this->modified); 
    }
    
    public function setModified(BaseObject $obj) 
    { 
        $this->modified[$obj->ID()] = $obj;
    }
    
    private function unsetObject(BaseObject $obj)
    {
        unset($this->objects[$obj->ID()]);
        
        if ($this->isModified($obj)) unset($this->modified[$obj->ID()]);
    }
    
    public function getLoadedObjects() : array
    { 
        return array_map(function($e){ return $e->GetClass(); }, $this->objects);
    }
    
    public function commit(bool $dryrun = false) : void
    {
        foreach ($this->modified as $object) $object->Save();        
        
        if (!$dryrun) parent::commit(); else parent::rollBack();
    }
    
    public static function GetFullClassName(string $class) : string
    {
        return "Andromeda\\$class";
    }
    
    public static function GetShortClassName(string $class) : string
    {
        $class = explode('\\',$class); unset($class[0]); return implode('\\',$class); 
    }
    
    public static function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class); unset($class[0]);
        return '`'.Config::PREFIX."objects_".strtolower(implode('_', $class)).'`';
    }
    
    private function Rows2Objects(array $rows, string $class, $replace = false) : array
    {
        $output = array(); 
        
        foreach ($rows as $row)
        {
            $object = new $class($this, $row); $id = $object->ID();
            
            if (!array_key_exists($class, $this->columns))
                $this->columns[$class] = array_keys($row);

            if (!$replace && in_array($id, array_keys($this->objects)))
                $output[$id] = $this->objects[$id];
            
            else { $output[$id] = $object; $this->objects[$id] = $object; }
        }       
        
        return $output; 
    }
    
    public function LoadObjects(string $class, string $query, array $criteria, ?int $limit = null) : array
    {
        if (array_key_exists('id',$criteria) && array_key_exists($criteria['id'], $this->objects))
        {
            if (!is_a($this->objects[$criteria['id']],$class)) 
            {
                throw new ObjectTypeException("Expected $class, got a ".get_class($this->objects[$criteria['id']])); 
            }
            return array($this->objects[$criteria['id']]);
        }
        
        $loaded = array(); $table = self::GetClassTableName($class); 
        
        $query = "SELECT $table.* FROM $table $query".($limit !== null ? " LIMIT $limit" : "");
        
        $result = $this->query($query, $criteria);
        
        return $this->Rows2Objects($result, $class, array_key_exists('id',$criteria));
    }
    
    public function TryLoadObjectByUniqueKey(string $class, string $field, string $value) : ?BaseObject
    {
        if (strpos($field," ") !== false) throw new UniqueKeyWithSpacesException();
        
        $unique = "$class\n$field\n$value"; if (array_key_exists($unique, $this->uniques)) return $this->uniques[$unique];

        $tempkey = ($field == 'id') ? 'id' : 'value';

        $objects = $this->LoadObjects($class, "WHERE `$field` = :$tempkey", array("$tempkey"=>$value));
        
        if (count($objects) > 1) throw new DuplicateUniqueKeyException("$class: $value");
        
        if (count($objects) == 1)
        {
            $object = array_values($objects)[0];
            $this->uniques[$unique] = $object;
            return $object;
        }
        else return null;
    }
    
    public function LoadObjectsMatchingAny(string $class, string $field, array $values, bool $like = false, ?int $limit = null) : array
    {
        if (strpos($field," ") !== false) throw new UniqueKeyWithSpacesException();
        
        $criteria_string = ""; $data = array(); $i = 0; $s = $like ? 'LIKE' : '=';
        
        foreach ($values as $value) {
            $criteria_string .= "`$field` $s :dat$i OR ";
            $data["dat$i"] = $value; $i++;
        }
        
        if ($criteria_string) $criteria_string = substr($criteria_string,0,-4);   
        
        $query = ($criteria_string?"WHERE $criteria_string ":"");
        
        return $this->LoadObjects($class, $query, $data, $limit);
    }
    
    public function LoadObjectsMatchingAll(string $class, ?array $criteria, bool $like = false, ?int $limit = null) : array
    {        
        $criteria_string = ""; $data = array(); $i = 0; $s = $like ? 'LIKE' : '=';
        
        if ($criteria !== null) foreach (array_keys($criteria) as $key) { 
            $criteria_string .= "`$key` ".($criteria[$key] !== null ? "$s :dat$i" : "IS NULL").' AND '; 
            if ($criteria[$key] !== null) $data["dat$i"] = $criteria[$key]; $i++;
        }; 
        
        if ($criteria_string) $criteria_string = substr($criteria_string,0,-5);       
        
        $query = ($criteria_string?"WHERE $criteria_string ":"");
        
        return $this->LoadObjects($class, $query, $data, $limit);
    }
    
    public function SaveObject(string $class, BaseObject $object, array $values, array $counters) : BaseObject
    {
        if ($object->isCreated()) return $this->SaveNewObject($class, $object, $values, $counters);
        
        $criteria_string = ""; $data = array('id'=>$object->ID()); $i = 0;
        
        foreach (array_keys($values) as $key) { 
            if ($key == 'id') continue;
            $criteria_string .= "`$key` = :dat$i, ";             
            $data["dat$i"] = $values[$key]; $i++;
        }; 
        
        foreach (array_keys($counters) as $key) {
            $criteria_string .= "`$key` = `$key` + :dat$i, ";
            $data["dat$i"] = $counters[$key]; $i++;
        }; 
        
        if (!$criteria_string) return $object;
        
        $criteria_string = substr($criteria_string,0,-2);            
        $table = self::GetClassTableName($class);            
        $query = "UPDATE $table SET $criteria_string WHERE id=:id";    
        $this->query($query, $data, false);    
        
        unset($this->modified[$object->ID()]);
        
        return $object;
    }
    
    public function SaveNewObject(string $class, BaseObject $object, array $values, array $counters) : BaseObject
    {        
        $columns_string = ""; $data_string = ""; $data = array(); $i = 0;
        
        $values['id'] = $object->ID();
        
        foreach (array_keys($values) as $key) {
            $columns_string .= "`$key`, "; $data_string .= ($values[$key] !== null ? ":dat$i, " : "NULL, ");
            if ($values[$key] !== null) $data["dat$i"] = $values[$key]; $i++;
        }
        
        if ($columns_string) $columns_string = substr($columns_string, 0, -2);
        if ($data_string) $data_string = substr($data_string, 0, -2);
        
        $table = self::GetClassTableName($class);
        $query = "INSERT INTO $table ($columns_string) VALUES ($data_string)";
        $this->query($query, $data, false);
        
        unset($this->modified[$object->ID()]);
        
        return $object;
    }
    
    private function getDefaultFields(string $class) : array
    {
        if (array_key_exists($class,$this->columns)) return $this->columns[$class];
        
        $table = self::GetClassTableName($class);
        $columns = $this->query("SHOW FIELDS FROM $table");        
        $columns = array_map(function($e){ return $e['Field']; }, $columns);
        
        $this->columns[$class] = $columns; return $columns;
    }
    
    public function CreateObject(string $class) : BaseObject
    {
        $columns = $this->getDefaultFields($class);        
        $data = array_fill_keys($columns, null);
        $data['id'] = Utilities::Random(BaseObject::IDLength);        
        
        $newobj = array_values($this->Rows2Objects(array($data), $class))[0];

        $this->objects[$newobj->ID()] = $newobj;
         
        return $newobj;
    }
    
    public function DeleteObject(string $class, BaseObject $object) : void
    {
        $this->unsetObject($object);
        
        $table = self::GetClassTableName($class); 
        $this->query("DELETE FROM $table WHERE id=:id", array('id'=>$object->ID()), false);
    }
    
    public function DeleteObjects(string $class, array $objects) : void
    {
        if (count($objects) < 1) return;        
        
        $criteria_string = ""; $data = array(); $i = 0;
        
        foreach ($objects as $object) {
            $criteria_string .= "id = :dat$i OR ";
            $data["dat$i"] = $object->ID(); $i++;            
            $this->unsetObject($object);
        }
        
        $criteria_string = substr($criteria_string,0,-4);   
        
        $table = self::GetClassTableName($class);
        $this->query("DELETE FROM $table WHERE $criteria_string",$data,false);
    }
}







