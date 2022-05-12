<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/Exceptions.php");
require_once(ROOT."/Core/Database/Database.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
require_once(ROOT."/Core/Database/BaseObject.php");

/** A class with a constant ID so there can only exist one instance */
abstract class SingletonObject extends BaseObject
{
    /** @var array<string, static> array of instances by class and database ID */
    private static $instances = array();
    
    protected static function GenerateID() : string { return 'A'; }
    
    /** Returns a unique instance index for this class and the given database */
    private static function GetIndex(ObjectDatabase $database) : string
    {
        return $database->GetInternal()->getInstanceID().'_'.static::class;
    }

    /**
     * Gets the instance of the given class, possibly loading it from the DB
     * @param ObjectDatabase $database reference to the database
     * @throws SingletonNotFoundException if no object is loaded
     * @return static
     */
    public static function GetInstance(ObjectDatabase $database) : self
    {
        $key = self::GetIndex($database);
        
        if (!array_key_exists($key, self::$instances))
        {
            $obj = $database->TryLoadUniqueByKey(static::class,'id','A');
            if ($obj === null) throw new SingletonNotFoundException(static::class);
            
            self::$instances[$key] = $obj;
        }
        
        return self::$instances[$key];
    }

    /** @return static */
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        $idx = self::GetIndex($database);
        $obj = parent::BaseCreate($database);
        return self::$instances[$idx] = $obj;
    }
}
