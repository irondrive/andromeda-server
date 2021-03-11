<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Singleton, Utilities};
require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/auth/External.php");

/** 
 * The regular internal authentication source
 * 
 * Does not exist in the database. Stores passwords as hashes in the Account object.
 */
class Local extends Singleton implements ISource
{
    public function VerifyAccountPassword(Account $account, string $password) : bool
    {
        $hash = $account->GetPasswordHash();
        
        $correct = password_verify($password, $hash);
        
        if ($correct && password_needs_rehash($hash, Utilities::GetHashAlgo()))
            static::SetPassword($account, $password);
            
        return $correct;
    }
    
    /**
     * Hashes and sets the given password on a given account
     * @param Account $account the account to set
     * @param string $password the password to set
     */
    public static function SetPassword(Account $account, string $password) : void
    {
        $account->SetPasswordHash(static::HashPassword($password));
    }
    
    /** Returns a new hash of the given password */
    private static function HashPassword(string $password) : string
    {
        return password_hash($password, Utilities::GetHashAlgo());
    }
}

