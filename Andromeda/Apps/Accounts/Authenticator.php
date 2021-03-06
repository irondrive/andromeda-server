<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, DatabaseException};
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Config.php");
require_once(ROOT."/Apps/Accounts/Client.php");
require_once(ROOT."/Apps/Accounts/Group.php");
require_once(ROOT."/Apps/Accounts/Session.php");
require_once(ROOT."/Apps/Accounts/TwoFactor.php");

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
class PasswordRequiredException extends AuthenticationFailedException { public $message = "PASSWORD_REQUIRED"; }

/** Exception indicating that the request requires providing crypto details */
class CryptoKeyRequiredException extends AuthenticationFailedException { public $message = "CRYPTO_KEY_REQUIRED"; }

/** Exception indicating that the account does not have crypto initialized */
class CryptoInitRequiredException extends Exceptions\ClientErrorException { public $message = "CRYPTO_INIT_REQUIRED"; }

/** Exception indicating that the action requires an account to act as */
class AccountRequiredException extends Exceptions\ClientErrorException { public $message = "ACCOUNT_REQUIRED"; }

/** Exception indicating that the action requires a session to use */
class SessionRequiredException extends Exceptions\ClientErrorException { public $message = "SESSION_REQUIRED"; }

use Andromeda\Core\DecryptionFailedException;
use Andromeda\Core\UpgradeRequiredException;

/**
 * The class used to authenticate requests
 *
 * This is the API class that should be used in other apps.
 */
class Authenticator
{
    private Input $input;
    
    private static array $instances = array();
   
    private ?Account $account = null;
    
    /** Returns the authenticated user account (or null) */
    public function TryGetAccount() : ?Account { return $this->account; }
    
    /** Returns the authenticated user account (not null) */
    public function GetAccount() : Account
    {
        if ($this->account === null)
            throw new AccountRequiredException();
        return $this->account;
    }

    private ?Account $realaccount = null;
    
    /** Returns the actual account used for the request, not the masqueraded one (or null) */
    public function TryGetRealAccount() : ?Account { return $this->realaccount; }
    
    /** Returns the actual account used for the request, not the masqueraded one (not null) */
    public function GetRealAccount() : Account
    {
        if ($this->realaccount === null)
            throw new AccountRequiredException();
        return $this->realaccount;
    }
    
    /** Returns true if the user is masquering as another user */
    public function isSudoUser() : bool { return $this->account !== $this->realaccount; }
    
    private ?Session $session = null;
    
    /** Returns the session used for the request (or null) */
    public function TryGetSession() : ?Session { return $this->session; }
    
    /** Returns the session used for the request (not null) */
    public function GetSession() : Session
    {
        if ($this->session === null)
            throw new SessionRequiredException();
        return $this->session;
    }
    
    private ?Client $client = null;
    
    /** Returns the client used for the request or null */
    public function TryGetClient() : ?Client { return $this->client; }
    
    /** Returns the client used for the request (not null) */
    public function GetClient() : Client
    {
        if ($this->client === null) 
            throw new SessionRequiredException();
        return $this->client;
    }
    
    /** Returns true if the account used for the request is an admin */
    public function isAdmin() : bool { return $this->account === null || $this->account->isAdmin(); }
    
    /** Returns true if the real account used for the request is an admin */
    public function isRealAdmin() : bool { return $this->realaccount === null || $this->realaccount->isAdmin(); }
    
    /**
     * @param Input $input the input containing auth details
     */
    private function __construct(Input $input)
    {
        $this->input = $input;
    }
    
