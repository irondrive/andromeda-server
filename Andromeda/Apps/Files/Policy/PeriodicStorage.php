<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; //if (!defined('Andromeda')) die(); // TODO FUTURE phpstan bug

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Apps\Files\Storage\Storage;

class PeriodicStorage extends Periodic
{
    use BaseStorage, TableTypes\TableNoChildren;

    /** Deletes any policy objects corresponding to the given storage */
    public static function DeleteByStorage(ObjectDatabase $database, Storage $storage) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'storage', $storage->ID());
    }
}