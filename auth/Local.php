<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\{BaseObject, SingletonObject};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\ClientObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/auth/LDAP.php");
require_once(ROOT."/apps/accounts/auth/IMAP.php");
require_once(ROOT."/apps/accounts/auth/FTP.php");

interface Source
{
    public function VerifyPassword(string $username, string $password) : bool;
    public function GetAccountGroup() : ?Group;
}

class Local extends SingletonObject implements Source
{                
    public function VerifyPassword(string $username, string $password) : bool
    {
        $account = Account::TryLoadByUsername($this->database, $username);
        if ($account === null) return false;
        
        $hash = $account->GetScalar('password');
        
        $correct = password_verify($password, $hash);
        
        $algo = defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_DEFAULT;
        
        if ($correct && password_needs_rehash($hash, $algo))
            $account->SetScalar('password', self::HashPassword($password));
            
        return $correct;
    }
    
    public static function HashPassword(string $password) : string
    {
        $algo = defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_DEFAULT;
        
        return password_hash($password, $algo);
    }
    
    public function GetAccountGroup() : ?Group { return null; }
}

class SourcePointer extends BaseObject implements ClientObject
{
    public static function TryLoadSourceByPointer(ObjectDatabase $database, string $pointer) : ?Source
    {
        $authsource = self::TryLoadByID($database, $pointer);
        if ($authsource === null) return null; else return $authsource->GetSource();
    }
    
    public function GetSource() : Source { return $this->GetObject('authsource'); }
    
    public function GetDescription()
    {
        return $this->TryGetScalar("description") ?? $this->GetClass();
    }
    
    public function GetClientObject(int $level = 0) : array
    {
        return array(
            'id' => $this->ID(),
            'description' => $this->GetDescription(),
        );
    }
}