    /**
     * The primary authentication routine
     *
     * Loads the specified session, checks validity, updates dates.
     * Note that only a session must be provided, not the client that owns it.
     * @param ObjectDatabase $database database reference
     * @param Input $input the input containing auth details
     * @param IOInterface $interface the interface used for the request
     * @throws InvalidSessionException if the given session details are invalid
     * @throws AccountDisabledException if the given account is disabled
     * @throws UnknownAccountException if the given sudo account is not valid
     * @returns new authenticator object or null if not authenticated
     */
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, IOInterface $interface) : ?self
    {
        try
        {
            if (Config::GetInstance($database)->getVersion() !== AccountsApp::getVersion())
                throw new UpgradeRequiredException(AccountsApp::getName());
        }
        catch (DatabaseException $e){ return null; } // not installed
        
        $sessionid = $input->GetOptParam('auth_sessionid',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        $sessionkey = $input->GetOptParam('auth_sessionkey',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        
        if (($auth = $input->GetAuth()) !== null)
        {
            $sessionid ??= $auth->GetUsername();
            $sessionkey ??= $auth->GetPassword();
        }
        
        $sudouser = $input->GetOptParam('auth_sudouser',SafeParam::TYPE_TEXT,SafeParams::PARAMLOG_ALWAYS);
        $sudoacct = $input->GetOptParam('auth_sudoacct',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        $account = null; $authenticator = new Authenticator($input);
        
        if ($sessionid !== null && $sessionkey !== null)
        {
            $session = Session::TryLoadByID($database, $sessionid);
            
            if ($session === null || !$session->CheckMatch($sessionkey)) throw new InvalidSessionException();
            
            $account = $session->GetAccount();
            
            if (!$account->isEnabled()) throw new AccountDisabledException();
            
            $authenticator->realaccount = $account->setActiveDate();
            $authenticator->session = $session->setActiveDate();
            $authenticator->client = $session->GetClient()->setActiveDate();
            
            if (($sudouser !== null || $sudoacct !== null) && !$account->isAdmin())
                throw new AdminRequiredException();
        }
        else if (!$interface->isPrivileged()) return null; // not authenticated
        
        if ($sudouser !== null)
        {
            $account = Account::TryLoadByUsername($database, $sudouser);
            if ($account === null) throw new UnknownAccountException();           
        }
        else if ($sudoacct !== null)
        {
            $account = Account::TryLoadByID($database, $sudoacct);
            if ($account === null) throw new UnknownAccountException();
        }
        
        $authenticator->account = $account;
        
        array_push(self::$instances, $authenticator);
        
        return $authenticator;
    }

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
        if ($this->realaccount === null) return $this;
        
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
        if ($this->realaccount === null) return $this;
        
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
        if ($this->account === null) return $this;
        
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
        if ($this->account === null) throw new AccountRequiredException();
        
        static::StaticRequireCrypto($this->input, $this->account, $this->session); return $this;    
    }
    
    /** @see Authenticator::RequireCrypto() */
    public static function StaticRequireCrypto(Input $input, Account $account, ?Session $session = null) : void
    {
        if ($account->CryptoAvailable()) return;
        
        if (!$account->hasCrypto()) throw new CryptoInitRequiredException();
        
        $password = $input->GetOptParam('auth_password', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        $recoverykey = $input->GetOptParam('auth_recoverykey', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        if ($session !== null)
        {
            try { $account->UnlockCryptoFromKeySource($session); }
            catch (DecryptionFailedException $e) { 
                throw new AuthenticationFailedException(); }
        }
        else if ($recoverykey !== null)
        {
            try { $account->UnlockCryptoFromRecoveryKey($recoverykey); }
            catch (DecryptionFailedException | RecoveryKeyFailedException $e) { 
                throw new AuthenticationFailedException(); }
        }
        else if ($password !== null)
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e) { 
                throw new AuthenticationFailedException(); }
        }
        else throw new CryptoKeyRequiredException();
    }
  
    /** 
     * Runs TryRequireCrypto() on all instantiated authenticators 
     * for $account and throws if not unlocked 
     */
    public static function RequireCryptoFor(Account $account) : void
    {
        foreach (self::$instances as $auth) 
        {
            if ($auth->TryGetAccount() === $account)
                $auth->TryRequireCrypto();
        }
        
        if (!$account->CryptoAvailable())
            throw new CryptoKeyRequiredException();
    }
}