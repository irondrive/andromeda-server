<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/Database/StandardObject.php");

/** Exception indicating that the singleton was duplicated */
class DuplicateSingletonException extends Exceptions\ServerException { public $message = "DUPLICATE_DBSINGLETON"; }

/** Extends StandardObject with interfaces for a singleton object */
abstract class SingletonObject extends StandardObject
{
    private static $instances = array();

    /**
     * Gets the instance of the given class, possibly loading it from the DB
     * @param ObjectDatabase $database reference to the database
     * @throws DuplicateSingletonException if > 1 object is loaded
     * @throws ObjectNotFoundException if no object is loaded
     * @return self
     */
    public static function GetInstance(ObjectDatabase $database) : self
    {
        if (array_key_exists(static::class, self::$instances)) 
            return self::$instances[static::class];
        
        $objects = static::LoadAll($database);
        if (count($objects) > 1) throw new DuplicateSingletonException();
        else if (count($objects) == 0) throw new ObjectNotFoundException();
        
        else return (self::$instances[static::class] = array_values($objects)[0]);
    }

    /**
     * @throws DuplicateSingletonException if an object already exists in the DB
     * @see StandardObject::BaseCreate()
     */
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        if (count(static::LoadAll($database)) > 0) 
            throw new DuplicateSingletonException();
        
        return (self::$instances[static::class] = parent::BaseCreate($database));
    }
}

