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

    /** 
     * Returns the policy object corresponding to the given account 
     * If account is null, or no object is found, returns self with no database values set (default policies)
     */
    public static function ForceLoadByAccount(ObjectDatabase $database, ?Account $account) : static
    {
        $aclim = ($account === null) ? null : static::TryLoadByAccount($database, $account);
        return $aclim ?? new static($database, array(), false);
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

    // TODO POLICY account should always return non-null values, right?
    // TODO POLICY need to implement getting from group policy

    /** Returns true if the limited object should allow sharing items */
    public function GetAllowItemSharing() : bool { return $this->can_itemshare->TryGetValue() ?? true; } // TODO POLICY remove ?? here, implement properly
    
    /** Returns true if the limited object should allow sharing to groups */
    public function GetAllowShareToGroups() : bool { return $this->can_share2groups->TryGetValue() ?? false; }
    
    /** Returns true if the limited object should allow public upload to folders */
    public function GetAllowPublicUpload() : bool { return $this->can_publicupload->TryGetValue() ?? false; }
    
    /** Returns true if the limited object should allow public modification of files */
    public function GetAllowPublicModify() : bool { return $this->can_publicmodify->TryGetValue() ?? false; } 
    
    /** Returns true if the limited object should allow random writes to files */
    public function GetAllowRandomWrite() : bool { return $this->can_randomwrite->TryGetValue() ?? true; }
    
    /** Returns true if sending shared via server email is allowed */
    public function GetAllowEmailShare() : bool { return $this->can_emailshare->TryGetValue() ?? false; }
    
    /** Returns true if creating user-defined storages is allowed */
    public function GetAllowUserStorage() : bool { return $this->can_userstorage->TryGetValue() ?? false; }

}
