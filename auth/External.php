<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** The basic authentication interface */
interface ISource
{
    /**
     * Verify the password given
     * @param Account $account the account to check (contains the username or other info)
     * @param string $password the password to check
     * @return bool true if the password check is valid
     */
    public function VerifyPassword(Account $account, string $password) : bool;
}

/** Describes an external auth source that has a manager and lives in the database */
abstract class External extends BaseObject implements ISource 
{ 
    public static function GetFieldTemplate() : array
    {
        return array(
            'manager' => new FieldTypes\ObjectRef(Manager::class, 'authsource', false)
        );
    }
    
    /** Returns the auth manager object for this source */
    public function GetManager() : Manager { return $this->GetObject('manager'); }

    /** Creates a new external auth source */
    public static function Create(ObjectDatabase $database, Input $input)
    {
        return parent::BaseCreate($database)->Edit($input);
    }
    
    public function Activate() : self { return $this; }
    
    /** Returns the backend-specific command usage for Create() and Edit() */
    public abstract static function GetPropUsage() : string;
    
    /** Edits properties of an existing auth source */
    public abstract function Edit(Input $input);
    
    public abstract function GetClientObject() : array;
}
