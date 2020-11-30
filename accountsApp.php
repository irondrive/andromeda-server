<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{FullEmailer, EmailRecipient};
require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParams};

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Authenticator.php");
require_once(ROOT."/apps/accounts/AuthObject.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/ContactInfo.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/GroupStuff.php");
require_once(ROOT."/apps/accounts/KeySource.php");
require_once(ROOT."/apps/accounts/RecoveryKey.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");
require_once(ROOT."/apps/accounts/auth/Local.php");

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;
use Andromeda\Core\DecryptionFailedException;
use Andromeda\Core\MailSendException;

use Andromeda\Core\Database\ObjectNotFoundException;
use Andromeda\Core\Exceptions\NotImplementedException;
use Andromeda\Core\IOFormat\SafeParamInvalidException;

class AccountExistsException extends Exceptions\ClientErrorException          { public $message = "ACCOUNT_ALREADY_EXISTS"; }
class GroupExistsException extends Exceptions\ClientErrorException            { public $message = "GROUP_ALREADY_EXISTS"; }
class ContactInfoExistsException extends Exceptions\ClientErrorException      { public $message = "CONTACTINFO_ALREADY_EXISTS"; }
class GroupMembershipExistsException extends Exceptions\ClientErrorException  { public $message = "GROUPMEMBERSHIP_ALREADY_EXISTS"; }

class ChangeExternalPasswordException extends Exceptions\ClientErrorException { public $message = "CANNOT_CHANGE_EXTERNAL_PASSWORD"; }
class RecoveryKeyCreateException extends Exceptions\ClientErrorException      { public $message = "CANNOT_GENERATE_RECOVERY_KEY"; }
class OldPasswordRequiredException extends Exceptions\ClientErrorException    { public $message = "OLD_PASSWORD_REQUIRED"; }
class NewPasswordRequiredException extends Exceptions\ClientErrorException    { public $message = "NEW_PASSWORD_REQUIRED"; }

class EmailAddressRequiredException extends Exceptions\ClientDeniedException   { public $message = "EMAIL_ADDRESS_REQUIRED"; }
class MandatoryGroupException extends Exceptions\ClientDeniedException         { public $message = "GROUP_MEMBERSHIP_REQUIRED"; }

