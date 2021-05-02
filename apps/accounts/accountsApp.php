<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/apps/accounts/AccessLog.php");
require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Authenticator.php");
require_once(ROOT."/apps/accounts/AuthObject.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/Contact.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/GroupStuff.php");
require_once(ROOT."/apps/accounts/KeySource.php");
require_once(ROOT."/apps/accounts/RecoveryKey.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/TwoFactor.php");
require_once(ROOT."/apps/accounts/Whitelist.php");

require_once(ROOT."/apps/accounts/auth/Manager.php");
require_once(ROOT."/apps/accounts/auth/Local.php");
require_once(ROOT."/apps/accounts/auth/LDAP.php");
require_once(ROOT."/apps/accounts/auth/IMAP.php");
require_once(ROOT."/apps/accounts/auth/FTP.php");

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;
use Andromeda\Core\DecryptionFailedException;

use Andromeda\Core\Database\DatabaseException;

/** Exception indicating that an account already exists under this username/email */
class AccountExistsException extends Exceptions\ClientErrorException { public $message = "ACCOUNT_ALREADY_EXISTS"; }

/** Exception indicating that a group already exists with this name */
class GroupExistsException extends Exceptions\ClientErrorException { public $message = "GROUP_ALREADY_EXISTS"; }

/** Exception indicating that this contact already exists */
class ContactExistsException extends Exceptions\ClientErrorException { public $message = "CONTACT_ALREADY_EXISTS"; }

/** Exception indicating that this group membership is for a default group and cannot be changed */
class ImmutableGroupException extends Exceptions\ClientDeniedException { public $message = "GROUP_MEMBERSHIP_REQUIRED"; }

/** Exception indicating that creating accounts is not allowed */
class AccountCreateDeniedException extends Exceptions\ClientDeniedException { public $message = "ACCOUNT_CREATE_NOT_ALLOWED"; }

/** Exception indicating that the requested username is not whitelisted */
class AccountWhitelistException extends Exceptions\ClientDeniedException { public $message = "USERNAME_NOT_WHITELISTED"; }

/** Exception indicating that deleting accounts is not allowed */
class AccountDeleteDeniedException extends Exceptions\ClientDeniedException { public $message = "ACCOUNT_DELETE_NOT_ALLOWED"; }

/** Exception indicating that the user is already signed in */
class AlreadySignedInException extends Exceptions\ClientDeniedException { public $message = "ALREADY_SIGNED_IN"; }

/** Exception indicating that account/group search is not allowed */
class SearchDeniedException extends Exceptions\ClientDeniedException { public $message = "SEARCH_NOT_ALLOWED"; }

/** Exception indicating that server-side crypto is not allowed */
class CryptoNotAllowedException extends Exceptions\ClientDeniedException { public $message = "CRYPTO_NOT_ALLOWED"; }

/** Exception indicating that this group membership already exists */
class DuplicateGroupMembershipException extends Exceptions\ClientErrorException { public $message = "GROUP_MEMBERSHIP_EXISTS"; }

/** Exception indicating that the password for an account using external authentication cannot be changed */
class ChangeExternalPasswordException extends Exceptions\ClientErrorException { public $message = "CANNOT_CHANGE_EXTERNAL_PASSWORD"; }

/** Exception indicating that a recovery key cannot be generated */
class RecoveryKeyCreateException extends Exceptions\ClientErrorException { public $message = "CANNOT_GENERATE_RECOVERY_KEY"; }

/** Exception indicating that the old password must be provided */
class OldPasswordRequiredException extends Exceptions\ClientErrorException { public $message = "OLD_PASSWORD_REQUIRED"; }

/** Exception indicating that a new password must be provided */
class NewPasswordRequiredException extends Exceptions\ClientErrorException { public $message = "NEW_PASSWORD_REQUIRED"; }

/** Exception indicating that an email address must be provided */
class ContactRequiredException extends Exceptions\ClientDeniedException { public $message = "VALID_CONTACT_REQUIRED"; }

/** Exception indicating that the test on the authentication source failed */
class AuthSourceTestFailException extends Exceptions\ClientErrorException { public $message = "AUTH_SOURCE_TEST_FAIL"; }

/** Exception indicating that an unknown authentication source was given */
class UnknownAuthSourceException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_AUTHSOURCE"; }

/** Exception indicating that an unknown account was given */
class UnknownAccountException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_ACCOUNT"; }

/** Exception indicating that an unknown group was given */
class UnknownGroupException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_GROUP"; }

/** Exception indicating that an unknown client was given */
class UnknownClientException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_CLIENT"; }

/** Exception indicating that an unknown session was given */
class UnknownSessionException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_SESSION"; }

/** Exception indicating that an unknown twofactor was given */
class UnknownTwoFactorException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_TWOFACTOR"; }

/** Exception indicating that an unknown contact was given */
class UnknownContactException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_CONTACT"; }

/** Exception indicating that the group membership does not exist */
class UnknownGroupMembershipException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_GROUPMEMBERSHIP"; }

/**
 * App for managing accounts and authenticating users.
 *
 * Creates and manages accounts, groups of accounts, authentication,
 * managing and validating contacts.  Supports account-crypto, two-factor 
 * authentication, multi-client/session management, authentication via external
 * sources, and granular per-account/per-group config.
 */
class AccountsApp extends AppBase
{   
    private Config $config;
    
    private ObjectDatabase $database;
    
    public static function getVersion() : string { return "2.0.0-alpha"; } 
    
    public static function getLogClass() : ?string { return AccessLog::class; }
    
