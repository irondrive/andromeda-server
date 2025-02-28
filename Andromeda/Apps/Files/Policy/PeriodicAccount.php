<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};

class PeriodicAccount extends Periodic
{
    use BaseAccount, TableTypes\TableNoChildren;

    /** Deletes any policy objects corresponding to the given account */
    public static function DeleteByAccount(ObjectDatabase $database, \Andromeda\Apps\Accounts\Account $account) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
}