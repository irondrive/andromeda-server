<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/AuthSource/IAuthSource.php");

/** 
 * The regular internal authentication source
 * 
 * Does not exist in the database. Stores passwords as hashes in the Account object.
 */
class Local implements IAuthSource // TODO make this not a singleton, get rid of Singleton entirely...
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
        $account->SetPasswordHash(password_hash($password, Utilities::GetHashAlgo()));
    }
}
