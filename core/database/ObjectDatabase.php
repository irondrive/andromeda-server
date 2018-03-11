<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Database;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

class ObjectTypeException extends Exceptions\ServerException            { public $message = "DBOBJECT_TYPE_MISMATCH"; }
class DuplicateUniqueKeyException extends Exceptions\ServerException    { public $message = "DUPLICATE_DBOBJECT_UNIQUE_VALUES"; }
class UniqueKeyWithSpacesException extends Exceptions\ServerException   { public $message = "UNIQUE_KEY_LOAD_WITH_SPACE"; }

class ObjectDatabase extends Database
{
    private $objects = array(); private $modified = array(); private $uniques = array();
    
    public function isModified(BaseObject $obj) : bool { return array_key_exists($obj->ID(), $this->modified); }
    
    public function setModified(BaseObject $obj) 
    { 
        if (!$this->isModified($obj)) $this->modified[$obj->ID()] = $obj;
    }
    
    private function unsetObject(BaseObject $obj)
    {
        unset($this->objects[$obj->ID()]);
        
        if ($this->isModified($obj)) unset($this->modified[$obj->ID()]);
    }
    
    public function getLoadedObjects() { return array_keys($this->objects); }
    
    public function commit() 
    {
        foreach ($this->modified as $object) { $object->Save(); }  
        
        parent::commit();
        
        $this->modified = array();
    }
    
    public static function GetFullClassName(string $class) : string
    {
        return "Andromeda\\$class";
    }
    
    public static function GetShortClassName(string $class)
    {
        $class = explode('\\',$class); unset($class[0]); return implode('\\',$class); 
    }
    
    public static function GetClassTableName(string $class) : string
    {
        $class = explode('\\',$class); unset($class[0]);
        return Config::PREFIX."objects_".strtolower(implode('_', $class));
    }
    
    private function Rows2Objects($rows, $class, $replace = false) : array
    {
        $output = array(); 
        
        foreach ($rows as $row)
        {
            $object = new $class($this, $row); $id = $object->ID();

            if (!$replace && in_array($id, array_keys($this->objects)))
                $output[$id] = $this->objects[$id];
            
            else { $output[$id] = $object; $this->objects[$id] = $object; }
        }       
        
        return $output; 
    }
    
    public function LoadObjects(string $class, string $query, array $criteria, $limit = -1) : array
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
        
        $query = "SELECT $table.* FROM $table $query".($limit>-1 ? " LIMIT $limit" : "");
        
        $result = $this->query($query, $criteria, true);
        
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
    
    public function LoadObjectsMatchingAny(string $class, string $field, array $values, int $limit = -1) : array
    {
        if (strpos($field," ") !== false) throw new UniqueKeyWithSpacesException();
        
        $criteria_string = ""; $data = array(); $i = 0; 
        
        foreach ($values as $value) {
            $criteria_string .= "`$field` = :dat$i OR ";
            $data["dat$i"] = $value; $i++;
        }
        
        if ($criteria_string) $criteria_string = substr($criteria_string,0,-4);   
        
        $query = ($criteria_string?"WHERE $criteria_string ":"");
        
        return $this->LoadObjects($class, $query, $data, $limit);
    }
    
    public function LoadObjectsMatchingAll(string $class, ?array $criteria, int $limit = -1) : array
    {        
        $criteria_string = ""; $data = array(); $i = 0; 
        
        if ($criteria !== null) foreach (array_keys($criteria) as $key) { 
            $criteria_string .= "`$key` ".($criteria[$key] !== null ? "= :dat$i" : "IS NULL").' AND   '; 
            if ($criteria[$key] !== null) $data["dat$i"] = $criteria[$key]; $i++;
        }; 
        
        if ($criteria_string) $criteria_string = substr($criteria_string,0,-7);       
        
        $query = ($criteria_string?"WHERE $criteria_string ":"");
        
        return $this->LoadObjects($class, $query, $data, $limit);
    }
    
    public function SaveObject(string $class, BaseObject $object, array $values, array $counters) : BaseObject
    {
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
        
        if ($criteria_string)
        {
            $criteria_string = substr($criteria_string,0,-2);            
            $table = self::GetClassTableName($class);            
            $query = "UPDATE $table SET $criteria_string WHERE id=:id";    
            $this->query($query, $data, false);
        }
        
        return $object;
    }
    
    public function CreateObject(string $class, array $input, ?int $idlen = null)
    {
        $table = self::GetClassTableName($class); 
        
        $columns_string = ""; $data_string = ""; $data = array(); $i = 0;
        
        $input['id'] = Utilities::Random($idlen);
        
        foreach (array_keys($input) as $key) {
            $columns_string .= "`$key`, "; $data_string .= ($input[$key] !== null ? ":dat$i, " : "NULL");
            if ($input[$key] !== null) $data["dat$i"] = $input[$key]; $i++;
        }
        
        if ($columns_string) $columns_string = substr($columns_string, 0, -2);
        if ($data_string) $data_string = substr($data_string, 0, -2);
        
        $query = "INSERT INTO $table ($columns_string) VALUES ($data_string)";

        $this->query($query, $data, false);
        
        return $class::LoadByID($this, $input['id']);
    }
    
    public function DeleteObject(string $class, BaseObject $object) : void
    {
        $this->unsetObject($object);
        
        $table = self::GetClassTableName($class); 
        $this->query("DELETE FROM $table WHERE id=:id",array('id'=>$object->ID()),false);
    }
    
    public function DeleteObjects(string $class, array $objects) : void
    {
        if (count($objects) < 1) return;
        
        $table = self::GetClassTableName($class); 
        
        $criteria_string = ""; $data = array(); $i = 0;
        
        foreach ($objects as $object) {
            $criteria_string .= "id = :dat$i OR ";
            $data["dat$i"] = $object->ID(); $i++;            
            $this->unsetObject($object);
        }
        
        $criteria_string = substr($criteria_string,0,-4);   
        
        $this->query("DELETE FROM $table WHERE $criteria_string",$data,false);
    }
}