class UnknownMailerException extends Exceptions\ClientNotFoundException          { public $message = "UNKNOWN_MAILER"; }
class MailSendFailException extends Exceptions\ClientErrorException              { public $message = "MAIL_SEND_FAILURE"; }

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
    
    public static function getUsage() : array 
    { 
        return array(
            'phpinfo',
            'testmail [--mailid id]',
            '- AUTH ALL: [--auth_sessionid id --auth_sessionkey alphanum] [--auth_sudouser id]',
            'getconfig',
            'getauthsources',
            'getaccount',
            'setfullname --fullname name',
            'changepassword --username text --new_password raw (--auth_password raw | --auth_recoverykey text)',
            'emailrecovery --username text',
            'createaccount (--email email | --username alphanum) --password raw',
            'unlockaccount --account id --unlockcode alphanum',
            'createsession --username text --auth_password raw [--authsource id] [(--auth_clientid id --auth_clientkey alphanum) | --recoverykey text | --auth_twofactor int]',
            'createrecoverykeys --auth_password raw --auth_twofactor int',
            'createtwofactor --auth_password raw [--comment text]',
            'verifytwofactor --auth_twofactor int',
            'createcontactinfo --type int --info email',
            'verifycontactinfo --type int --unlockcode alphanum',
            'deleteaccount --auth_password raw --auth_twofactor int',
            'deletesession [--session id --auth_password raw]',
            'deleteclient [--client id --auth_password raw]',
            'deleteallauth --auth_password raw',
            'deletetwofactor --auth_password raw --twofactor id',
            'deletecontactinfo --type int --info email',
            'listaccounts [--limit int] [--offset int]',
            'listgroups [--limit int] [--offset int]',
            'creategroup --name name [--priority int] [--comment text]',
            'deletegroup --group id',
            'addgroupmember --account id --group id',
            'removegroupmember --acount id --group id'
        );
    }
    
    public function __construct(Main $api)
    {
        parent::__construct($api);   
        
        try { $this->config = Config::Load($api->GetDatabase()); }
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }        
    }

    public function Run(Input $input)
    {
        $this->authenticator = Authenticator::TryAuthenticate($this->API->GetDatabase(), $input, $this->API->GetInterface());

        switch($input->GetAction())
        {       
            case 'phpinfo':             return $this->PHPInfo($input); break;
            case 'testmail':            return $this->TestMail($input); break;
            
            case 'getconfig':           return $this->GetConfig($input); break;
            case 'getauthsources':      return $this->GetAuthSources($input); break;
            
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
    
    protected function PHPInfo(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $this->API->GetInterface()->SetOutmode(IOInterface::OUTPUT_NONE); phpinfo();
    }
    
    protected function TestMail(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();        
        
        $account = $this->authenticator->GetAccount();
        $subject = "Andromeda Email Test";
        $body = "This is a test email from Andromeda";
        
        if (($mailer = $input->TryGetParam('mailid', SafeParam::TYPE_ID)) !== null)
        {
            $mailer = FullEmailer::TryLoadByID($this->API->GetDatabase(), $mailer);
            if ($mailer === null) throw new UnknownMailerException();
        }
        else $mailer = $this->API->GetConfig()->GetMailer();        
        
        try { $mailer->SendMail($subject, $body, $account->GetMailTo()); }
        catch (MailSendException $e) { throw new MailSendFailException($e->getDetails()); }
        
        return array();
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
        
        return $this->config->GetClientObject(Config::OBJECT_ADMIN);
    }
    
    // TODO Get and Set config for the server also
    
    protected function GetAuthSources(Input $input) : array
    {
        $data = array(); $pointers = Auth\Pointer::LoadAll($this->API->GetDatabase());        
        foreach ($pointers as $pointer) array_push($data, $pointer->GetClientObject());        
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
        $recoverykey = $input->TryGetParam('recoverykey', SafeParam::TYPE_TEXT);
        
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
            if (!$account->CheckRecoveryKey($recoverykey)) 
                throw new AuthenticationFailedException();
        }
        else 
        {
            if (!$this->authenticator->isSudoUser()) 
                $this->authenticator->RequirePassword();
        }
        
        Authenticator::StaticTryRequireCrypto($input, $account);
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
        
        if ($account->hasCrypto() || $account->HasValidTwoFactor()) throw new RecoveryKeyCreateException();

        $key = RecoveryKey::Create($this->API->GetDatabase(), $account)->GetFullKey();   
        
        $subject = "Andromeda Account Recovery Key";
        $body = "Your recovery key is: $key";
        
        $this->API->GetConfig()->GetMailer()->SendMail($subject, $body, $account->GetMailTo());
        
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
            
            $mailer->SendMail($subject, $body, $to);
        }
        
        return $account->GetClientObject();
    }
    
    protected function UnlockAccount(Input $input) : ?array
    {
        if ($this->authenticator !== null) throw new Exceptions\ClientDeniedException();
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_ID);        
        $account = Account::TryLoadByID($this->API->GetDatabase(), $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        if (!$this->authenticator->GetRealAccount()->isAdmin())
        {
            $code = $input->GetParam("unlockcode", SafeParam::TYPE_ALPHANUM);
            if ($account->getUnlockCode() !== $code) throw new AuthenticationFailedException();           
        }
        $account->setUnlockCode(null)->setEnabled(null);
        
        $contacts = $account->GetContactInfos();
        if (count($contacts) !== 1) throw new NotImplementedException();
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
        if (($authsource = $input->TryGetParam("authsource", SafeParam::TYPE_ID)) !== null) 
        {
            $authsource = Auth\Pointer::TryLoadSourceByPointer($database, $authsource);
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
        
        $interface = $this->API->GetInterface();
        
        /* if a clientid is provided, check that it and the clientkey are correct */
        if ($clientid !== null && $clientkey !== null)
        {
            if ($account->ForceTwoFactor()) 
                Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $client = Client::TryLoadByID($database, $clientid);
            if ($client === null || !$client->CheckMatch($interface, $clientkey)) 
                throw new UnknownClientException();
        } 
        else /* if no clientkey, require either a recoverykey or twofactor, create a client */
        { 
            if (($recoverykey = $input->TryGetParam('recoverykey', SafeParam::TYPE_TEXT)) !== null)
            {
                if (!$account->CheckRecoveryKey($recoverykey))
                    throw new AuthenticationFailedException();
            }
            else Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $client = Client::Create($interface, $database, $account);
        }
        
        /* unlock account crypto - failure means the password source must've changed without updating crypto */
        if ($account->hasCrypto())
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e)
            {
                $old_password = $input->TryGetParam("old_password", SafeParam::TYPE_RAW);
                if ($old_password === null) throw new OldPasswordRequiredException();
                $account->UnlockCryptoFromPassword($old_password);
                
                $account->ChangePassword($password);
            }
        }
        
        /* check account password age, possibly require a new one */
        if (!$account->CheckPasswordAge())
        {
            $new_password = $input->TryGetParam('new_password',SafeParam::TYPE_RAW);
            if ($new_password === null) throw new NewPasswordRequiredException();
            $account->ChangePassword($new_password);
        }
        
        /* delete old session associated with this client, create a new one */
        $session = $client->GetSession();
        if ($session !== null) $session->Delete();

        $session = Session::Create($database, $account, $client);
        
        /* update object dates */
        $session->setActiveDate();
        $client->setLoggedonDate()->setActiveDate();
        $account->setLoggedonDate()->setActiveDate();
        
        $return = $client->GetClientObject(true);

        return $this->StandardReturn($input, $return, $account);
    }
    
    protected function CreateRecoveryKeys(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $this->authenticator->TryRequireTwoFactor()->RequirePassword()->TryRequireCrypto();        
        
        $keys = RecoveryKey::CreateSet($this->API->GetDatabase(), $account);
        
        $output = array_map(function($key){
            return $key->GetClientObject(true); }, $keys);
        
        $return = array('recoverykeys' => $output);
        
        return $this->StandardReturn($input, $return);
    }
    
    protected function CreateTwoFactor(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $database = $this->API->GetDatabase();
        
        $this->authenticator->RequirePassword()->TryRequireCrypto();
        
        if ($this->config->GetAllowCrypto() && !$account->hasCrypto())
        {
            $password = $input->GetParam('auth_password',SafeParam::TYPE_RAW);
            
            $account->InitializeCrypto($password);
            $this->authenticator->GetSession()->InitializeCrypto();
            Session::DeleteByAccountExcept($database, $this->authenticator->GetSession());
        }
        
        $comment = $input->TryGetParam('comment', SafeParam::TYPE_TEXT);
        
        $twofactor = TwoFactor::Create($database, $account, $comment);
        $recoverykeys = RecoveryKey::CreateSet($database, $account);
        
        $tfobj = $twofactor->GetClientObject(true);
        $keyobjs = array_map(function($key){ return $key->GetClientObject(true); }, $recoverykeys);
        
        $return = array('twofactor' => $tfobj, 'recoverykeys' => $keyobjs );
        
        return $this->StandardReturn($input, $return);
    }
    
    protected function VerifyTwoFactor(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->TryRequireCrypto();
        
        $account = $this->authenticator->GetAccount();
        $code = $input->GetParam("auth_twofactor", SafeParam::TYPE_INT);
        if (!$account->CheckTwoFactor($code, true)) throw new AuthenticationFailedException();
        
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
            $code = Utilities::Random(16); $contact->SetIsValid(false)->SetUnlockCode($code);
            
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
        
        $sessionid = $input->TryGetParam("session", SafeParam::TYPE_ID);

        if ($this->authenticator->isSudoUser() || $sessionid !== null)
        {
            if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
            $session = Session::TryLoadByAccountAndID($this->API->GetDatabase(), $account, $sessionid);
            if ($session === null) throw new UnknownSessionException();
        }
        
        if ($session->GetAccount()->HasValidTwoFactor()) $session->Delete();
        else $session->GetClient()->Delete();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteClient(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $client = $this->authenticator->GetClient();
        
        $clientid = $input->TryGetParam("client", SafeParam::TYPE_ID);
        
        if ($this->authenticator->isSudoUser() || $clientid !== null)
        {
            if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
            $client = Client::TryLoadByAccountAndID($this->API->GetDatabase(), $account, $clientid);
            if ($client === null) throw new UnknownClientException();
        }
        
        $client->Delete();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteAllAuth(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        if (!$this->authenticator->isSudoUser()) $this->authenticator->RequirePassword();
        
        $this->authenticator->GetAccount()->DeleteClients();
        
        return $this->StandardReturn($input);
    }
    
    protected function DeleteTwoFactor(Input $input) : ?array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequirePassword();
        $account = $this->authenticator->GetAccount();
        
        $twofactorid = $input->GetParam("twofactor", SafeParam::TYPE_ID);
        $twofactor = TwoFactor::TryLoadByAccountAndID($this->API->GetDatabase(), $account, $twofactorid); 
        if ($twofactor === null) throw new UnknownTwoFactorException();

        $twofactor->Delete();
        
        if (!$account->HasTwoFactor() && $account->hasCrypto()) 
        {
            $this->authenticator->RequireCrypto();
            $account->DestroyCrypto();
        }
        
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
        $offset = $input->TryGetparam("offset", SafeParam::TYPE_INT);
        
        $full = $input->TryGetParam("full", SafeParam::TYPE_BOOL) ?? false;
        $type = ($full ? Account::OBJECT_FULL : Account::OBJECT_SIMPLE) | Account::OBJECT_ADMIN;
        
        $accounts = Account::LoadAll($this->API->GetDatabase(), $limit, $offset);        
        return array_map(function($account)use($type){ return $account->GetClientObject($type); }, $accounts);
    }
    
    protected function ListGroups(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $limit = $input->TryGetParam("limit", SafeParam::TYPE_INT);
        $offset = $input->TryGetparam("offset", SafeParam::TYPE_INT);
        
        $groups = Group::LoadAll($this->API->GetDatabase(), $limit, $offset);
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
        
        $database = $this->API->GetDatabase();
        
        $groupid = $input->GetParam("group", SafeParam::TYPE_ID);
        $group = Group::TryLoadByID($database, $groupid);
        if ($group === null) throw new UnknownGroupException();        
        
        if ($group === $this->config->GetDefaultGroup()) 
            throw new MandatoryGroupException();
        
        foreach (Auth\Pointer::LoadAll($database) as $authp)
            if ($group === $authp->GetSource()->GetAccountGroup()) 
                throw new MandatoryGroupException();
            
        $group->Delete(); return $this->StandardReturn($input);        
    }
    
    protected function AddGroupMember(Input $input)
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $this->authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_ID);
        $groupid = $input->GetParam("group", SafeParam::TYPE_ID);
        
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
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_ID);
        $groupid = $input->GetParam("group", SafeParam::TYPE_ID);
        
        $account = Account::TryLoadByID($this->API->GetDatabase(), $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->API->GetDatabase(), $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($group === $this->config->GetDefaultGroup()) throw new MandatoryGroupException();
        if ($group === $account->GetAuthSource()->GetAccountGroup()) throw new MandatoryGroupException();
        
        if ($account->HasGroup($group)) $account->RemoveGroup($group);
        else throw new UnknownGroupMembershipException();
        
        return $this->StandardReturn($input);
    }

    public function Test(Input $input)
    {
        $config = $this->config;
        
        $old1 = $config->GetAllowCreateAccount(); $config->SetAllowCreateAccount(true);
        $old2 = $config->GetUseEmailAsUsername(); $config->SetUseEmailAsUsername(false);
        $old3 = $config->GetRequireContact(); $config->SetRequireContact(Config::CONTACT_EXIST);

        $results = array(); $app = "accounts";
        
        $email = Utilities::Random(8)."@unittest.com";
        $user = Utilities::Random(8); 
        $password = Utilities::Random(16);
        
        $test = $this->API->Run(new Input($app,'createaccount', (new SafeParams())
            ->AddParam('email',$email)
            ->AddParam('username',$user)
            ->AddParam('password',$password)));
        array_push($results, $test);
        
        $test = $this->API->Run(new Input($app,'createsession', (new SafeParams())
            ->AddParam('username',$user)
            ->AddParam('auth_password',$password)));
        array_push($results, $test);
        
        $sessionid = $test['session']['id'];
        $sessionkey = $test['session']['authkey'];
        
        $password2 = Utilities::Random(16);
        $test = $this->API->Run(new Input($app,'changepassword', (new SafeParams())
            ->AddParam('auth_sessionid',$sessionid)
            ->AddParam('auth_sessionkey',$sessionkey)
            ->AddParam('getaccount',true)
            ->AddParam('auth_password',$password)
            ->AddParam('new_password',$password2)));
        array_push($results, $test); 
        $password = $password2;
        
        $test = $this->API->Run(new Input($app,'deleteaccount', (new SafeParams())
            ->AddParam('auth_sessionid',$sessionid)
            ->AddParam('auth_sessionkey',$sessionkey)
            ->AddParam('auth_password',$password)));
        array_push($results, $test);
        
        $config->SetAllowCreateAccount($old1);
        $config->SetUseEmailAsUsername($old2);
        $config->SetRequireContact($old3);
        
        return $results;
    }
}

