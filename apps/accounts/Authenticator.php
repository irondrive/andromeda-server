<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, DatabaseException};
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParamException};
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");

/** Exception indicating that the request is not allowed with the given authentication */
class AuthenticationFailedException extends Exceptions\ClientDeniedException { public $message = "AUTHENTICATION_FAILED"; }

/** Exception indicating that the authenticated account is disabled */
class AccountDisabledException extends AuthenticationFailedException { public $message = "ACCOUNT_DISABLED"; }

/** Exception indicating that the specified session is invalid */
class InvalidSessionException extends AuthenticationFailedException { public $message = "INVALID_SESSION"; }

/** Exception indicating that admin-level access is required */
class AdminRequiredException extends AuthenticationFailedException { public $message = "ADMIN_REQUIRED"; }

/** Exception indicating that a two factor code was required but not given */
class TwoFactorRequiredException extends AuthenticationFailedException { public $message = "TWOFACTOR_REQUIRED"; }

/** Exception indicating that a password for authentication was required but not given */
class PasswordRequiredException extends AuthenticationFailedException { public $message = "AUTH_PASSWORD_REQUIRED"; }

/** Exception indicating that the request requires providing crypto details */
class CryptoKeyRequiredException extends AuthenticationFailedException { public $message = "CRYPTOKEY_REQUIRED"; }

use Andromeda\Core\DecryptionFailedException;

/**
 * The class used to authenticate requests
 *
 * This is the API class that should be used in other apps.
 * Allows admins to masquerade as other users ("sudouser")
 */
class Authenticator
{
    private Account $account; 
    private Session $session; 
    private Client $client;     
    private Input $input;
    
    private ObjectDatabase $database; 
    
    private static array $instances = array();
   
    /** Returns the authenticated user account */
    public function GetAccount() : Account { return $this->account; }
    
    /** Returns the session used for the request */
    public function GetSession() : Session { return $this->session; }
    
    /** Returns the client used for the request */
    public function GetClient() : Client { return $this->client; }
    
    private bool $issudouser = false; 
    
    /** Returns true if the user is masquering as another user */
    public function isSudoUser() : bool { return $this->issudouser; }
    
    private Account $realaccount;    
    
    /** Returns the actual account used for the request, not the masqueraded one */
    public function GetRealAccount() : Account { return $this->realaccount; }
    