    public static function getUsage() : array 
    { 
        return array(
            'install',
            '- GENERAL AUTH: [--auth_sessionid id --auth_sessionkey alphanum] [--auth_sudouser id]',
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'getaccount [--account id] [--full bool]',
            'setfullname --fullname name',
            'enablecrypto --auth_password raw [--auth_twofactor int]',
            'disablecrypto --auth_password raw',
            'changepassword --new_password raw ((--username text --auth_password raw) | --recoverykey text)',
            'emailrecovery (--username text | '.Contact::GetFetchUsage().')',
            'createaccount (--username alphanum | '.Contact::GetFetchUsage().') --password raw [--admin bool]',
            'createsession (--username text | '.Contact::GetFetchUsage().') --auth_password raw [--authsource ?id] [--old_password raw] [--new_password raw]',
                "\t [--recoverykey text | --auth_twofactor int] [--name name]",
                "\t --auth_clientid id --auth_clientkey alphanum",
            'createrecoverykeys --auth_password raw --auth_twofactor int [--replace bool]',
            'createtwofactor --auth_password raw [--comment text]',
            'verifytwofactor --auth_twofactor int',
            'createcontact '.Contact::GetFetchUsage(),
            'verifycontact --authkey text',
            'deleteaccount --auth_password raw --auth_twofactor int',
            'deletesession [--session id --auth_password raw]',
            'deleteclient [--client id --auth_password raw]',
            'deleteallauth --auth_password raw [--everyone bool]',
            'deletetwofactor --auth_password raw --twofactor id',
            'deletecontact --contact id',
            'editcontact --contact id [--usefrom bool] [--public bool]',
            'searchaccounts --name text',
            'searchgroups --name text',
            'listaccounts [--limit int] [--offset int]',
            'listgroups [--limit int] [--offset int]',            
            'creategroup --name name [--priority int] [--comment text]',
            'editgroup --group id [--name name] [--priority int] [--comment ?text]',
            'getgroup --group id',
            'deletegroup --group id',
            'addgroupmember --account id --group id',
            'removegroupmember --account id --group id',
            'getmembership --account id --group id',
            'getauthsources',
            'createauthsource --auth_password raw '.Auth\Manager::GetPropUsage().' [--test_username text --test_password raw]',
            ...Auth\Manager::GetPropUsages(),
            'testauthsource --manager id [--test_username text --test_password raw]',
            'editauthsource --manager id --auth_password raw '.Auth\Manager::GetPropUsage().' [--test_username text --test_password raw]',
            'deleteauthsource --manager id --auth_password raw',
            'setaccountprops --account id '.AuthEntity::GetPropUsage().' [--expirepw bool]',
            'setgroupprops --group id '.AuthEntity::GetPropUsage(),
            'sendmessage (--account id | --group id) --subject text --text text [--html raw]',
            'addwhitelist --type '.implode('|',array_keys(Whitelist::TYPES)).' --value text',
            'removewhitelist --type '.implode('|',array_keys(Whitelist::TYPES)).' --value text',
            'getwhitelist'
        );
    }
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        $this->database = $api->GetDatabase();
        
        try { $this->config = Config::GetInstance($this->database); }
        catch (DatabaseException $e) { }
        
