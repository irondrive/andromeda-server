<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/Exceptions.php"); use Andromeda\Core\Database\DatabaseException;

/** A singleton object that stores a version field */
abstract class BaseConfig extends SingletonObject
{    
    /** @return string the lowercase name of the app */
    public abstract static function getAppname() : string;
    
    /** @return string the config database schema version */
    public abstract static function getVersion() : string;
    
    /** Date the config object was created */
    protected FieldTypes\Timestamp $date_created;
    /** Version of the app that owns this config */
    protected FieldTypes\StringType $version;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->version = $fields[] = new FieldTypes\StringType('version');
        
        $this->RegisterChildFields($fields);
        
        parent::CreateFields();
    }
    
    /** 
     * Create a new config singleton in the given database 
     * @return static
     */
    public abstract static function Create(ObjectDatabase $database);

    /** 
     * @throws InstallRequiredException if config is not installed
     * @throws UpgradeRequiredException if the version in the given data does not match
     * @return static 
     */
    public static function GetInstance(ObjectDatabase $database) : self
    {
        try { return parent::GetInstance($database); }
        catch (DatabaseException $e) 
        {
            if ($database->HasApiPackage()) // log just in case
                $database->GetApiPackage()->GetErrorManager()->LogException($e);
            throw new InstallRequiredException(static::GetAppname()); 
        }
    }
    
    /** @return static */
    protected static function BaseCreate(ObjectDatabase $database) : self
    {
        $obj = parent::BaseCreate($database);
        $obj->date_created->SetTimeNow();
        $obj->version->SetValue(static::getVersion());
        return $obj;
    }
    
    /** true if we should update the version when loading instead of checking */
    protected static bool $forceUpdate = false;
    
    /** @return static */
    public static function ForceUpdate(ObjectDatabase $database) : self
    {
        static::$forceUpdate = true;
        $obj = static::GetInstance($database);
        static::$forceUpdate = false;
        return $obj;
    }
    
    /** @throws UpgradeRequiredException if the version in the given data does not match */
    public function __construct(ObjectDatabase $database, array $data)
    {
        $version = $data['version'] ?? null;
        if ($version !== null && $version !== static::getVersion() && !static::$forceUpdate)
            throw new UpgradeRequiredException(static::GetAppname(), (string)$version);
        
        parent::__construct($database, $data);
        
        if (static::$forceUpdate)
            $this->version->SetValue(static::getVersion());
    }
}