    /**
     * The primary authentication routine
     * 
     * Loads the specified session, checks validity, updates dates, checks sudouser.
     * Note that only a session must be provided, not the client that owns it.
     * @param ObjectDatabase $database database reference
     * @param Input $input the input containing auth details
     * @param IOInterface $interface the interface used for the request
     * @throws InvalidSessionException if the given session details are invalid
     * @throws AccountDisabledException if the given account is disabled
     * @throws UnknownAccountException if the given sudo account is not valid
     */
    private function __construct(ObjectDatabase $database, Input $input, IOInterface $interface)
    {        
        $sessionid = $input->GetOptParam('auth_sessionid',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        $sessionkey = $input->GetOptParam('auth_sessionkey',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        
        if (($auth = $input->GetAuth()) !== null)
        {
            $sessionid ??= $auth->GetUsername();
            $sessionkey ??= $auth->GetPassword();
        }
        
        if (!$sessionid || !$sessionkey) throw new InvalidSessionException();

        $session = Session::TryLoadByID($database, $sessionid);
        
        if ($session === null || !$session->CheckKeyMatch($sessionkey)) throw new InvalidSessionException();
            
        $account = $session->GetAccount(); $client = $session->GetClient();
        
        $this->input = $input;
        $this->database = $database;
        $this->realaccount = $account; 
        $this->session = $session; 
        $this->client = $client;

        if (!$account->isEnabled()) throw new AccountDisabledException();
        
        $account->setActiveDate(); $client->setActiveDate();
        
        if ($input->HasParam('auth_sudouser') && $account->isAdmin())
        {
            $this->issudouser = true;
            $sudouser = $input->GetParam('auth_sudouser', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
            $account = Account::TryLoadByID($database, $sudouser);
            if ($account === null) throw new UnknownAccountException();     
        }
        
        $this->account = $account;
        
        array_push(self::$instances, $this);
    }
    
    /**
     * Returns a new authenticator object
     * @see Authenticator::__construct()
     */
    public static function Authenticate(ObjectDatabase $database, Input $input, IOInterface $interface) : self
    {
        return new self($database, $input, $interface);
    }
    
    /**
     * Returns a new authenticator object, or null if it fails (instead of exceptions)
     * @see Authenticator::__construct()
     */
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, IOInterface $interface) : ?self
    {
        try { return new self($database, $input, $interface); }
        catch (AuthenticationFailedException | SafeParamException | DatabaseException $e) { return null; }
    }
    
    /** Returns true if the real account used for the request is an admin */
    public function isAdmin() : bool { return $this->realaccount->isAdmin(); }
    
    /**
     * Requires that the user is an administrator
     * @throws AdminRequiredException if not an admin
     */
    public function RequireAdmin() : self
    {
        if (!$this->isAdmin()) throw new AdminRequiredException(); return $this;
    }
    
    /**
     * Requires that the user posts a twofactor code, if the account uses twofactor
     * @throws TwoFactorRequiredException if twofactor was not given
     * @throws AuthenticationFailedException if the given twofactor was invalid
     */
    public function TryRequireTwoFactor() : self
    {
        static::StaticTryRequireTwoFactor($this->input, $this->realaccount, $this->session); return $this;
    }
    
    /** @see Authenticator::TryRequireTwoFactor() */
    public static function StaticTryRequireTwoFactor(Input $input, Account $account, ?Session $session = null) : void
    {
        if (!$account->HasValidTwoFactor()) return; 
        
        static::StaticTryRequireCrypto($input, $account, $session);
        
        $twofactor = $input->GetOptParam('auth_twofactor', SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_NEVER); // not an int (leading zeroes)
        
        if ($twofactor === null) throw new TwoFactorRequiredException();
        else if (!$account->CheckTwoFactor($twofactor)) throw new AuthenticationFailedException();
    }
    
    /**
     * Requires that the user provides their password
     * @throws PasswordRequiredException if the password is not given
     * @throws AuthenticationFailedException if the password is invalid
     */
    public function RequirePassword() : self
    {
        $password = $this->input->GetOptParam('auth_password',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        if ($password === null) throw new PasswordRequiredException();

        if (!$this->realaccount->VerifyPassword($password))
                throw new AuthenticationFailedException();

        return $this;
    }
    
    /**
     * Same as RequireCrypto() but does nothing if the account does not have crypto
     * @see Authenticator::RequireCrypto()
     */
    public function TryRequireCrypto() : self
    {
        return (!$this->account->hasCrypto()) ? $this : $this->RequireCrypto();
    }
    
    /**
     * Same as StaticRequireCrypto() but does nothing if the account does not have crypto
     * @see Authenticator::StaticRequireCrypto()
     */
    public static function StaticTryRequireCrypto(Input $input, Account $account, ?Session $session = null) : void
    {
        if ($account->hasCrypto()) static::StaticRequireCrypto($input, $account, $session);
    }
    
    /**
     * Requires that the account's crypto is unlocked for the request (and exists)
     *
     * Account crypto can be unlocked via a session, a recovery key, or a password
     * @throws AuthenticationFailedException if the given keysource is not valid
     * @throws CryptoKeyRequiredException if no key source was given
     */
    public function RequireCrypto() : self
    {
        static::StaticRequireCrypto($this->input, $this->account, $this->session); return $this;    
    }
    
    /** @see Authenticator::RequireCrypto() */
    public static function StaticRequireCrypto(Input $input, Account $account, ?Session $session = null) : void
    {
        if ($account->CryptoAvailable()) return;
        
        $password = $input->GetOptParam('auth_password', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        $recoverykey = $input->GetOptParam('recoverykey', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        if ($session !== null && $session->hasCrypto())
        {
            try { $account->UnlockCryptoFromKeySource($session); }
            catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
        }
        else if ($recoverykey !== null && $recoverykey->hasCrypto())
        {
            try { $account->UnlockCryptoFromRecoveryKey($recoverykey); }
            catch (DecryptionFailedException | RecoveryKeyFailedException $e) { throw new AuthenticationFailedException(); }
        }
        else if ($password !== null && $account->hasCrypto())
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
        }       
        else throw new CryptoKeyRequiredException();
    }
  
    /** Runs TryRequireCrypto() on all instantiated authenticators */
    public static function AllRequireCrypto() : void
    {
        foreach (self::$instances as $auth) $auth->TryRequireCrypto();
    }
}