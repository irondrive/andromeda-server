<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParams};

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Authenticator.php");
require_once(ROOT."/apps/accounts/AuthEntity.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/ContactInfo.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/RecoveryKey.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");
require_once(ROOT."/apps/accounts/auth/Local.php");

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;
use Andromeda\Core\DecryptionFailedException;
use Andromeda\Core\EmailUnavailableException;

use Andromeda\Core\Database\ObjectNotFoundException;
use Andromeda\Core\Exceptions\NotImplementedException;
use Andromeda\Core\IOFormat\SafeParamInvalidException;

class AccountExistsException extends Exceptions\ClientErrorException          { public $message = "ACCOUNT_ALREADY_EXISTS"; }
class GroupExistsException extends Exceptions\ClientErrorException            { public $message = "GROUP_ALREADY_EXISTS"; }
class ContactInfoExistsException extends Exceptions\ClientErrorException      { public $message = "CONTACTINFO_ALREADY_EXISTS"; }
class GroupMembershipExistsException extends Exceptions\ClientErrorException  { public $message = "GROUPMEMBERSHIP_ALREADY_EXISTS"; }

class ChangeExternalPasswordException extends Exceptions\ClientErrorException { public $message = "CANNOT_CHANGE_EXTERNAL_PASSWORD"; }
class RecoveryKeyFailedException extends Exceptions\ClientErrorException      { public $message = "CANNOT_GENERATE_RECOVERY_KEY"; }
class RecoveryKeyDeliveryException extends Exceptions\ClientErrorException    { public $message = "CANNOT_DELIVER_RECOVERY_KEY"; }
class OldPasswordRequiredException extends Exceptions\ClientErrorException    { public $message = "OLD_PASSWORD_REQUIRED"; }
class NewPasswordRequiredException extends Exceptions\ClientErrorException    { public $message = "NEW_PASSWORD_REQUIRED"; }

class EmailAddressRequiredException extends Exceptions\ClientDeniedException   { public $message = "EMAIL_ADDRESS_REQUIRED"; }
class MandatoryGroupException extends Exceptions\ClientDeniedException         { public $message = "GROUP_MEMBERSHIP_REQUIRED"; }

class UnknownAuthSourceException extends Exceptions\ClientNotFoundException      { public $message = "UNKNOWN_AUTHSOURCE"; }
class UnknownAccountException extends Exceptions\ClientNotFoundException         { public $message = "UNKNOWN_ACCOUNT"; }
class UnknownGroupException extends Exceptions\ClientNotFoundException           { public $message = "UNKNOWN_GROUP"; }
class UnknownClientException extends Exceptions\ClientNotFoundException          { public $message = "UNKNOWN_CLIENT"; }
class UnknownSessionException extends Exceptions\ClientNotFoundException         { public $message = "UNKNOWN_SESSION"; }
class UnknownTwoFactorException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_TWOFACTOR"; }
class UnknownContactInfoException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_CONTACTINFO"; }
class UnknownGroupMembershipException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_GROUPMEMBERSHIP"; }

class AccountsApp extends AppBase
{   
    private Config $config; 
    private ?Authenticator $authenticator;
    
    public static function getVersion() : array { return array(0,0,1); } 
    
