<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Transactions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

abstract class Storage extends StandardObject implements Transactions
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner' => new FieldTypes\ObjectRef(Account::class)
        ));
    }
    
    public abstract function GetClientObject() : array;

    public function commit() { }
    public function rollback() { }
}
