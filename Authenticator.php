<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParamException};
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/GroupMembership.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");

class AuthenticationFailedException extends Exceptions\Client403Exception { public $message = "AUTHENTICATION_FAILED"; }

class AccountDisabledException extends AuthenticationFailedException      { public $message = "ACCOUNT_DISABLED"; }
class InvalidSessionException extends AuthenticationFailedException       { public $message = "INVALID_SESSION"; }

class AdminRequiredException extends AuthenticationFailedException        { public $message = "ADMIN_REQUIRED"; }
class TwoFactorRequiredException extends AuthenticationFailedException    { public $message = "TWOFACTOR_REQUIRED"; }
class PasswordRequiredException extends AuthenticationFailedException     { public $message = "PASSWORD_REQUIRED"; }
class CryptoKeyRequiredException extends AuthenticationFailedException    { public $message = "CRYPTOKEY_REQUIRED"; }

use Andromeda\Core\DecryptionFailedException;

class Authenticator
{
    private $account = null; private $session = null; private $client = null; 
    private $interface = null; private $database = null; private $input = null;
    
    public function GetAccount() : Account { return $this->account; }
    public function GetSession() : Session { return $this->session; }
    public function GetClient() : Client { return $this->client; }
    
    private $issudouser = false; public function isSudoUser() : bool { return $this->issudouser; }
    private $realaccount = null; public function GetRealAccount() : Account { return $this->realaccount; }
    
    private function __construct(ObjectDatabase $database, IOInterface $interface, Input $input)
    {
        $auth = $input->GetAuth();

        $sessionid = ($auth !== null) ? $auth->GetUsername() : $input->GetParam('auth_sessionid',SafeParam::TYPE_ID);
        $sessionkey = ($auth !== null) ? $auth->GetPassword() : $input->GetParam('auth_sessionkey',SafeParam::TYPE_ALPHANUM);
        
        $session = Session::TryLoadByID($database, $sessionid);     
        
        if ($session === null || !$session->CheckMatch($sessionkey)) throw new InvalidSessionException();
            
        $account = $session->GetAccount(); $client = $session->GetClient();
        
        $this->realaccount = $account; $this->session = $session; $this->client = $client;
        $this->interface = $interface; $this->database = $database; $this->input = $input;
        
        if (!$client->CheckAgentMatch($interface)) throw new InvalidSessionException();
        
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
    
    public static function Authenticate(ObjectDatabase $database, IOInterface $interface, Input $input) : self
    {
        return new self($database, $interface, $input);
    }
    
    public static function TryAuthenticate(ObjectDatabase $database, IOInterface $interface, Input $input) : ?self
    {
        try { return new self($database, $interface, $input); }
        catch (AuthenticationFailedException | SafeParamException $e) { return null; }
    }
    
    public function RequireAdmin() : self
    {
        if (!$this->account->isAdmin()) throw new AdminRequiredException(); return $this;
    }
    
    public function RequireTwoFactor() : self
    {
        self::StaticRequireTwoFactor($this->input, $this->realaccount, $this->session); return $this;
    }
    
    public static function StaticRequireTwoFactor(Input $input, Account $account, ?Session $session = null) : void
    {
        if (!$account->HasTwoFactor()) return; 
        self::StaticRequireCrypto($input, $account, $session);
        
        $twofactor = $input->TryGetParam('auth_twofactor',SafeParam::TYPE_ALPHANUM);
        if ($twofactor === null) throw new TwoFactorRequiredException();
        else if (!$account->CheckTwoFactor($twofactor)) throw new AuthenticationFailedException();
    }
    
    public function RequirePassword() : self
    {
        $password = $this->input->TryGetParam('auth_password',SafeParam::TYPE_RAW);
        if ($password === null) throw new PasswordRequiredException();
        
        $this->RequireCrypto(); 
     
        $account = $this->realaccount;
        
        if (!$account->CryptoAvailable())
        {
            if (!$account->VerifyPassword($password))
                throw new AuthenticationFailedException();
        }

        return $this;
    }   
    
    public function RequireCrypto() : self
    {
        self::StaticRequireCrypto($this->input, $this->account, $this->session); return $this;    
    }
    
    public static function StaticRequireCrypto(Input $input, Account $account, ?Session $session = null) : void
    {
        if ($account->CryptoAvailable() || !$account->hasCrypto()) return;
        
        $password = $input->TryGetParam('auth_password', SafeParam::TYPE_RAW);
        $cryptokey = $input->TryGetParam('auth_cryptokey', SafeParam::TYPE_ALPHANUM);
        
        if ($password !== null)
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
        }
        
        else if ($session !== null && $session->hasCrypto() && $cryptokey !== null)
        {
            try { $account->UnlockCryptoFromKeySource($session, $cryptokey); }
            catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
        }
        
        else throw new CryptoKeyRequiredException();
    }
  
}