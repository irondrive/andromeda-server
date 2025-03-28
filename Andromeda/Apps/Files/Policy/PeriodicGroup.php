<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; //if (!defined('Andromeda')) die(); // TODO FUTURE phpstan bug

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Apps\Accounts\Group;

class PeriodicGroup extends Periodic implements IBaseGroup
{
    use BaseGroup, TableTypes\TableNoChildren;

    /** Deletes any policy objects corresponding to the given group */
    public static function DeleteByGroup(ObjectDatabase $database, Group $group) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'group', $group->ID());
    }
}