        new Auth\Local(); // construct the singleton
    }

    /**
     * {@inheritDoc}
     * @throws UnknownConfigException if config needs to be initialized
     * @throws UnknownActionException if the given action is not valid
     * @see AppBase::Run()
     */
    public function Run(Input $input)
    {
        // if config is not available, require installing it
        if (!isset($this->config) && $input->GetAction() !== 'install')
            throw new UnknownConfigException(static::class);
        
        $authenticator = Authenticator::TryAuthenticate(
            $this->database, $input, $this->API->GetInterface());
        
        $accesslog = AccessLog::Create($this->database, $authenticator); $input->SetLogger($accesslog);
        
        switch($input->GetAction())
        {
            case 'install':             return $this->Install($input); 
            
            case 'getconfig':           return $this->GetConfig($input, $authenticator);
            case 'setconfig':           return $this->SetConfig($input, $authenticator);
            
            case 'getauthsources':      return $this->GetAuthSources($input, $authenticator);
            case 'createauthsource':    return $this->CreateAuthSource($input, $authenticator, $accesslog);
            case 'testauthsource':      return $this->TestAuthSource($input, $authenticator);
            case 'editauthsource':      return $this->EditAuthSource($input, $authenticator);
            case 'deleteauthsource':    return $this->DeleteAuthSource($input, $authenticator, $accesslog);
            
            case 'getaccount':          return $this->GetAccount($input, $authenticator);
            case 'setfullname':         return $this->SetFullName($input, $authenticator);
            case 'changepassword':      return $this->ChangePassword($input, $authenticator);
            
            case 'emailrecovery':       return $this->EmailRecovery($input);
            
            case 'createaccount':       return $this->CreateAccount($input, $authenticator, $accesslog);
            case 'createsession':       return $this->CreateSession($input, $authenticator, $accesslog);
            case 'enablecrypto':        return $this->EnableCrypto($input, $authenticator);
            case 'disablecrypto':       return $this->DisableCrypto($input, $authenticator);
            
            case 'createrecoverykeys':  return $this->CreateRecoveryKeys($input, $authenticator);
            case 'createtwofactor':     return $this->CreateTwoFactor($input, $authenticator, $accesslog);
            case 'verifytwofactor':     return $this->VerifyTwoFactor($input, $authenticator);
            case 'createcontact':       return $this->CreateContact($input, $authenticator, $accesslog);
            case 'verifycontact':       return $this->VerifyContact($input);
            
            case 'deleteaccount':       return $this->DeleteAccount($input, $authenticator, $accesslog);
            case 'deletesession':       return $this->DeleteSession($input, $authenticator, $accesslog);
            case 'deleteclient':        return $this->DeleteClient($input, $authenticator, $accesslog);
            case 'deleteallauth':       return $this->DeleteAllAuth($input, $authenticator);
            case 'deletetwofactor':     return $this->DeleteTwoFactor($input, $authenticator, $accesslog);
            
            case 'deletecontact':       return $this->DeleteContact($input, $authenticator, $accesslog); 
            case 'editcontact':         return $this->EditContact($input, $authenticator);
            
            case 'searchaccounts':      return $this->SearchAccounts($input, $authenticator);
            case 'searchgroups':        return $this->SearchGroups($input, $authenticator);
            case 'listaccounts':        return $this->ListAccounts($input, $authenticator);
            case 'listgroups':          return $this->ListGroups($input, $authenticator);
            case 'creategroup':         return $this->CreateGroup($input, $authenticator, $accesslog);
            case 'editgroup':           return $this->EditGroup($input, $authenticator); 
            case 'getgroup':            return $this->GetGroup($input, $authenticator);
            case 'deletegroup':         return $this->DeleteGroup($input, $authenticator, $accesslog);
            case 'addgroupmember':      return $this->AddGroupMember($input, $authenticator);
            case 'removegroupmember':   return $this->RemoveGroupmember($input, $authenticator);
            case 'getmembership':       return $this->GetMembership($input, $authenticator);
            
            case 'setaccountprops':     return $this->SetAccountProps($input, $authenticator);
            case 'setgroupprops':       return $this->SetGroupProps($input, $authenticator);
            
            case 'sendmessage':         return $this->SendMessage($input, $authenticator);
            
            case 'addwhitelist':        return $this->AddWhitelist($input, $authenticator);
            case 'removewhitelist':     return $this->RemoveWhitelist($input, $authenticator);
            case 'getwhitelist':        return $this->GetWhitelist($input, $authenticator);
            
            default: throw new UnknownActionException();
        }
    }

    /**
     * Installs the app by importing its SQL file, creating config, and creating an admin account
     * @throws UnknownActionException if config already exists
     */
    public function Install(Input $input) : void
    {
        if (isset($this->config)) throw new UnknownActionException();
        
        $this->database->importTemplate(ROOT."/apps/accounts");
        
        Config::Create($this->database)->Save();
    }
    
    /**
     * Gets config for this app
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function GetConfig(Input $input, ?Authenticator $authenticator) : array
    {
        $account = $authenticator ? $authenticator->GetAccount() : null;
        
        $admin = $account !== null && $account->isAdmin();

        return $this->config->GetClientObject($admin);
    }
    
    /**
     * Sets config for this app
     * @throws AuthenticationFailedException if not admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();        
        $authenticator->RequireAdmin();
        
        return $this->config->SetConfig($input)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured authentication sources
     * @return array [id:Auth\Manager]
     * @see Auth\Manager::GetClientObject()
     */
    protected function GetAuthSources(Input $input, ?Authenticator $authenticator) : array
    {
        $admin = $authenticator !== null && $authenticator->isAdmin();
        
        return array_map(function(Auth\Manager $m)use($admin){ return $m->GetClientObject($admin); },
            Auth\Manager::LoadAll($this->database));
    }

    /**
     * Gets the current account object, or the specified one
     * @throws UnknownAccountException if the specified account is not valid
     * @return array Account
     * @see Account::GetClientObject()
     */
    protected function GetAccount(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) return null;
        
        $account = $input->GetOptParam("account", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $self = ($account === null);
        
        if ($account !== null)
        {
            $account = Account::TryLoadByID($this->database, $account);
            if ($account === null) throw new UnknownAccountException();
        }
        else $account = $authenticator->GetAccount();
        
        $admin = $authenticator->isAdmin();
        
        $full = $input->GetOptParam("full", SafeParam::TYPE_BOOL) && ($self || $admin);

        $type = ($full ? Account::OBJECT_FULL : 0) | ($admin ? Account::OBJECT_ADMIN : 0);
        
        return $account->GetClientObject($type);
    }

    /**
     * Changes the password for an account
     * 
     * If currently logged in, this changes the password for the user's account (requiring the old one)
     * If not logged in, this allows account recovery by resetting the password via a recovery key.
     * @throws AuthenticationFailedException if the given account or recovery key are invalid
     * @throws ChangeExternalPasswordException if the user's account uses an non-local auth source
     */
    protected function ChangePassword(Input $input, ?Authenticator $authenticator) : void
    {
        $new_password = $input->GetParam('new_password', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        $recoverykey = $input->GetOptParam('recoverykey', SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_NEVER);
        
        if ($recoverykey !== null)
        {
            $username = $input->GetParam("username", SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_ALWAYS);
            $account = Account::TryLoadByUsername($this->database, $username);
            if ($account === null) throw new AuthenticationFailedException();
        }
        else
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();   
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
            if (!$authenticator->isSudoUser()) 
                $authenticator->RequirePassword();
        }
        
        Authenticator::StaticTryRequireCrypto($input, $account);
        $account->ChangePassword($new_password);
    }
    
    /** Returns the given string with each character after a space capitalized */
    private static function capitalizeWords(string $str) : string 
    { 
        return implode(" ",array_map(function($p){ 
            return mb_strtoupper(mb_substr($p,0,1)).mb_substr($p,1); 
        }, explode(" ", trim($str)))); 
    }
    
    /**
     * Sets the user's full (real) name
     * @throws AuthenticationFailedException if not logged in
     */
    protected function SetFullName(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $fullname = static::capitalizeWords($input->GetParam("fullname", SafeParam::TYPE_NAME));
        
        $authenticator->GetAccount()->SetFullName($fullname);
    }
    
    /**
     * Emails a recovery key to the user's registered contacts
     * @throws UnknownAccountException if the given username is invalid
     * @throws RecoveryKeyCreateException if crypto or two factor are enabled
     */
    protected function EmailRecovery(Input $input) : void
    {
        if ($input->HasParam('username'))
        {
            $username = $input->GetParam("username", SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_ALWAYS);
            $account = Account::TryLoadByUsername($this->database, $username);
        }
        else
        {
            $contactInfo = Contact::FetchInfoFromInput($input);
            $account = Account::TryLoadByContactInfo($this->database, $contactInfo);
        }        
        
        if ($account === null) throw new UnknownAccountException();
        
        if ($account->hasCrypto() || $account->HasValidTwoFactor()) 
            throw new RecoveryKeyCreateException();

        $key = RecoveryKey::Create($this->database, $account)->GetFullKey();   
        
        $subject = "Andromeda Account Recovery Key";
        $body = "Your recovery key is: $key";       
        
        // TODO CLIENT - HTML - configure a directory where client templates reside
        
        $account->SendMessage($subject, null, $body);
    }
    
    /**
     * Enables server-side crypto for an account and returns new recovery keys
     * 
     * Deletes any existing recovery keys, requiring two factor if they exist
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:RecoveryKey] if crypto was not enabled
     * @see RecoveryKey::GetClientObject()
     */
    protected function EnableCrypto(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        if ($account->hasCrypto()) return null;
        
        if (!$account->GetAllowCrypto()) throw new CryptoNotAllowedException();
        
        $authenticator->RequirePassword();
        
        $password = $input->GetParam('auth_password', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);

        if ($account->HasRecoveryKeys())
        {
            $authenticator->TryRequireTwoFactor();
            
            RecoveryKey::DeleteByAccount($this->database, $account);
        }
        
        $account->InitializeCrypto($password);
        
        $session = $authenticator->GetSession(); $session->InitializeCrypto();
        
        Session::DeleteByAccountExcept($this->database, $account, $session);
        
        return array_map(function(RecoveryKey $key){ return $key->GetClientObject(true); },
            RecoveryKey::CreateSet($this->database, $account));
    }
    
    /**
     * Disables server side crypto for an account
     * @throws AuthenticationFailedException if not signed in
     */
    protected function DisableCrypto(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        if (!$account->hasCrypto()) return;

        $authenticator->RequirePassword()->RequireCrypto();
        
        $account->DestroyCrypto();
    }
    
    /**
     * Creates a new user account
     * @throws AccountCreateDeniedException if the feature is disabled
     * @throws AccountExistsException if the account already exists
     * @return array Account
     * @see Account::GetClientObject()
     */
    protected function CreateAccount(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $admin = $authenticator !== null; 
        if ($admin) $authenticator->RequireAdmin();
        $admin = $admin || $this->API->GetInterface()->isPrivileged();
        
        $allowCreate = $this->config->GetAllowCreateAccount();
        
        if (!$admin && !$allowCreate) throw new AccountCreateDeniedException();
        
        $userIsContact = $this->config->GetUsernameIsContact();
        $requireContact = $this->config->GetRequireContact();
               
        if ($userIsContact || $requireContact >= Config::CONTACT_EXIST)
        {
            $contactInfo = Contact::FetchInfoFromInput($input);           
        }

        $username = $userIsContact ? $contactInfo->info : $input->GetParam("username", 
            SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ALWAYS, SafeParam::MaxLength(127));
        
        if (!$admin && $allowCreate == Config::CREATE_WHITELIST)
        {
            $ok = Whitelist::ExistsTypeAndValue($this->database, Whitelist::TYPE_USERNAME, $username);
            
            if (isset($contactInfo)) $ok |= Whitelist::ExistsTypeAndValue($this->database, Whitelist::TYPE_CONTACT, $contactInfo->info);
            
            if (!$ok) throw new AccountWhitelistException();
        }

        $password = $input->GetParam("password", SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        if (Account::TryLoadByUsername($this->database, $username) !== null) throw new AccountExistsException();

        $account = Account::Create($this->database, Auth\Local::GetInstance(), $username, $password);
       
        if (isset($contactInfo)) 
        {
            if (Contact::TryLoadByInfoPair($this->database, $contactInfo) !== null)
                throw new ContactExistsException();
            
            $valid = $requireContact >= Config::CONTACT_VALID;
            
            if ($valid) $account->setDisabled(Account::DISABLE_PENDING_CONTACT);
            
            Contact::Create($this->database, $account, $contactInfo, $valid);
        }
        
        if ($admin && $input->GetOptParam('admin',SafeParam::TYPE_BOOL)) $account->setAdmin(true);
        
        if ($accesslog) $accesslog->LogDetails('account',$account->ID()); 

        return $account->GetClientObject(Account::OBJECT_FULL);
    }

    /**
     * Creates a new session, and possibly a new client for the account
     * 
     * The authentication source for the account must be provided if not local.
     * First locates the account, then checks the password.  Possibly creates
     * a new account if it exists on the external auth source. Then checks the
     * client object in use, creating one and checking extra auth if not provided.
     * Account crypto is checked, password age is checked, dates are updated.
     * Then finally, the session is created and the client is returned.
     * @throws Exceptions\ClientDeniedException if already logged in
     * @throws UnknownAuthSourceException if the given auth source is invalid
     * @throws AuthenticationFailedException if the given username/password are wrong
     * @throws AccountDisabledException if the account is not enabled
     * @throws UnknownClientException if the given client is invalid
     * @throws OldPasswordRequiredException if the old password is required to unlock crypto
     * @throws NewPasswordRequiredException if a new password is required to be set
     * @return array `{client:Client, account:Account}`
     * @see Client::GetClientObject()
     * @see Account::GetClientObject()
     */
    protected function CreateSession(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator !== null) throw new AlreadySignedInException();

        /* load the authentication source being used - could be local, or an LDAP server, etc. */
        if ($input->HasParam('authsource'))
        {
            if (($authsource = $input->GetNullParam('authsource',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS)) !== null)
            {
                $authsource = Auth\Manager::TryLoadByID($this->database, $authsource);
                if ($authsource === null) throw new UnknownAuthSourceException();
            }
        }
        else $authsource = $this->config->GetDefaultAuth();
        
        $authsource ??= Auth\Local::GetInstance();

        if ($input->HasParam('username'))
        {
            $username = $input->GetParam("username", SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_ALWAYS);
            $account = Account::TryLoadByUsername($this->database, $username);
        }
        else 
        {
            $cinfo = Contact::FetchInfoFromInput($input);
            $account = Account::TryLoadByContactInfo($this->database, $cinfo);
            
            if ($account === null) throw new AuthenticationFailedException();
        }
        
        $password = $input->GetParam("auth_password", SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);        
        
        /* if we found an account, verify the password and correct authsource */
        if ($account !== null)
        {            
            if (($account->GetAuthSource() === null && !($authsource instanceof Auth\Local)) ||
                ($account->GetAuthSource() !== null && $account->GetAuthSource() !== $authsource)) 
                    throw new AuthenticationFailedException();
            
            if (!$account->VerifyPassword($password)) throw new AuthenticationFailedException();
        }
        /* if no account and using external auth, try the password, and if success, create a new account on the fly */
        else if ($authsource instanceof Auth\External)
        {            
            if (!$authsource->VerifyUsernamePassword($username, $password))
                throw new AuthenticationFailedException();
            
            $account = Account::Create($this->database, $authsource, $username);    
        }
        else throw new AuthenticationFailedException();
        
        if (!$account->isEnabled()) throw new AccountDisabledException();
        
        if ($accesslog) $accesslog->LogDetails('account',$account->ID()); 
        
        $clientid = $input->GetOptParam("auth_clientid", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        $clientkey = $input->GetOptParam("auth_clientkey", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER);
        
        $interface = $this->API->GetInterface();
        
        /* if a clientid is provided, check that it and the clientkey are correct */
        if ($clientid !== null && $clientkey !== null)
        {
            if ($account->GetForceUseTwoFactor() && $account->HasValidTwoFactor()) 
                Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $client = Client::TryLoadByID($this->database, $clientid);
            if ($client === null || !$client->CheckMatch($interface, $clientkey)) 
                throw new UnknownClientException();
        } 
        else /* if no clientkey, require either a recoverykey or twofactor, create a client */
        { 
            if (($recoverykey = $input->GetOptParam('recoverykey', SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_NEVER)) !== null)
            {
                if (!$account->CheckRecoveryKey($recoverykey))
                    throw new AuthenticationFailedException();
            }
            else Authenticator::StaticTryRequireTwoFactor($input, $account);
            
            $cname = $input->GetOptParam('name', SafeParam::TYPE_NAME);
            $client = Client::Create($interface, $this->database, $account, $cname);
        }
        
        if ($accesslog) $accesslog->LogDetails('client',$client->ID()); 
        
        /* unlock account crypto - failure means the password source must've changed without updating crypto */
        if ($account->hasCrypto())
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e)
            {
                $old_password = $input->GetOptParam("old_password", SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
                if ($old_password === null) throw new OldPasswordRequiredException();
                $account->UnlockCryptoFromPassword($old_password);
                
                $account->ChangePassword($password);
            }
        }
        
        /* check account password age, possibly require a new one */
        if (!$account->CheckPasswordAge())
        {
            $new_password = $input->GetOptParam('new_password',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
            if ($new_password === null) throw new NewPasswordRequiredException();
            $account->ChangePassword($new_password);
        }
        
        /* delete old session associated with this client, create a new one */
        $session = Session::Create($this->database, $account, $client->DeleteSession());
        
        if ($accesslog) $accesslog->LogDetails('session',$session->ID()); 
        
        /* update object dates */
        $client->setLoggedonDate()->setActiveDate();
        $account->setLoggedonDate()->setActiveDate();
        
        return array('client'=>$client->GetClientObject(true), 
                     'account'=>$account->GetClientObject());
    }
    
    /**
     * Creates a set of recovery keys, optionally replacing existing
     * @throws AuthenticationFailedException if not logged in
     * @return array `[id:RecoveryKey]`
     * @see RecoveryKey::GetClientObject()
     */
    protected function CreateRecoveryKeys(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $authenticator->RequirePassword()->TryRequireTwoFactor()->TryRequireCrypto();        
        
        if ($input->GetOptParam('replace',SafeParam::TYPE_BOOL))
            RecoveryKey::DeleteByAccount($this->database, $account);
        
        $keys = RecoveryKey::CreateSet($this->database, $account);
        
        return array_map(function(RecoveryKey $key){
            return $key->GetClientObject(true); }, $keys);
    }
    
    /**
     * Creates a two factor authentication source, and recovery keys
     * 
     * Also activates crypto for the account, if allowed and not active.
     * Doing so will delete all other sessions for the account.
     * @throws AuthenticationFailedException if not signed in
     * @return array `{twofactor:TwoFactor,recoverykeys:[id:RecoveryKey]}` \
     *  - recovery keys are returned only if they don't already exist
     * @see TwoFactor::GetClientObject()
     * @see RecoveryKey::GetClientObject()
     */
    protected function CreateTwoFactor(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $authenticator->RequirePassword()->TryRequireCrypto();
        
        $comment = $input->GetOptParam('comment', SafeParam::TYPE_TEXT);
        
        $twofactor = TwoFactor::Create($this->database, $account, $comment);
        
        if ($accesslog) $accesslog->LogDetails('twofactor',$twofactor->ID()); 

        $retval = array('twofactor'=>$twofactor->GetClientObject(true));
        
        if (!$account->HasRecoveryKeys())
        {
            $keys = RecoveryKey::CreateSet($this->database, $account);
            
            $retval['recoverykeys'] = array_map(function(RecoveryKey $key){ 
                return $key->GetClientObject(true); }, $keys);
        }
        
        return $retval;
    }
    
    /**
     * Verifies a two factor source
     * @throws AuthenticationFailedException if not signed in
     */
    protected function VerifyTwoFactor(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->TryRequireCrypto();
        
        $account = $authenticator->GetAccount();
        
        $code = $input->GetParam("auth_twofactor", SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_NEVER); // not an int (leading zeroes)
        
        if (!$account->CheckTwoFactor($code, true)) 
            throw new AuthenticationFailedException();
    }
    
    /**
     * Adds a contact to the account
     * @throws AuthenticationFailedException if not signed in
     * @throws ContactExistsException if the contact info is used
     * @return array Contact
     * @see Contact::GetClientObject()
     */
    protected function CreateContact(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $verify = $this->config->GetRequireContact() >= Config::CONTACT_VALID;
        
        $info = Contact::FetchInfoFromInput($input);
        
        if (Contact::TryLoadByInfoPair($this->database, $info) !== null) throw new ContactExistsException();

        $contact = Contact::Create($this->database, $account, $info, $verify);
        
        if ($accesslog) $accesslog->LogDetails('contact',$contact->ID()); 
        
        return $contact->GetClientObject();
    }
    
    /**
     * Verifies an account contact
     * @throws AuthenticationFailedException if the given key is invalid
     * @throws UnknownContactException if the contact does not exist
     */
    protected function VerifyContact(Input $input) : void
    {
        $authkey = $input->GetParam('authkey',SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_NEVER);
        
        $contact = Contact::TryLoadByFullKey($this->database, $authkey);
        if ($contact === null) throw new UnknownContactException();
        
        if (!$contact->CheckFullKey($authkey)) throw new AuthenticationFailedException();
    }
    
    /**
     * Deletes the current account (and signs out)
     * @throws AuthenticationFailedException if not signed in
     * @throws AccountDeleteDeniedException if delete is not allowed
     */
    protected function DeleteAccount(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();        
        $account = $authenticator->GetAccount();
        
        if (!$account->GetAllowUserDelete()) throw new AccountDeleteDeniedException();
        // TODO will not work for admin deleting accounts
        
        $authenticator->RequirePassword();
        
        if (!$authenticator->isSudoUser()) 
            $authenticator->TryRequireTwoFactor();
        
        if ($accesslog && AccessLog::isFullDetails()) $accesslog->LogDetails('account',
            $account->GetClientObject(Account::OBJECT_ADMIN | Account::OBJECT_FULL));
        
        $account->Delete();
    }
    
    /**
     * Deletes an account session (signing it out)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownSessionException if an invalid session was provided
     */
    protected function DeleteSession(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        $session = $authenticator->GetSession();
        
        $sessionid = $input->GetOptParam("session", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);

        if ($authenticator->isSudoUser() || $sessionid !== null)
        {
            if (!$authenticator->isSudoUser()) $authenticator->RequirePassword();
            $session = Session::TryLoadByAccountAndID($this->database, $account, $sessionid);
            if ($session === null) throw new UnknownSessionException();
        }
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('session', $session->GetClientObject());
        
        $session->Delete();
    }
    
    /**
     * Deletes an account session and client (signing out fully)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownClientException if an invalid client was provided
     */
    protected function DeleteClient(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        $client = $authenticator->GetClient();
        
        $clientid = $input->GetOptParam("client", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        if ($authenticator->isSudoUser() || $clientid !== null)
        {
            if (!$authenticator->isSudoUser()) $authenticator->RequirePassword();
            $client = Client::TryLoadByAccountAndID($this->database, $account, $clientid);
            if ($client === null) throw new UnknownClientException();
        }
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('client', $client->GetClientObject());
        
        $client->Delete();
    }
    
    /**
     * Deletes all registered clients/sessions for an account
     * @throws AuthenticationFailedException if not signed in
     */
    protected function DeleteAllAuth(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequirePassword();
        
        if ($input->GetOptParam('everyone',SafeParam::TYPE_BOOL) ?? false)
        {
            $authenticator->RequireAdmin()->TryRequireTwoFactor();
            Client::DeleteAll($this->database);
        }
        else $authenticator->GetAccount()->DeleteClients();
    }
    
    /**
     * Deletes a two factor source for an account
     * 
     * If this leaves the account without two factor, crypto is disabled
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownTwoFactorException if the given twofactor is invalid
     */
    protected function DeleteTwoFactor(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequirePassword();
        $account = $authenticator->GetAccount();
        
        $twofactorid = $input->GetParam("twofactor", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $twofactor = TwoFactor::TryLoadByAccountAndID($this->database, $account, $twofactorid); 
        if ($twofactor === null) throw new UnknownTwoFactorException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('twofactor', $twofactor->GetClientObject());

        $twofactor->Delete();
    }    
    
    /**
     * Deletes a contact from an account
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownContactException if the contact is invalid
     * @throws ContactRequiredException if a valid contact is required
     */
    protected function DeleteContact(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $cid = $input->GetParam('contact',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $contact = Contact::TryLoadByAccountAndID($this->database, $account, $cid);
        if ($contact === null) throw new UnknownContactException();

        if ($this->config->GetRequireContact() && $contact->GetIsValid() && count($account->GetContacts()) <= 1)
            throw new ContactRequiredException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('contact', $contact->GetClientObject());
    
        $contact->Delete();
    }
    
    /**
     * Edits a contact for an account
     * @throws AuthenticationFailedException
     * @throws UnknownContactException
     */
    protected function EditContact(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $cid = $input->GetParam('contact',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $contact = Contact::TryLoadByAccountAndID($this->database, $account, $cid);
        if ($contact === null) throw new UnknownContactException();
        
        if ($input->HasParam('usefrom')) $contact->setUseFrom($input->GetParam('usefrom',SafeParam::TYPE_BOOL));        
        if ($input->HasParam('public')) $contact->setIsPublic($input->GetParam('public',SafeParam::TYPE_BOOL));
        
        return $contact->GetClientObject();
    }
    
    /**
     * Searches for accounts identified with the given name prefix
     * @throws AuthenticationFailedException if not signed in
     * @throws SearchDeniedException if the feature is disabled
     * @return array Account
     * @see Account::LoadAllMatchingInfo()
     * @see Account::GetClientObject()
     */
    protected function SearchAccounts(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        if (!($limit = $authenticator->GetAccount()->GetAllowAccountSearch())) throw new SearchDeniedException();
        
        $name = $input->GetParam('name', SafeParam::TYPE_TEXT);
        
        if (strlen($name) < 3) return array();

        return array_map(function(Account $account){ return $account->GetClientObject(); },
            Account::LoadAllMatchingInfo($this->database, $name, $limit));
    }
    
    /**
     * Searches for groups identified with the given name prefix
     * @throws AuthenticationFailedException if not signed in
     * @throws SearchDeniedException if the feature is disabled
     * @return array Group
     * @see Group::LoadAllMatchingName()
     * @see Group::GetClientObject()
     */
    protected function SearchGroups(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        if (!($limit = $authenticator->GetAccount()->GetAllowGroupSearch())) throw new SearchDeniedException();
        
        $name = $input->GetParam('name', SafeParam::TYPE_TEXT);
        
        if (strlen($name) < 3) return array();
        
        return array_map(function(Group $group){ return $group->GetClientObject(); },
            Group::LoadAllMatchingName($this->database, $name, $limit));
    }
    
    /**
     * Returns a list of all registered accounts
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Account]
     * @see Account::GetClientObject()
     */
    protected function ListAccounts(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $limit = $input->GetOptNullParam("limit", SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam("offset", SafeParam::TYPE_UINT);
        
        $full = $input->GetOptParam("full", SafeParam::TYPE_BOOL) ?? false;
        $type = $full ? Account::OBJECT_ADMIN : 0;
        
        $accounts = Account::LoadAll($this->database, $limit, $offset);        
        return array_map(function(Account $account)use($type){ return $account->GetClientObject($type); }, $accounts);
    }
    
    /**
     * Returns a list of all registered groups
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Group]
     * @see Group::GetClientObject()
     */
    protected function ListGroups(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $limit = $input->GetOptNullParam("limit", SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam("offset", SafeParam::TYPE_UINT);
        
        $groups = Group::LoadAll($this->database, $limit, $offset);
        return array_map(function(Group $group){ return $group->GetClientObject(Group::OBJECT_ADMIN); }, $groups);
    }
    
    /**
     * Creates a new account group
     * @throws AuthenticationFailedException if not admin
     * @throws GroupExistsException if the group name exists already
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function CreateGroup(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $name = $input->GetParam("name", SafeParam::TYPE_NAME, SafeParams::PARAMLOG_ONLYFULL, SafeParam::MaxLength(127));
        
        $priority = $input->GetOptParam("priority", SafeParam::TYPE_INT);
        $comment = $input->GetOptParam("comment", SafeParam::TYPE_TEXT);
        
        $duplicate = Group::TryLoadByName($this->database, $name);
        if ($duplicate !== null) throw new GroupExistsException();

        $group = Group::Create($this->database, $name, $priority, $comment);
        
        if ($accesslog) $accesslog->LogDetails('group',$group->ID()); 
        
        return $group->Initialize()->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }    
    
    /**
     * Edits properties of an existing group
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is not found
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function EditGroup(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($input->HasParam('name')) 
        {
            $name = $input->GetParam("name", SafeParam::TYPE_NAME, SafeParams::PARAMLOG_ONLYFULL, SafeParam::MaxLength(127));
            
            $duplicate = Group::TryLoadByName($this->database, $name);
            if ($duplicate !== null) throw new GroupExistsException();
            
            $group->SetName($name);
        }
 
        if ($input->HasParam('priority')) $group->SetPriority($input->GetParam("priority", SafeParam::TYPE_INT));
        if ($input->HasParam('comment')) $group->SetComment($input->GetNullParam("comment", SafeParam::TYPE_TEXT));
        
        return $group->GetClientObject(Group::OBJECT_ADMIN);
    }
    
    /**
     * Returns the requested group object
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is invalid
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function GetGroup(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        return $group->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }

    /**
     * Deletes an account group
     * @throws AuthenticationFailedException if not admin 
     * @throws UnknownGroupException if the group does not exist
     */
    protected function DeleteGroup(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($accesslog && AccessLog::isFullDetails()) $accesslog->LogDetails('group',
            $group->GetClientObject(Group::OBJECT_ADMIN | Group::OBJECT_FULL));
            
        $group->Delete();
    }
    
    /**
     * Adds an account to a group
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAccountException if the account is not found
     * @throws UnknownGroupException if the group is not found
     * @throws DuplicateGroupMembershipException if the membership already exists
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function AddGroupMember(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $account = Account::TryLoadByID($this->database, $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();

        if (!$account->HasGroup($group)) $account->AddGroup($group);
        else throw new DuplicateGroupMembershipException();

        return $group->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }
    
    /**
     * Removes an account from a group
     * @throws AuthenticationFailedException if not admin 
     * @throws UnknownAccountException if the account is not found
     * @throws UnknownGroupException if the group is not found
     * @throws ImmutableGroupException if the group is a default group
     * @throws UnknownGroupMembershipException if the group membership does not exist
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function RemoveGroupMember(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $account = Account::TryLoadByID($this->database, $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if (in_array($group, $account->GetDefaultGroups(), true))
            throw new ImmutableGroupException();
        
        if ($account->HasGroup($group)) $account->RemoveGroup($group);
        else throw new UnknownGroupMembershipException();
        
        return $group->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }
    
    /**
     * Gets metadata for an account group membership
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAccountException if given an invalid account
     * @throws UnknownGroupException if given an invalid group
     * @throws UnknownGroupMembershipException if the account is not in the group
     * @return array GroupJoin
     * @see GroupJoin::GetClientObject()
     */
    protected function GetMembership(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $input->GetParam("account", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $account = Account::TryLoadByID($this->database, $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        $joinobj = $account->GetGroupJoin($group);
        if ($joinobj === null) throw new UnknownGroupMembershipException();
        
        return $joinobj->GetClientObject();
    }
    
    /**
     * Adds a new external authentication source, optionally testing it
     * 
     * This authorizes automatically creating an account for anyone
     * that successfully authenticates against the auth source
     * @throws AuthenticationFailedException if not admin
     * @return array Auth\Manager
     * @see Auth\Manager::GetClientObject()
     */
    protected function CreateAuthSource(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword();

        $manager = Auth\Manager::Create($this->database, $input);
        
        if ($input->HasParam('test_username'))
        {
            $this->TestAuthSource($input->AddParam('manager',$manager->ID()));
        }
        
        if ($accesslog) $accesslog->LogDetails('manager',$manager->ID()); 
        
        return $manager->GetClientObject(true);
    }
    
    /**
     * Tests an auth source by running an auth query on it
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAuthSourceException if the auth source is not found
     * @throws AuthSourceTestFailException if the test fails
     * @return array Auth\Manager
     * @see Auth\Manager::GetClientObject()
     */
    protected function TestAuthSource(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $manager = $input->GetParam('manager', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();        
        
        $testuser = $input->GetParam('test_username',SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_NEVER);
        $testpass = $input->GetParam('test_password',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        if (!$manager->GetAuthSource()->VerifyUsernamePassword($testuser, $testpass))
            throw new AuthSourceTestFailException();        
           
        return $manager->GetClientObject(true);
    }
    
    /**
     * Edits the properties of an existing auth source, optionally testing it
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAuthSourceException if the auth source is not found
     * @return array Auth\Manager
     * @see Auth\Manager::GetClientObject()
     */
    protected function EditAuthSource(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword();
        
        $manager = $input->GetParam('manager', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();
        
        if ($input->HasParam('test_username')) $this->TestAuthSource($input);
        
        return $manager->Edit($input)->GetClientObject(true);
    }
    
    /**
     * Removes an external auth source, deleting accounts associated with it!
     * @throws AuthenticationFailedException if not an admin
     * @throws UnknownAuthSourceException if the auth source does not exist
     */
    protected function DeleteAuthSource(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword()->TryRequireTwoFactor();
        
        $manager = $input->GetParam('manager', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('manager', $manager->GetClientObject(true));
        
        $manager->Delete();
    }
    
    /**
     * Sets config on an account
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAccountException if the account is not found
     * @return array Account
     * @see Account::GetClientObject()
     */
    protected function SetAccountProps(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $acctid = $input->GetParam("account", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $account = Account::TryLoadByID($this->database, $acctid);
        if ($account === null) throw new UnknownAccountException();
        
        if ($input->GetOptParam("expirepw", SafeParam::TYPE_BOOL) ?? false) $account->resetPasswordDate();
        
        return $account->SetProperties($input)->GetClientObject(Account::OBJECT_ADMIN);
    }
    
    /**
     * Sets config on a group
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is not found
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function SetGroupProps(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $input->GetParam("group", SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();

        return $group->SetProperties($input)->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }
    
    /**
     * Sends a message to the given account or group's contacts
     * @throws AuthenticationFailedException if not admin 
     * @throws UnknownGroupException if the given group is not found
     * @throws UnknownAccountException if the given account is not found
     */
    protected function SendMessage(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        if ($input->HasParam('group'))
        {
            $groupid = $input->GetParam('group',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
            
            if (($dest = Group::TryLoadByID($this->database, $groupid)) === null) 
                throw new UnknownGroupException();
        }
        else if ($input->HasParam('account'))
        {
            $acctid = $input->GetParam('account',SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_ALWAYS);
            
            if (($dest = Account::TryLoadByID($this->database, $acctid)) === null)
                throw new UnknownAccountException();
        }
        else throw new UnknownAccountException();
        
        $subject = $input->GetParam('subject',SafeParam::TYPE_TEXT);
        
        $text = $input->GetParam('text',SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_NEVER);
        $html = $input->GetOptParam('html',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER);
        
        $dest->SendMessage($subject, $html, $text);
    }
    
    /**
     * Adds a new entry to the account create whitelist
     * @throws AuthenticationFailedException if not admin
     * @return array Whitelist
     * @see Whitelist::GetClientObject()
     */
    protected function AddWhitelist(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ALWAYS, 
            function(string $v){ return array_key_exists($v, Whitelist::TYPES); });
        
        $type = Whitelist::TYPES[$type];
        
        $value = $input->GetParam('value', SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_ALWAYS);
        
        return Whitelist::Create($this->database, $type, $value)->GetClientObject();
    }
    
    /**
     * Removes an entry from the account create whitelist
     * @throws AuthenticationFailedException if not admin
     */
    protected function RemoveWhitelist(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ALWAYS,
            function(string $v){ return array_key_exists($v, Whitelist::TYPES); });
        
        $type = Whitelist::TYPES[$type];
        
        $value = $input->GetParam('value', SafeParam::TYPE_TEXT, SafeParams::PARAMLOG_ALWAYS);
        
        Whitelist::DeleteByTypeAndValue($this->database, $type, $value);
    }
    
    /**
     * Gets all entries in the account whitelist
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Whitelist]
     * @see Whitelist::GetClientObject()
     */
    protected function GetWhitelist(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();

        return array_map(function(Whitelist $w){ return $w->GetClientObject(); }, Whitelist::LoadAll($this->database));
    }

    public function Test(Input $input)
    {
        $this->config->SetAllowCreateAccount(Config::CREATE_PUBLIC, true);
        $this->config->SetRequireContact(Config::CONTACT_EXIST, true);
        $this->config->SetUsernameIsContact(false, true);
        
        $results = array(); $app = "accounts";
        
        $email = Utilities::Random(8)."@unittest.com";
        $user = Utilities::Random(8); 
        $password = Utilities::Random(16);
        
        $test = $this->API->Run((new Input($app,'createaccount'))
            ->AddParam('email',$email)
            ->AddParam('username',$user)
            ->AddParam('password',$password));
        $results[] = $test;
        
        $test = $this->API->Run((new Input($app,'createsession'))
            ->AddParam('username',$user)
            ->AddParam('auth_password',$password));
        $results[] = $test;
        
        $sessionid = $test['client']['session']['id'];
        $sessionkey = $test['client']['session']['authkey'];
        
        $password2 = Utilities::Random(16);
        $test = $this->API->Run((new Input($app,'changepassword'))
            ->AddParam('auth_sessionid',$sessionid)
            ->AddParam('auth_sessionkey',$sessionkey)
            ->AddParam('getaccount',true)
            ->AddParam('auth_password',$password)
            ->AddParam('new_password',$password2));
        $results[] = $test;
        $password = $password2;
        
        $test = $this->API->Run((new Input($app,'deleteaccount'))
            ->AddParam('auth_sessionid',$sessionid)
            ->AddParam('auth_sessionkey',$sessionkey)
            ->AddParam('auth_password',$password));
        $results[] = $test;
        
        return $results;
    }
}

