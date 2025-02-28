<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Apps\Files\Storage\Storage;

class StandardStorage extends Standard
{
    use BaseStorage, TableTypes\TableNoChildren;

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'storage';
        return $ret;
    }
    
    protected function CreateFields() : void
    {
        $fields = array();
        $this->RegisterFields($fields, self::class);
        $this->BaseStorageCreateFields();
        parent::CreateFields();
    }

    /** Loads the policy object corresponding to the given storage */
    public static function TryLoadByStorage(ObjectDatabase $database, Storage $storage) : ?static
    {
        return $database->TryLoadUniqueByKey(static::class, 'storage', $storage->ID());
    }
    
    /** Deletes the policy object corresponding to the given storage */
    public static function DeleteByStorage(ObjectDatabase $database, Storage $storage) : bool
    {
        return $database->TryDeleteUniqueByKey(static::class, 'storage', $storage->ID());
    }
}