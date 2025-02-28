<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes, FieldTypes};
use Andromeda\Apps\Accounts\Account;

class StandardAccount extends Standard
{
    use BaseAccount, TableTypes\TableNoChildren;
    
    /** True if sending shares via server email is allowed */
    protected FieldTypes\NullBoolType $can_emailshare;
    /** True if creating user-defined storages is allowed */
    protected FieldTypes\NullBoolType $can_userstorage;

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'account';
        return $ret;
    }
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->can_emailshare = $fields[] = new FieldTypes\NullBoolType('can_emailshare');
        $this->can_userstorage = $fields[] = new FieldTypes\NullBoolType('can_userstorage');

        $this->RegisterFields($fields, self::class);
        $this->BaseAccountCreateFields();
        parent::CreateFields();
    }

    /** Returns self with no database values set (will return default policy) */
    public static function GetDefault(ObjectDatabase $database) : static
    {
        return new static($database, array(), false);
    }

    /** Loads the policy object corresponding to the given account */
    public static function TryLoadByAccount(ObjectDatabase $database, Account $account) : ?static
    {
        return $database->TryLoadUniqueByKey(static::class, 'account', $account->ID());
    }

    /** Deletes the policy object corresponding to the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : bool
    {
        return $database->TryDeleteUniqueByKey(static::class, 'account', $account->ID());
    }
}