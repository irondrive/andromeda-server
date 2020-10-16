<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParamException};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");

class AuthenticationFailedException extends Exceptions\ClientDeniedException { public $message = "AUTHENTICATION_FAILED"; }

class AccountDisabledException extends AuthenticationFailedException      { public $message = "ACCOUNT_DISABLED"; }
class InvalidSessionException extends AuthenticationFailedException       { public $message = "INVALID_SESSION"; }

class AdminRequiredException extends AuthenticationFailedException        { public $message = "ADMIN_REQUIRED"; }
class TwoFactorRequiredException extends AuthenticationFailedException    { public $message = "TWOFACTOR_REQUIRED"; }
class PasswordRequiredException extends AuthenticationFailedException     { public $message = "PASSWORD_REQUIRED"; }
class CryptoKeyRequiredException extends AuthenticationFailedException    { public $message = "CRYPTOKEY_REQUIRED"; }

use Andromeda\Core\DecryptionFailedException;

class Authenticator
{
    private Account $account; 
    private Session $session; 
    private Client $client;     
    private Input $input;
    
    private ObjectDatabase $database; 
   
    public function GetAccount() : Account { return $this->account; }
    public function GetSession() : Session { return $this->session; }
    public function GetClient() : Client { return $this->client; }
    
    private bool $issudouser = false; public function isSudoUser() : bool { return $this->issudouser; }
    private ?Account $realaccount;    public function GetRealAccount() : Account { return $this->realaccount; }
    
    private function __construct(ObjectDatabase $database, Input $input)
    {
        $auth = $input->GetAuth();

        $sessionid = ($auth !== null) ? $auth->GetUsername() : $input->GetParam('auth_sessionid',SafeParam::TYPE_ID);
        $sessionkey = ($auth !== null) ? $auth->GetPassword() : $input->GetParam('auth_sessionkey',SafeParam::TYPE_ALPHANUM);

        $session = Session::TryLoadByID($database, $sessionid);
        
        if ($session === null || !$session->CheckMatch($sessionkey)) throw new InvalidSessionException();
            
        $account = $session->GetAccount(); $client = $session->GetClient();
        
        $this->realaccount = $account; $this->session = $session; $this->client = $client;
        $this->database = $database; $this->input = $input;
        
        if (!$client->CheckAgentMatch($input->GetAddress())) throw new InvalidSessionException();
        
        if (!$account->isEnabled()) throw new AccountDisabledException();
        
        $session->setActiveDate(); $account->setActiveDate(); $client->setActiveDate();
        
        if ($input->HasParam('auth_sudouser') && $account->isAdmin())
        {
            $sudouser = $input->TryGetParam('auth_sudouser', SafeParam::TYPE_ID);
            if ($sudouser === null) throw new AuthenticationFailedException();
            else
            {
                $this->issudouser = true;
                $account = Account::TryLoadByID($database, $sudouser);
                if ($account === null) throw new UnknownAccountException();   
            }      
        }
        
        $this->account = $account;
    }
    
    public static function Authenticate(ObjectDatabase $database, Input $input) : self
    {
        return new self($database, $input);
    }
    
    public static function TryAuthenticate(ObjectDatabase $database, Input $input) : ?self
    {
        try { return new self($database, $input); }
        catch (AuthenticationFailedException | SafeParamException $e) { return null; }
    }
    
    public function RequireAdmin() : self
    {
        if (!$this->account->isAdmin()) throw new AdminRequiredException(); return $this;
    }
    
    public function TryRequireTwoFactor() : self
    {
        self::StaticTryRequireTwoFactor($this->input, $this->realaccount, $this->session); return $this;
    }
    
    public static function StaticTryRequireTwoFactor(Input $input, Account $account, ?Session $session = null) : void
    {
        if (!$account->HasTwoFactor()) return; 
        self::StaticRequireCrypto($input, $account);
        
        $twofactor = $input->TryGetParam('auth_twofactor',SafeParam::TYPE_ALPHANUM);
        if ($twofactor === null) throw new TwoFactorRequiredException();
        else if (!$account->CheckTwoFactor($twofactor)) throw new AuthenticationFailedException();
    }
    
    public function RequirePassword(bool $allowRecovery = false) : self
    {
        $password = $this->input->TryGetParam('auth_password',SafeParam::TYPE_RAW);
        $recoverykey = $this->input->TryGetParam('recoverykey', SafeParam::TYPE_ALPHANUM);
        
        $account = $this->realaccount;
        
        if ($password !== null)
        {
            if (!$account->VerifyPassword($password))
                throw new AuthenticationFailedException();
        }
        else if ($recoverykey !== null && $allowRecovery)
        {
            if (!$account->CheckRecoveryCode($recoverykey))
                throw new AuthenticationFailedException();
        }
        else throw new PasswordRequiredException();

        return $this;
    }
    
    public function TryRequireCrypto(bool $allowRecovery = true) : self
    {
        if (!$this->account->hasCrypto()) return $this;
        return $this->RequireCrypto($allowRecovery);
    }
    
    public function RequireCrypto(bool $allowRecovery = true) : self
    {
        self::StaticRequireCrypto($this->input, $this->account, $allowRecovery); return $this;    
    }
    
    public static function StaticRequireCrypto(Input $input, Account $account, bool $allowRecovery = true) : void
    {
        if ($account->CryptoAvailable()) return;
        
        $password = $input->TryGetParam('auth_password', SafeParam::TYPE_RAW);
        $recoverykey = $input->TryGetParam('recoverykey', SafeParam::TYPE_ALPHANUM);
        
        if ($password !== null)
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
        }        
        else if ($recoverykey !== null && $allowRecovery)
        {
            if (!$account->CheckRecoveryCode($recoverykey))
                throw new AuthenticationFailedException();
        }        
        else throw new CryptoKeyRequiredException();
    }
  
}