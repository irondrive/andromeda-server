<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\{BaseObject, SingletonObject};

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

require_once(ROOT."/apps/accounts/auth/LDAP.php");
require_once(ROOT."/apps/accounts/auth/IMAP.php");
require_once(ROOT."/apps/accounts/auth/FTP.php");
require_once(ROOT."/apps/accounts/auth/Pointer.php");

interface ISource
{
    public function ID() : string;
    public function GetAccountGroup() : ?Group;
    
    public function VerifyPassword(string $username, string $password) : bool;    
}

abstract class External extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array(
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        );
    }
}

class Local extends SingletonObject implements ISource
{
    public function VerifyPassword(string $username, string $password) : bool
    {
        $account = Account::TryLoadByUsername($this->database, $username);
        if ($account === null) return false;
        
        $hash = $account->GetScalar('password');
        
        $correct = password_verify($password, $hash);
        
        if ($correct && password_needs_rehash($hash, Utilities::GetHashAlgo()))
            $account->SetScalar('password', self::HashPassword($password));
            
        return $correct;
    }
    
    public static function HashPassword(string $password) : string
    {
        return password_hash($password, Utilities::GetHashAlgo());
    }
    
    public function GetAccountGroup() : ?Group { return null; }
}