    public function __construct(Main $api)
    {
        parent::__construct($api);   
        
        try { $this->config = Config::Load($api->GetDatabase()); }
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }        
    }
    
    public function Run(Input $input)
    {   
        $this->authenticator = Authenticator::TryAuthenticate($this->API->GetDatabase(), $input);
        
        switch($input->GetAction())
        {       
            case 'getconfig':           return $this->GetConfig($input); break;
            case 'getextauthsources':   return $this->GetExtAuthSources($input); break;
            
            case 'getaccount':          return $this->GetAccount($input); break;
            case 'setfullname':         return $this->SetFullName($input); break;
            case 'changepassword':      return $this->ChangePassword($input); break;
            case 'emailrecovery':       return $this->EmailRecovery($input); break;
            
            case 'createaccount':       return $this->CreateAccount($input); break;
            case 'unlockaccount':       return $this->UnlockAccount($input); break;            
            case 'createsession':       return $this->CreateSession($input); break;
            
            case 'createrecoverykeys':  return $this->CreateRecoveryKeys($input); break;
            case 'createtwofactor':     return $this->CreateTwoFactor($input); break;
            case 'verifytwofactor':     return $this->VerifyTwoFactor($input); break;
            case 'createcontactinfo':   return $this->CreateContactInfo($input); break;
            case 'verifycontactinfo':   return $this->VerifyContactInfo($input); break;
            
            case 'deleteaccount':       return $this->DeleteAccount($input); break;
            case 'deletesession':       return $this->DeleteSession($input); break;
            case 'deleteclient':        return $this->DeleteClient($input); break;
            case 'deleteallauth':       return $this->DeleteAllAuth($input); break;
            case 'deletetwofactor':     return $this->DeleteTwoFactor($input); break;
            case 'deletecontactinfo':   return $this->DeleteContactInfo($input); break; 
            
            case 'listaccounts':        return $this->ListAccounts($input); break;
            case 'listgroups':          return $this->ListGroups($input); break;
            case 'creategroup':         return $this->CreateGroup($input); break;
            case 'deletegroup':         return $this->DeleteGroup($input); break;
            case 'addgroupmember':      return $this->AddGroupMember($input); break;
            case 'removegroupmember':   return $this->RemoveGroupmember($input); break;
            
            default: throw new UnknownActionException();
        }
    }
    
    private function StandardReturn(Input $input, ?array $return = null, ?Account $account = null) : ?array
    {
        if ($account === null) $account = $this->authenticator->GetAccount();
        $fullget = $input->TryGetParam("getaccount", SafeParam::TYPE_BOOL) ?? false;
        if ($fullget && $return !== null) $return['account'] = $account->GetClientObject();
        else if ($fullget) $return = $account->GetClientObject();
        return $return;
    }
    
    protected function GetConfig(Input $input) : array
    {
        $account = $this->authenticator->GetAccount();
        $admin = $account !== null && $account->isAdmin();

        return $this->config->GetClientObject($admin ? Config::OBJECT_ADMIN : Config::OBJECT_SIMPLE);
    }
    
    protected function SetConfig(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        // TODO implement setconfig
        
        return $this->config->GetClientObject(Config::OBJECT_ADMIN);
    }
    
    protected function GetExtAuthSources(Input $input) : array
    {
        $data = array(); $sources = Auth\SourcePointer::LoadAll($this->API->GetDatabase());        
        foreach ($sources as $source) array_push($data, $source->GetClientObject());        
        return $data;
    }
    
    protected function GetAccount(Input $input) : ?array
    {
        if ($this->authenticator === null) return null;
        else return $this->authenticator->GetAccount()->GetClientObject();
    }
    
    protected function ChangePassword(Input $input) : ?array
    {        
        $new_password = $input->GetParam('new_password',SafeParam::TYPE_RAW);
        $recoverykey = $input->TryGetParam("recoverykey", SafeParam::TYPE_ALPHANUM);

        if ($recoverykey !== null)
        {
            $username = $input->GetParam("username", SafeParam::TYPE_TEXT);
            $account = Account::TryLoadByUsername($this->API->GetDatabase(), $username);
            if ($account === null) throw new AuthenticationFailedException();
        }
        else
        {
            if ($this->authenticator === null) throw new AuthenticationFailedException();
            $account = $this->authenticator->GetAccount();   
        }       
        
        if (!$account->GetAuthSource() instanceof Auth\Local) 
            throw new ChangeExternalPasswordException();
                
        if ($recoverykey !== null)
        {
            if (!$account->CheckRecoveryCode($recoverykey)) 
                throw new AuthenticationFailedException();
        }
        else 
        {
            $this->authenticator->TryRequireCrypto();
            if (!$this->authenticator->isSudoUser()) 
                $this->authenticator->RequirePassword();
        }
        
        $account->ChangePassword($new_password);

        return $this->StandardReturn($input, null, $account);
    }
    
    private function capitalizeWords($str){ 
        return implode(" ",array_map(function($p){ 
            return strtoupper(substr($p,0,1)).substr($p,1); 
        }, explode(" ", trim($str)))); }
    
    protected function SetFullName(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $fullname = $this->capitalizeWords($input->GetParam("fullname", SafeParam::TYPE_NAME));
        $this->authenticator->GetAccount()->SetFullName($fullname);
        
        return $this->StandardReturn($input);
    }
    
    protected function EmailRecovery(Input $input) : array
    {
        if ($this->authenticator !== null) throw new Exceptions\ClientDeniedException();
        
        $username = $input->GetParam("username", SafeParam::TYPE_TEXT);
        $account = Account::TryLoadByUsername($this->API->GetDatabase(), $username);
        if ($account === null) throw new UnknownAccountException();
        
        if ($account->hasCrypto()) throw new RecoveryKeyFailedException();        

        $key = RecoveryKey::Create($this->API->GetDatabase(), $account)->InitializeKey();   
        
        $subject = "Andromeda Account Recovery Key";
        $body = "Your recovery key is: $key";
        
        try { $account->SendMailTo($this->API->GetConfig()->GetMailer(), $subject, $body); } 
        catch (EmailUnavailableException $e) { throw new RecoveryKeyDeliveryException(); }
        
        return $account->GetEmailRecipients(true);
    }
    
    protected function CreateAccount(Input $input) : array
    {
        if ($this->authenticator !== null) $this->authenticator->RequireAdmin();        
        else if (!$this->config->GetAllowCreateAccount()) throw new Exceptions\ClientDeniedException();
        $admin = $this->authenticator !== null;

        $emailasuser = $this->config->GetUseEmailAsUsername();
        $requireemail = $this->config->GetRequireContact();
        $username = null; $emailaddr = null;
        
        if ($emailasuser || $requireemail >= Config::CONTACT_EXIST) $emailaddr = $input->GetParam("email", SafeParam::TYPE_EMAIL);        
        $username = $emailasuser ? $emailaddr : $input->GetParam("username", SafeParam::TYPE_ALPHANUM);
        $password = $input->GetParam("password", SafeParam::TYPE_RAW);
        
        $database = $this->API->GetDatabase();        
        if (Account::TryLoadByUsername($database, $username) !== null) throw new AccountExistsException();
        if ($emailaddr !== null && ContactInfo::TryLoadByInfo($database, $emailaddr) !== null) throw new AccountExistsException();

        $account = Account::Create($database, Auth\Local::Load($database), $username, $password);
        
        if ($emailaddr !== null) $contact = ContactInfo::Create($database, $account, ContactInfo::TYPE_EMAIL, $emailaddr);

        if (!$admin && $requireemail >= Config::CONTACT_VALID)
        {
            $contact->SetIsValid(false);
            
            $code = Utilities::Random(8);
            $account->setEnabled(false)->setUnlockCode($code);
            
            $mailer = $this->API->GetConfig()->GetMailer();
            $to = array(new EmailRecipient($emailaddr, $username));
            
            $code = Utilities::Random();
            
            $subject = "Andromeda Account Validation Code";
            $body = "Your validation code is: $code";
            
            try { $mailer->SendMail($subject, $body, $to); }
            catch (EmailUnavailableException $e) { throw new RecoveryKeyDeliveryException(); }
        }
        
        return $account->GetClientObject();
    }
    
    protected function UnlockAccount(Input $input) : ?array
    {
        if ($this->authenticator !== null) throw new Exceptions\ClientDeniedException();
        
        $accountid = $input->GetParam("accountid", SafeParam::TYPE_ID);        
        $account = Account::TryLoadByID($this->API->GetDatabase(), $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        if (!$this->authenticator->GetRealAccount()->isAdmin())
        {
            $code = $input->GetParam("unlockcode", SafeParam::TYPE_ALPHANUM);
            if ($account->getUnlockCode() !== $code) throw new AuthenticationFailedException();           
        }
        $account->setUnlockCode(null)->setEnabled(null);
        
        $contacts = $account->GetContactInfos();
        if (count($contacts) !== 1) throw new NotImplementedException();    // TODO can there ever be > 1 ?
        array_values($contacts)[0]->SetIsValid(true);
        
        return $this->StandardReturn($input, null, $account);
    }
    
    protected function CreateSession(Input $input) : array
    {
        if ($this->authenticator !== null) throw new Exceptions\ClientDeniedException();
        
        $username = $input->GetParam("username", SafeParam::TYPE_TEXT);
        $password = $input->GetParam("auth_password", SafeParam::TYPE_RAW); 
        
        $database = $this->API->GetDatabase();
        
        /* load the authentication source being used - could be local, or an LDAP server, etc. */
        if (($authsource = $input->TryGetParam("authsourceid", SafeParam::TYPE_ID)) !== null) 
        {
            $authsource = Auth\SourcePointer::TryLoadSourceByPointer($database, $authsource);
            if ($authsource === null) throw new UnknownAuthSourceException();
        }
        else $authsource = Auth\Local::Load($database);     
        
        /* try loading by username, or even by an email address */
        $account = Account::TryLoadByUsername($database, $username);
        if ($account === null) $account = Account::TryLoadByContactInfo($database, $username);
        
        /* if we found an account, verify the password and correct authsource */
        if ($account !== null)
        {
            if ($account->GetAuthSource() === null && !($authsource instanceof Auth\Local)) throw new AuthenticationFailedException();
            else if ($account->GetAuthSource() !== null && $account->GetAuthSource() !== $authsource) throw new AuthenticationFailedException();
            
            if (!$account->VerifyPassword($password)) throw new AuthenticationFailedException();
        }
        /* if no account and using external auth, try the password, and if success, create a new account on the fly */
        else if (!($authsource instanceof Auth\Local))
        {            
            if (!$authsource->VerifyPassword($username, $password))
                throw new AuthenticationFailedException();
            
            $account = Account::Create($this->API->GetDatabase(), $authsource, $username, $password);    
        }
        else throw new AuthenticationFailedException();
        
        if (!$account->isEnabled()) throw new AccountDisabledException();
        
        $clientid = $input->TryGetParam("auth_clientid", SafeParam::TYPE_ID);
        $clientkey = $input->TryGetParam("auth_clientkey", SafeParam::TYPE_ALPHANUM);
        
        /* if a clientid is provided, check that it and the clientkey are correct */
        if ($clientid !== null && $clientkey !== null)
        {
            if ($account->ForceTwoFactor()) Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $client = Client::TryLoadByID($database, $clientid);
            if ($client === null || !$client->CheckMatch($input->GetAddress(), $clientkey)) 
                throw new UnknownClientException();
        } 
        else /* if no clientkey, require either a recoverykey or twofactor, create a client */
        { 
            if (($recoverykey = $input->TryGetParam("recoverykey", SafeParam::TYPE_ALPHANUM)) !== null)
            {
                if (!$account->CheckRecoveryCode($recoverykey))
                    throw new AuthenticationFailedException();
            }
            else Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $client = Client::Create($input->GetAddress(), $database, $account);
        }
        
        /* unlock account crypto - failure means the password source must've changed without updating crypto */
        if ($account->hasCrypto())
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e)
            {
                $old_password = $input->TryGetParam("old_password", SafeParam::TYPE_RAW);
                if ($old_password === null) throw new OldPasswordRequiredException();
                try { $account->ChangePassword($password, $old_password); }
                catch (DecryptionFailedException $e) { throw new AuthenticationFailedException(); }
            }
        }
        
        /* check account password age, possibly require a new one */
        if (!$account->CheckPasswordAge())
        {
            $new_password = $input->TryGetParam('new_password',SafeParam::TYPE_RAW);
            if ($new_password === null) throw new NewPasswordRequiredException();
            $account->ChangePassword($new_password, $password);
        }
        
        /* delete old session associated with this client, create a new one */
        $session = $client->GetSession();
        if ($session !== null) $session->Delete();

        $session = Session::Create($database, $account, $client);
        
        /* update object dates */
        $session->setActiveDate();
        $client->setLoggedonDate()->setActiveDate();
        $account->setLoggedonDate()->setActiveDate();
        
        $return = $client->GetClientObject(Client::OBJECT_WITHSECRET);

        return $this->StandardReturn($input, $return, $account);
    }
    
    protected function CreateRecoveryKeys(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->TryRequireTwoFactor()->RequirePassword()->RequireCrypto();
        
        $account = $this->authenticator->GetAccount();
        
        $keys = RecoveryKey::CreateSet($this->API->GetDatabase(), $account);
        
        $output = array_map(function($key){ return $key->InitializeKey(); }, $keys);
        
        $return = array('recoverykeys' => $output);
        
        return $this->StandardReturn($input, $return);
    }
    
    protected function CreateTwoFactor(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequirePassword()->RequireCrypto();
        
        $comment = $input->TryGetParam('comment', SafeParam::TYPE_TEXT);
        
        $twofactor = TwoFactor::Create($this->API->GetDatabase(), $this->authenticator->GetAccount(), $comment);
        
        $return = array('twofactor' => $twofactor->GetClientObject(TwoFactor::OBJECT_WITHSECRET) );
        
        return $this->StandardReturn($input, $return);
    }
    
    protected function VerifyTwoFactor(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequireCrypto();
        
        $account = $this->authenticator->GetAccount();
        
        $twofactorid = $input->GetParam("twofactorid", SafeParam::TYPE_ID);
        $twofactor = TwoFactor::TryLoadByID($this->API->GetDatabase(), $twofactorid);
        if ($twofactor === null || $twofactor->GetAccount() !== $account) throw new UnknownTwoFactorException();
        
        $code = $input->GetParam("code", SafeParam::TYPE_ALPHANUM);
        if ($twofactor->CheckCode($code)) $twofactor->SetIsValid();
        else throw new AuthenticationFailedException();
        
        return $this->StandardReturn($input);
    }
    
    protected function CreateContactInfo(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $type = $input->GetParam('type', SafeParam::TYPE_INT); switch ($type)
        {
            case ContactInfo::TYPE_EMAIL: $info = $input->GetParam('info', SafeParam::TYPE_EMAIL); break;                
            default: throw new SafeParamInvalidException("CONTACTINFO_TYPE");
        }        
        
        if (ContactInfo::TryLoadByInfo($this->API->GetDatabase(), $info) !== null) throw new ContactInfoExistsException();

        $contact = ContactInfo::Create($this->API->GetDatabase(), $account, $type, $info);
        
        if ($this->config->GetRequireContact() >= Config::CONTACT_VALID && !$this->authenticator->GetRealAccount()->isAdmin())
        { 
            $code = Utilities::Random(); $contact->SetIsValid(false)->SetUnlockCode($code);
            
            switch ($type)
            {
                case ContactInfo::TYPE_EMAIL:                    
                    $mailer = $this->API->GetConfig()->GetMailer();
                    $to = array(new EmailRecipient($info, $account->GetUsername()));                    
                    $subject = "Andromeda Email Validation Code";
                    $body = "Your validation code is: $code";                    
                    $mailer->SendMail($subject, $body, $to);                    
                break;            
            } 
        }
        
        return $this->StandardReturn($input);
    }
    
    protected function VerifyContactInfo(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $type = $input->GetParam('type', SafeParam::TYPE_INT); switch ($type)
        {
            case ContactInfo::TYPE_EMAIL: $info = $input->GetParam('info', SafeParam::TYPE_EMAIL); break;            
            default: throw new SafeParamInvalidException("CONTACTINFO_TYPE");
        }
        
        $contact = ContactInfo::TryLoadByInfo($this->API->GetDatabase(), $info);
        if ($contact === null || $contact->GetAccount() !== $account) throw new UnknownContactInfoException();        
        
        if (!$this->authenticator->GetRealAccount()->isAdmin())
        {
            $code = $input->GetParam("unlockcode", SafeParam::TYPE_ALPHANUM);
            if ($contact->GetUnlockCode() !== $code) throw new AuthenticationFailedException();
        }

        $contact->SetUnlockCode(null)->SetIsValid(true);

        return $this->StandardReturn($input);
    }
    
    protected function DeleteAccount(Input $input) : void
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequirePassword();
        
        if (!$this->authenticator->isSudoUser()) $this->authenticator->TryRequireTwoFactor();
            
        $this->authenticator->GetAccount()->Delete();
    }
    
    protected function DeleteSession(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $session = $this->authenticator->GetSession();
        
        $sessionid = $input->TryGetParam("sessionid", SafeParam::TYPE_ID);

        if ($this->authenticator->isSudoUser() || $sessionid !== null)
        {
            if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
            $session = Session::TryLoadByID($this->API->GetDatabase(), $sessionid);
            if ($session === null || $session->GetAccount() !== $account) throw new UnknownSessionException();
        }
        
        if ($session->GetAccount()->HasTwoFactor()) $session->Delete();
        else $session->GetClient()->Delete();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteClient(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $client = $this->authenticator->GetClient();
        
        $clientid = $input->TryGetParam("clientid", SafeParam::TYPE_ID);
        
        if ($this->authenticator->isSudoUser() || $clientid !== null)
        {
            if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
            $client = Client::TryLoadByID($this->API->GetDatabase(), $clientid);
            if ($client === null || $client->GetAccount() !== $account) throw new UnknownClientException();
        }
        
        $client->Delete();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteAllAuth(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
        
        $clients = $this->authenticator->GetAccount()->GetClients();        
        foreach ($clients as $client) $client->Delete();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteTwoFactor(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequirePassword();
        $account = $this->authenticator->GetAccount();
        
        $twofactorid = $input->GetParam("twofactorid", SafeParam::TYPE_ID);
        $twofactor = TwoFactor::TryLoadByID($this->API->GetDatabase(), $twofactorid);
        if ($twofactor === null || $twofactor->GetAccount() !== $account) throw new UnknownTwoFactorException();

        $twofactor->Delete();
        
        return $this->StandardReturn($input);
    }    
    
    protected function DeleteContactInfo(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $type = $input->GetParam('type', SafeParam::TYPE_INT); switch ($type)
        {
            case ContactInfo::TYPE_EMAIL: $info = $input->GetParam('info', SafeParam::TYPE_EMAIL); break;                
            default: throw new SafeParamInvalidException("CONTACTINFO_TYPE");
        }     
        
        $contact = ContactInfo::TryLoadByInfo($this->API->GetDatabase(), $info);
        if ($contact === null || $contact->GetAccount() !== $account) throw new UnknownContactInfoException();
        
        $contact->Delete();
        
        if ($type == ContactInfo::TYPE_EMAIL)
        {
            $require = $this->config->GetRequireEmails();
            if ($require >= Config::CONTACT_EXIST && !$account->HasContactInfos())
                throw new EmailAddressRequiredException();  
        }

        return $this->StandardReturn($input);
    }
    
    /* everything here and below is admin only! */
    
    protected function ListAccounts(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $limit = $input->TryGetParam("limit", SafeParam::TYPE_INT); 
        $full = $input->TryGetParam("full", SafeParam::TYPE_BOOL) ?? false;
        $type = ($full ? Account::OBJECT_FULL : Account::OBJECT_SIMPLE) | Account::OBJECT_ADMIN;
        
        $accounts = Account::LoadAll($this->API->GetDatabase(), $limit);        
        return array_map(function($account)use($type){ return $account->GetClientObject($type); }, $accounts);
    }
    
    protected function ListGroups(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $limit = $input->TryGetParam("limit", SafeParam::TYPE_INT);
        $groups = Group::LoadAll($this->API->GetDatabase(), $limit);
        return array_map(function($group){ return $group->GetClientObject(); }, $groups);
    }
    
    protected function CreateGroup(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $name = $input->GetParam("name", SafeParam::TYPE_NAME);
        $priority = $input->TryGetParam("priority", SafeParam::TYPE_INT);
        $comment = $input->TryGetParam("comment", SafeParam::TYPE_TEXT);
        
        $duplicate = Group::TryLoadByName($this->API->GetDatabase(), $name);
        if ($duplicate !== null) throw new GroupExistsException();
        
        $group = Group::Create($this->API->GetDatabase(), $name, $priority, $comment);
        
        $return = array('group' => $group->GetClientObject());  
        
        return $this->StandardReturn($input, $return); 
    }
    
    protected function DeleteGroup(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $groupid = $input->GetParam("groupid", SafeParam::TYPE_ID);
        $group = Group::TryLoadByID($this->API->GetDatabase(), $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($this->config->GetDefaultGroup() === $group)
            $this->config->SetDefaultGroup(null);
        
        $group->Delete();
        
        return $this->StandardReturn($input);        
    }
    
    protected function AddGroupMember(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("accountid", SafeParam::TYPE_ID);
        $groupid = $input->GetParam("groupid", SafeParam::TYPE_ID);
        
        $account = Account::TryLoadByID($this->API->GetDatabase(), $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->API->GetDatabase(), $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if (!$account->HasGroup($group)) $account->AddGroup($group);
        else throw new GroupMembershipExistsException();

        return $this->StandardReturn($input);
    }
    
    protected function RemoveGroupMember(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("accountid", SafeParam::TYPE_ID);
        $groupid = $input->GetParam("groupid", SafeParam::TYPE_ID);
        
        $account = Account::TryLoadByID($this->API->GetDatabase(), $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->API->GetDatabase(), $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        $default1 = $this->config->GetDefaultGroup();
        $default2 = $membership->GetAccount()->GetAuthSource()->GetAccountGroup();
        if ($group === $default1 || $group === $default2) throw new MandatoryGroupException();
        
        if ($account->HasGroup($group)) $account->RemoveGroup($group);
        else throw new UnknownGroupMembershipException();
        
        return $this->StandardReturn($input);
    }

    public static function Test(Main $api, Input $input)
    {
        $config = Config::Load($api->GetDatabase());
        
        $old1 = $config->GetAllowCreateAccount(); $config->SetAllowCreateAccount(true);
        $old2 = $config->GetUseEmailAsUsername(); $config->SetUseEmailAsUsername(false);
        $old3 = $config->GetRequireContact(); $config->SetRequireContact(Config::CONTACT_EXIST);

        $results = array(); $app = "accounts";
        
        $email = Utilities::Random(8)."@unittest.com";
        $user = Utilities::Random(8); 
        $password = Utilities::Random(16);
        
        $test = $api->Run(new Input($app,'createaccount', (new SafeParams())
            ->AddParam('email','email',$email)
            ->AddParam('alphanum','username',$user)
            ->AddParam('raw','password',$password)));
        array_push($results, $test); 
        $api->GetDatabase()->saveObjects();
        
        $test = $api->Run(new Input($app,'createsession', (new SafeParams())
            ->AddParam('text','username',$user)
            ->AddParam('raw','auth_password',$password)));
        array_push($results, $test);
        $api->GetDatabase()->saveObjects();
        
        $sessionid = $test['session']['id'];
        $sessionkey = $test['session']['authkey'];
        
        $password2 = Utilities::Random(16);
        $test = $api->Run(new Input($app,'changepassword', (new SafeParams())
            ->AddParam('id','auth_sessionid',$sessionid)
            ->AddParam('alphanum','auth_sessionkey',$sessionkey)
            ->AddParam('bool','getaccount',true)
            ->AddParam('raw','auth_password',$password)
            ->AddParam('raw','new_password',$password2)));
        array_push($results, $test); 
        $api->GetDatabase()->saveObjects();
        $password = $password2;
        
        $test = $api->Run(new Input($app,'deleteaccount', (new SafeParams())
            ->AddParam('id','auth_sessionid',$sessionid)
            ->AddParam('alphanum','auth_sessionkey',$sessionkey)
            ->AddParam('raw','auth_password',$password)));
        array_push($results, $test);
        $api->GetDatabase()->saveObjects();
        
        $config->SetAllowCreateAccount($old1);
        $config->SetUseEmailAsUsername($old2);
        $config->SetRequireContact($old3);
        
        return $results;
    }
}

