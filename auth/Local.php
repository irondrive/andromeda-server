<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Singleton, Utilities};
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

interface ISource
{
    public function GetAccountGroup() : ?Group;
    
    public function VerifyPassword(Account $account, string $password) : bool;    
}

abstract class External extends BaseObject implements ISource
{
    public static function GetFieldTemplate() : array
    {
        return array(
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        );
    }
}

class Local extends Singleton implements ISource
{
    public function VerifyPassword(Account $account, string $password) : bool
    {
        $hash = $account->GetPasswordHash();
        
        $correct = password_verify($password, $hash);
        
        if ($correct && password_needs_rehash($hash, Utilities::GetHashAlgo()))
            $account->SetPasswordHash(static::HashPassword($password));
            
        return $correct;
    }
    
    public static function HashPassword(string $password) : string
    {
        return password_hash($password, Utilities::GetHashAlgo());
    }
    
    public function GetAccountGroup() : ?Group { return null; }
}

