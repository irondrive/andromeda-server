<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/BaseApp.php");
require_once(ROOT."/Core/ApiPackage.php");
require_once(ROOT."/Core/Utilities.php");
use Andromeda\Core\{ApiPackage, BaseApp, Utilities};

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParamInvalidException};
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Accounts/ActionLog.php");
require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Authenticator.php");
require_once(ROOT."/Apps/Accounts/AuthObject.php");
require_once(ROOT."/Apps/Accounts/Client.php");
require_once(ROOT."/Apps/Accounts/Config.php");
require_once(ROOT."/Apps/Accounts/Contact.php");
require_once(ROOT."/Apps/Accounts/Group.php");
require_once(ROOT."/Apps/Accounts/GroupStuff.php");
require_once(ROOT."/Apps/Accounts/KeySource.php");
require_once(ROOT."/Apps/Accounts/RecoveryKey.php");
require_once(ROOT."/Apps/Accounts/Session.php");
require_once(ROOT."/Apps/Accounts/TwoFactor.php");
require_once(ROOT."/Apps/Accounts/Whitelist.php");

require_once(ROOT."/Apps/Accounts/Auth/Manager.php");
require_once(ROOT."/Apps/Accounts/Auth/Local.php");
require_once(ROOT."/Apps/Accounts/Auth/LDAP.php");
require_once(ROOT."/Apps/Accounts/Auth/IMAP.php");
require_once(ROOT."/Apps/Accounts/Auth/FTP.php");

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\DecryptionFailedException;

/** Exception indicating that an account already exists under this username/email */
class AccountExistsException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that a group already exists with this name */
class GroupExistsException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("GROUP_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that this contact already exists */
class ContactExistsException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CONTACT_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that this group membership is for a default group and cannot be changed */
class ImmutableGroupException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("GROUP_MEMBERSHIP_REQUIRED", $details);
    }
}

/** Exception indicating that creating accounts is not allowed */
class AccountCreateDeniedException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_CREATE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that the requested username is not whitelisted */
class AccountWhitelistException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("USERNAME_NOT_WHITELISTED", $details);
    }
}

/** Exception indicating that deleting accounts is not allowed */
class AccountDeleteDeniedException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACCOUNT_DELETE_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that account/group search is not allowed */
class SearchDeniedException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SEARCH_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that server-side crypto is not allowed */
class CryptoNotAllowedException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("CRYPTO_NOT_ALLOWED", $details);
    }
}

/** Exception indicating that this group membership already exists */
class DuplicateGroupMembershipException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("GROUP_MEMBERSHIP_EXISTS", $details);
    }
}

/** Exception indicating that the password for an account using external authentication cannot be changed */
class ChangeExternalPasswordException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_CHANGE_EXTERNAL_PASSWORD", $details);
    }
}

/** Exception indicating that a recovery key cannot be generated */
class RecoveryKeyCreateException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_GENERATE_RECOVERY_KEY", $details);
    }
}

/** Exception indicating that the old password must be provided */
class OldPasswordRequiredException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("OLD_PASSWORD_REQUIRED", $details);
    }
}

/** Exception indicating that a new password must be provided */
class NewPasswordRequiredException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("NEW_PASSWORD_REQUIRED", $details);
    }
}

/** Exception indicating that an email address must be provided */
class ContactRequiredException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("VALID_CONTACT_REQUIRED", $details);
    }
}

/** Exception indicating that the test on the authentication source failed */
class AuthSourceTestFailException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("AUTH_SOURCE_TEST_FAIL", $details);
    }
}

/** Exception indicating that an unknown authentication source was given */
class UnknownAuthSourceException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_AUTHSOURCE", $details);
    }
}

/** Exception indicating that an unknown account was given */
class UnknownAccountException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ACCOUNT", $details);
    }
}

/** Exception indicating that an unknown group was given */
class UnknownGroupException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_GROUP", $details);
    }
}

/** Exception indicating that an unknown client was given */
class UnknownClientException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_CLIENT", $details);
    }
}

/** Exception indicating that an unknown session was given */
class UnknownSessionException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_SESSION", $details);
    }
}

/** Exception indicating that an unknown twofactor was given */
class UnknownTwoFactorException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_TWOFACTOR", $details);
    }
}

/** Exception indicating that an unknown contact was given */
class UnknownContactException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_CONTACT", $details);
    }
}

/** Exception indicating that the group membership does not exist */
class UnknownGroupMembershipException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_GROUPMEMBERSHIP", $details);
    }
}

/**
 * App for managing accounts and authenticating users.
 *
 * Creates and manages accounts, groups of accounts, authentication,
 * managing and validating contacts.  Supports account-crypto, two-factor 
 * authentication, multi-client/session management, authentication via external
 * sources, and granular per-account/per-group config.
 */
class AccountsApp extends BaseApp
{
    public static function getName() : string { return 'accounts'; }
    
    public static function getVersion() : string { return andromeda_version; }
    
    protected function getLogClass() : string { return ActionLog::class; }

    public static function getUsage() : array 
    { 
        return array(
            '- GENERAL AUTH: [--auth_sessionid id --auth_sessionkey randstr] [--auth_sudouser alphanum|email | --auth_sudoacct id]',
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            'getaccount [--account id] [--full bool]',
            'setfullname --fullname name',
            'enablecrypto --auth_password raw [--auth_twofactor int]',
            'disablecrypto --auth_password raw',
            'changepassword --new_password raw ((--username alphanum|email --auth_password raw) | --auth_recoverykey utf8)',
            'emailrecovery (--username alphanum|email | '.Contact::GetFetchUsage().')',
            'createaccount (--username alphanum | '.Contact::GetFetchUsage().') --password raw [--admin bool]',
            'createsession (--username alphanum|email | '.Contact::GetFetchUsage().') --auth_password raw [--authsource id] [--old_password raw] [--new_password raw]',
            '(createsession) [--auth_recoverykey utf8 | --auth_twofactor int] [--name ?name]',
            '(createsession) --auth_clientid id --auth_clientkey randstr',
            'createrecoverykeys --auth_password raw --auth_twofactor int [--replace bool]',
            'createtwofactor --auth_password raw [--comment ?text]',
            'verifytwofactor --auth_twofactor int',
            'createcontact '.Contact::GetFetchUsage(),
            'verifycontact --authkey utf8',
            'deleteaccount --auth_password raw --auth_twofactor int',
            'deletesession [--session id --auth_password raw]',
            'deleteclient [--client id --auth_password raw]',
            'deleteallauth --auth_password raw [--everyone bool]',
            'deletetwofactor --auth_password raw --twofactor id',
            'deletecontact --contact id',
            'editcontact --contact id [--usefrom bool] [--public bool]',
            'searchaccounts --name alphanum|email',
            'searchgroups --name name',
            'listaccounts [--limit ?uint] [--offset ?uint]',
            'listgroups [--limit ?uint] [--offset ?uint]',
            'creategroup --name name [--priority ?int8] [--comment ?text]',
            'editgroup --group id [--name name] [--priority int8] [--comment ?text]',
            'getgroup --group id',
            'deletegroup --group id',
            'addgroupmember --account id --group id',
            'removegroupmember --account id --group id',
            'getmembership --account id --group id',
            'getauthsources',
            'createauthsource --auth_password raw '.Auth\Manager::GetPropUsage().' [--test_username text --test_password raw]',
            ...array_map(function($u){ return "(createauthsource) $u"; }, Auth\Manager::GetPropUsages()),
            'testauthsource --manager id [--test_username alphanum|email --test_password raw]',
            'editauthsource --manager id --auth_password raw '.Auth\Manager::GetPropUsage().' [--test_username text --test_password raw]',
            'deleteauthsource --manager id --auth_password raw',
            'setaccountprops --account id '.AuthEntity::GetPropUsage().' [--expirepw bool]',
            'setgroupprops --group id '.AuthEntity::GetPropUsage(),
            'sendmessage (--account id | --group id) --subject utf8 --text text [--html raw]',
            'addwhitelist --type '.implode('|',array_keys(Whitelist::TYPES)).' --value alphanum|email',
            'removewhitelist --type '.implode('|',array_keys(Whitelist::TYPES)).' --value alphanum|email',
            'getwhitelist'
        );
    }
    
    public function __construct(ApiPackage $api)
    {
        parent::__construct($api);
        
        $this->config = Config::GetInstance($this->database);
        
        new Auth\Local(); // TODO refactor to not need a singleton
    }

    /**
     * {@inheritDoc}
     * @throws UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input)
    {
        $authenticator = Authenticator::TryAuthenticate(
            $this->database, $input, $this->API->GetInterface());
        
        $actionlog = ActionLog::Create($this->database, $authenticator); $input->SetLogger($actionlog); // TODO fix
        
        $params = $input->GetParams();
        
        switch($input->GetAction())
        {
            case 'getconfig':           return $this->GetConfig($authenticator);
            case 'setconfig':           return $this->SetConfig($params, $authenticator);
            
            case 'getauthsources':      return $this->GetAuthSources($authenticator);
            case 'createauthsource':    return $this->CreateAuthSource($params, $authenticator, $actionlog);
            case 'testauthsource':      return $this->TestAuthSource($params, $authenticator);
            case 'editauthsource':      return $this->EditAuthSource($params, $authenticator);
            case 'deleteauthsource':    $this->DeleteAuthSource($params, $authenticator, $actionlog); return;
            
            case 'getaccount':          return $this->GetAccount($params, $authenticator);
            case 'setfullname':         $this->SetFullName($params, $authenticator); return;
            case 'changepassword':      $this->ChangePassword($params, $authenticator); return;
            
            case 'emailrecovery':       $this->EmailRecovery($params); return;
            
            case 'createaccount':       return $this->CreateAccount($params, $authenticator, $actionlog);
            case 'createsession':       return $this->CreateSession($params, $authenticator, $actionlog);
            case 'enablecrypto':        return $this->EnableCrypto($params, $authenticator);
            case 'disablecrypto':       $this->DisableCrypto($authenticator); return;
            
            case 'createrecoverykeys':  return $this->CreateRecoveryKeys($params, $authenticator);
            case 'createtwofactor':     return $this->CreateTwoFactor($params, $authenticator, $actionlog);
            case 'verifytwofactor':     $this->VerifyTwoFactor($params, $authenticator); return;
            case 'createcontact':       return $this->CreateContact($params, $authenticator, $actionlog);
            case 'verifycontact':       $this->VerifyContact($params); return;
            
            case 'deleteaccount':       $this->DeleteAccount($authenticator, $actionlog); return;
            case 'deletesession':       $this->DeleteSession($params, $authenticator, $actionlog); return;
            case 'deleteclient':        $this->DeleteClient($params, $authenticator, $actionlog); return;
            case 'deleteallauth':       $this->DeleteAllAuth($params, $authenticator); return;
            case 'deletetwofactor':     $this->DeleteTwoFactor($params, $authenticator, $actionlog); return;
            
            case 'deletecontact':       $this->DeleteContact($params, $authenticator, $actionlog); return;
            case 'editcontact':         return $this->EditContact($params, $authenticator);
            
            case 'searchaccounts':      return $this->SearchAccounts($params, $authenticator);
            case 'searchgroups':        return $this->SearchGroups($params, $authenticator);
            case 'listaccounts':        return $this->ListAccounts($params, $authenticator);
            case 'listgroups':          return $this->ListGroups($params, $authenticator);
            case 'creategroup':         return $this->CreateGroup($params, $authenticator, $actionlog);
            case 'editgroup':           return $this->EditGroup($params, $authenticator); 
            case 'getgroup':            return $this->GetGroup($params, $authenticator);
            case 'deletegroup':         $this->DeleteGroup($params, $authenticator, $actionlog); return;
            case 'addgroupmember':      return $this->AddGroupMember($params, $authenticator);
            case 'removegroupmember':   return $this->RemoveGroupmember($params, $authenticator);
            case 'getmembership':       return $this->GetMembership($params, $authenticator);
            
            case 'setaccountprops':     return $this->SetAccountProps($params, $authenticator);
            case 'setgroupprops':       return $this->SetGroupProps($params, $authenticator);
            
            case 'sendmessage':         $this->SendMessage($params, $authenticator); return;
            
            case 'addwhitelist':        return $this->AddWhitelist($params, $authenticator);
            case 'removewhitelist':     $this->RemoveWhitelist($params, $authenticator); return;
            case 'getwhitelist':        return $this->GetWhitelist($authenticator);
            
            default: throw new UnknownActionException();
        }
    }
    
    /**
     * Get either an alphanum or email from the given param
     * @param SafeParam $param param to extract value from
     * @return string validated alphanum or email
     */
    public static function getUsername(SafeParam $param) : string
    {
        try { return $param->GetAlphanum(); }
        catch (SafeParamInvalidException $e) {
            return $param->GetEmail(); }
    }

    /**
     * Gets config for this app
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function GetConfig(?Authenticator $authenticator) : array
    {
        $admin = $authenticator !== null && $authenticator->isAdmin();

        return $this->GetConfig()->GetClientObject($admin);
    }
    
    /**
     * Sets config for this app
     * @throws AuthenticationFailedException if not admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();        
        $authenticator->RequireAdmin();
        
        return $this->GetConfig()->SetConfig($params)->GetClientObject(true);
    }
    
    /**
     * Returns a list of the configured authentication sources
     * @return array [id:Auth\Manager]
     * @see Auth\Manager::GetClientObject()
     */
    protected function GetAuthSources(?Authenticator $authenticator) : array
    {
        $admin = $authenticator !== null && $authenticator->isAdmin();
        
        $auths = Auth\Manager::LoadAll($this->database);
        
        if (!$admin) $auths = array_filter($auths, function(Auth\Manager $m){ return $m->GetEnabled(); });
        
        return array_map(function(Auth\Manager $m)use($admin){ return $m->GetClientObject($admin); }, $auths);
    }

    /**
     * Gets the current account object, or the specified one
     * @throws UnknownAccountException if the specified account is not valid
     * @return array Account
     * @see Account::GetClientObject()
     */
    protected function GetAccount(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) return null;
        
        if ($params->HasParam('account'))
        {
            $account = $params->GetParam('account',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            
            $account = Account::TryLoadByID($this->database, $account);
            if ($account === null) throw new UnknownAccountException();
        }
        else $account = $authenticator->GetAccount();
        
        $objtype = 0;
        
        $admin = $authenticator->isAdmin();
        if ($admin) $objtype |= Account::OBJECT_ADMIN;
        
        $self = ($account === $authenticator->GetAccount());
        $full = $params->GetOptParam("full",false)->GetBool();
        if ($full && ($admin || $self)) $objtype |= Account::OBJECT_FULL;

        return $account->GetClientObject($objtype);
    }

    /**
     * Changes the password for an account
     * 
     * If currently logged in, this changes the password for the user's account (requiring the old one)
     * If not logged in, this allows account recovery by resetting the password via a recovery key.
     * @throws AuthenticationFailedException if the given account or recovery key are invalid
     * @throws ChangeExternalPasswordException if the user's account uses an non-local auth source
     */
    protected function ChangePassword(SafeParams $params, ?Authenticator $authenticator) : void
    {   
        $new_password = $params->GetParam('new_password', SafeParams::PARAMLOG_NEVER)->GetRawString();
        
        $recoverykey = $params->HasParam('auth_recoverykey') ? 
            $params->GetParam('auth_recoverykey',SafeParams::PARAMLOG_NEVER)->GetUTF8String() : null;
        
        if ($recoverykey !== null)
        {
            $username = self::getUsername($params->GetParam("username", SafeParams::PARAMLOG_ALWAYS));
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
        else if (!$authenticator->isSudoUser()) 
            $authenticator->RequirePassword();
        
        Authenticator::StaticTryRequireCrypto($params, $account);
        $account->ChangePassword($new_password);
    }
    
    /** Returns the given string with each character after a space capitalized */
    private static function capitalizeWords(string $str) : string 
    { 
        return implode(" ",array_map(function(string $p){ 
            return Utilities::FirstUpper($p);
        }, explode(" ", trim($str)))); 
    }
    
    /**
     * Sets the user's full (real) name
     * @throws AuthenticationFailedException if not logged in
     */
    protected function SetFullName(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $fullname = self::capitalizeWords($params->GetParam('fullname')->GetName());
        
        $authenticator->GetAccount()->SetFullName($fullname);
    }
    
    /**
     * Emails a recovery key to the user's registered contacts
     * @throws UnknownAccountException if the given username is invalid
     * @throws RecoveryKeyCreateException if crypto or two factor are enabled
     */
    protected function EmailRecovery(SafeParams $params) : void
    {
        if ($params->HasParam('username'))
        {
            $username = self::getUsername($params->GetParam("username", SafeParams::PARAMLOG_ALWAYS));
            $account = Account::TryLoadByUsername($this->database, $username);
        }
        else
        {
            $contactInfo = Contact::FetchInfoFromParams($params);
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
    protected function EnableCrypto(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        if ($account->hasCrypto()) return null;
        
        if (!$account->GetAllowCrypto()) throw new CryptoNotAllowedException();
        
        $authenticator->RequirePassword()->TryRequireTwoFactor();
        
        $password = $params->GetParam('auth_password', SafeParams::PARAMLOG_NEVER)->GetRawString();

        if ($account->HasRecoveryKeys())
        {
            RecoveryKey::DeleteByAccount($this->database, $account);
        }
        
        $account->InitializeCrypto($password);
        
        if (($session = $authenticator->TryGetSession()) !== null)
        {
            $session->InitializeCrypto();
            
            Session::DeleteByAccountExcept($this->database, $account, $session);
        }
        else Session::DeleteByAccount($this->database, $account);
        
        return array_map(function(RecoveryKey $key){ return $key->GetClientObject(true); },
            RecoveryKey::CreateSet($this->database, $account));
    }
    
    /**
     * Disables server side crypto for an account
     * @throws AuthenticationFailedException if not signed in
     */
    protected function DisableCrypto(?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
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
    protected function CreateAccount(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        $admin = $authenticator !== null; 
        if ($admin) $authenticator->RequireAdmin();
        
        $allowCreate = $this->GetConfig()->GetAllowCreateAccount();
        
        if (!$admin && !$allowCreate) throw new AccountCreateDeniedException();
        
        $userIsContact = $this->GetConfig()->GetUsernameIsContact();
        $requireContact = $this->GetConfig()->GetRequireContact();
               
        if ($userIsContact || $requireContact >= Config::CONTACT_EXIST)
        {
            $contactInfo = Contact::FetchInfoFromParams($params);
            if ($userIsContact) $username = $contactInfo->info;
        }
        
        $username ??= $params->GetParam("username", 
            SafeParams::PARAMLOG_ALWAYS)->CheckLength(127)->GetAlphanum();

        if (!$admin && $allowCreate == Config::CREATE_WHITELIST)
        {
            $ok = Whitelist::ExistsTypeAndValue($this->database, Whitelist::TYPE_USERNAME, $username);
            
            if (isset($contactInfo)) $ok |= Whitelist::ExistsTypeAndValue($this->database, Whitelist::TYPE_CONTACT, $contactInfo->info);
            
            if (!$ok) throw new AccountWhitelistException();
        }

        $password = $params->GetParam("password", SafeParams::PARAMLOG_NEVER)->GetRawString();
        
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
        
        if ($admin && $params->GetOptParam('admin',false)->GetBool()) $account->setAdmin(true);
        
        if ($actionlog) $actionlog->LogDetails('account',$account->ID()); 

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
    protected function CreateSession(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        if ($params->HasParam('username'))
        {
            $username = self::getUsername($params->GetParam("username", SafeParams::PARAMLOG_ALWAYS));
            $account = Account::TryLoadByUsername($this->database, $username);
        }
        else 
        {
            $cinfo = Contact::FetchInfoFromParams($params);
            $account = Account::TryLoadByContactInfo($this->database, $cinfo);
            if ($account === null) // can't log in externally with contact info
                throw new AuthenticationFailedException();
            $username = $account->GetUsername(); // phpstan
        }
        
        $password = $params->GetParam("auth_password", SafeParams::PARAMLOG_NEVER)->GetRawString();
        
        $reqauthman = null; if ($params->HasParam('authsource'))
        {
            $mgrid = $params->GetParam('authsource', SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            
            $reqauthman = Auth\Manager::TryLoadByID($this->database,$mgrid);
            if ($reqauthman === null) throw new UnknownAuthSourceException();
        }
        
        if ($account !== null) /** check password */
        {
            $authsource = $account->GetAuthSource();
            $authman = ($authsource instanceof Auth\External)
                ? $authsource->GetManager() : null;
            
            /** check the authmanager matches if given */
            if ($reqauthman !== null && $reqauthman !== $authman)
                throw new AuthenticationFailedException();
            
            if ($authman !== null && !$authman->GetEnabled())
                 throw new AuthenticationFailedException();
             
            if (!$account->VerifyPassword($password))
                throw new AuthenticationFailedException();
        }
        else /** create account on the fly if external auth */
        {
            $authman = $reqauthman ?? $this->GetConfig()->GetDefaultAuth();
            if ($authman === null) throw new UnknownAuthSourceException();
            
            if ($authman->GetEnabled() < Auth\Manager::ENABLED_FULL)
                throw new AuthenticationFailedException();
            
            $authsource = $authman->GetAuthSource();
            if (!$authsource->VerifyUsernamePassword($username, $password))
                throw new AuthenticationFailedException();
            
            $account = Account::Create($this->database, $authsource, $username);
        }
        
        if (!$account->isEnabled()) throw new AccountDisabledException();
        
        if ($actionlog) $actionlog->LogDetails('account',$account->ID()); 
        
        $interface = $this->API->GetInterface();
        
        /* if a clientid is provided, check that it and the clientkey are correct */
        if ($params->HasParam('auth_clientid') && $params->HasParam('auth_clientkey'))
        {
            $clientid = $params->GetParam("auth_clientid", SafeParams::PARAMLOG_NEVER)->GetRandstr();
            $clientkey = $params->GetParam("auth_clientkey", SafeParams::PARAMLOG_NEVER)->GetRandstr();
            
            if ($account->GetForceUseTwoFactor() && $account->HasValidTwoFactor()) 
                Authenticator::StaticTryRequireTwoFactor($params, $account);
            
            $client = Client::TryLoadByID($this->database, $clientid);
            if ($client === null || !$client->CheckMatch($interface, $clientkey)) 
                throw new UnknownClientException();
        } 
        else /* if no clientkey, require either a recoverykey or twofactor, create a client */
        { 
            if ($params->HasParam('auth_recoverykey'))
            {
                $recoverykey = $params->GetParam('auth_recoverykey',SafeParams::PARAMLOG_NEVER)->GetUTF8String();
                
                if (!$account->CheckRecoveryKey($recoverykey))
                    throw new AuthenticationFailedException();
            }
            else Authenticator::StaticTryRequireTwoFactor($params, $account);
            
            $cname = $params->GetOptParam('name',null)->GetNullName();
            $client = Client::Create($interface, $this->database, $account, $cname);
        }
        
        if ($actionlog) $actionlog->LogDetails('client',$client->ID()); 
        
        /* unlock account crypto - failure means the password source must've changed without updating crypto */
        if ($account->hasCrypto())
        {
            try { $account->UnlockCryptoFromPassword($password); }
            catch (DecryptionFailedException $e)
            {
                if (!$params->HasParam('old_password')) throw new OldPasswordRequiredException();
                $old_password = $params->GetParam("old_password",SafeParams::PARAMLOG_NEVER)->GetRawString();
                $account->UnlockCryptoFromPassword($old_password);
                
                $account->ChangePassword($password);
            }
        }
        
        /* check account password age, possibly require a new one */
        if (!$account->CheckPasswordAge())
        {
            if (!$params->HasParam('new_password')) throw new NewPasswordRequiredException();
            $new_password = $params->GetParam('new_password',SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            $account->ChangePassword($new_password);
        }
        
        Client::PruneOldFor($this->database, $account);
        Session::PruneOldFor($this->database, $account);
        
        /* delete old session associated with this client, create a new one */
        $session = Session::Create($this->database, $account, $client->DeleteSession());
        
        if ($actionlog) $actionlog->LogDetails('session',$session->ID()); 
        
        /* update object dates */
        $client->setLoggedonDate();
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
    protected function CreateRecoveryKeys(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $authenticator->RequirePassword()->TryRequireTwoFactor()->TryRequireCrypto();        
        
        if ($params->GetOptParam('replace',false)->GetBool())
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
    protected function CreateTwoFactor(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $authenticator->RequirePassword()->TryRequireCrypto();
        
        $comment = $params->GetOptParam('comment',null)->GetNullHTMLText();
        
        $twofactor = TwoFactor::Create($this->database, $account, $comment);
        
        if ($actionlog) $actionlog->LogDetails('twofactor',$twofactor->ID()); 

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
    protected function VerifyTwoFactor(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $authenticator->TryRequireCrypto(); // can't use authenticator's RequireTwoFactor yet
        
        $account = $authenticator->GetAccount();
        
        $code = $params->GetParam("auth_twofactor",SafeParams::PARAMLOG_NEVER)->GetAlphanum(); // not an int (leading zeroes)
        
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
    protected function CreateContact(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $verify = $this->GetConfig()->GetRequireContact() >= Config::CONTACT_VALID;
        
        $info = Contact::FetchInfoFromParams($params);
        
        if (Contact::TryLoadByInfoPair($this->database, $info) !== null) throw new ContactExistsException();

        $contact = Contact::Create($this->database, $account, $info, $verify);
        
        if ($actionlog) $actionlog->LogDetails('contact',$contact->ID()); 
        
        return $contact->GetClientObject();
    }
    
    /**
     * Verifies an account contact
     * @throws AuthenticationFailedException if the given key is invalid
     * @throws UnknownContactException if the contact does not exist
     */
    protected function VerifyContact(SafeParams $params) : void
    {
        $authkey = $params->GetParam('authkey',SafeParams::PARAMLOG_NEVER)->GetUTF8String();
        
        $contact = Contact::TryLoadByFullKey($this->database, $authkey);
        if ($contact === null) throw new UnknownContactException();
        
        if (!$contact->CheckFullKey($authkey)) throw new AuthenticationFailedException();
    }
    
    /**
     * Deletes the current account (and signs out)
     * @throws AuthenticationFailedException if not signed in
     * @throws AccountDeleteDeniedException if delete is not allowed
     */
    protected function DeleteAccount(?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $account = $authenticator->GetAccount();
        
        if (!$authenticator->isRealAdmin() && !$account->GetAllowUserDelete()) 
            throw new AccountDeleteDeniedException();
        
        $authenticator->RequirePassword();
        
        if (!$authenticator->isSudoUser()) 
            $authenticator->TryRequireTwoFactor();
        
        if ($actionlog && $actionlog->isFullDetails()) $actionlog->LogDetails('account',
            $account->GetClientObject(Account::OBJECT_ADMIN | Account::OBJECT_FULL));
        
        $account->Delete();
    }
    
    /**
     * Deletes an account session (signing it out)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownSessionException if an invalid session was provided
     */
    protected function DeleteSession(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $account = $authenticator->GetAccount();
        $session = $authenticator->GetSession();

        $specify = $params->HasParam('session');
        if (($authenticator->isSudoUser()) && !$specify)
            throw new UnknownSessionException();

        if ($specify)
        {
            $sessionid = $params->GetParam("session",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            $session = Session::TryLoadByAccountAndID($this->database, $account, $sessionid);
            if ($session === null) throw new UnknownSessionException();
        }
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('session', $session->GetClientObject());
        
        $session->Delete();
    }
    
    /**
     * Deletes an account session and client (signing out fully)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownClientException if an invalid client was provided
     */
    protected function DeleteClient(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $account = $authenticator->GetAccount();
        $client = $authenticator->GetClient();

        $specify = $params->HasParam('client');
        if (($authenticator->isSudoUser()) && !$specify)
            throw new UnknownClientException();
            
        if ($specify)
        {
            $clientid = $params->GetParam("client",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            $client = Client::TryLoadByAccountAndID($this->database, $account, $clientid);
            if ($client === null) throw new UnknownClientException();
        }
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('client', $client->GetClientObject());
        
        $client->Delete();
    }
    
    /**
     * Deletes all registered clients/sessions for an account
     * @throws AuthenticationFailedException if not signed in
     */
    protected function DeleteAllAuth(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $authenticator->RequirePassword();
        
        if ($params->GetOptParam('everyone',false)->GetBool())
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
    protected function DeleteTwoFactor(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequirePassword();
        $account = $authenticator->GetAccount();
        
        $twofactorid = $params->GetParam("twofactor", SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $twofactor = TwoFactor::TryLoadByAccountAndID($this->database, $account, $twofactorid); 
        if ($twofactor === null) throw new UnknownTwoFactorException();
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('twofactor', $twofactor->GetClientObject());

        $twofactor->Delete();
    }    
    
    /**
     * Deletes a contact from an account
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownContactException if the contact is invalid
     * @throws ContactRequiredException if a valid contact is required
     */
    protected function DeleteContact(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $cid = $params->GetParam('contact',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $contact = Contact::TryLoadByAccountAndID($this->database, $account, $cid);
        if ($contact === null) throw new UnknownContactException();

        if ($this->GetConfig()->GetRequireContact() && $contact->GetIsValid() && count($account->GetContacts()) <= 1)
            throw new ContactRequiredException();
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('contact', $contact->GetClientObject());
    
        $contact->Delete();
    }
    
    /**
     * Edits a contact for an account
     * @throws AuthenticationFailedException
     * @throws UnknownContactException
     */
    protected function EditContact(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $cid = $params->GetParam('contact',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $contact = Contact::TryLoadByAccountAndID($this->database, $account, $cid);
        if ($contact === null) throw new UnknownContactException();
        
        if ($params->HasParam('usefrom')) $contact->setUseFrom($params->GetParam('usefrom')->GetBool());        
        if ($params->HasParam('public')) $contact->setIsPublic($params->GetParam('public')->GetBool());
        
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
    protected function SearchAccounts(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $limit = Account::DEFAULT_SEARCH_MAX;
        
        $account = $authenticator->TryGetAccount();
        if ($account !== null) $limit = $account->GetAllowAccountSearch();
        
        if (!$limit) throw new SearchDeniedException();

        $name = self::getUsername($params->GetParam('name')->CheckFunction(
            function(string $v){ return mb_strlen($v) >= 3; }));

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
    protected function SearchGroups(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $limit = Account::DEFAULT_SEARCH_MAX;
        
        $account = $authenticator->TryGetAccount();
        if ($account !== null) $limit = $account->GetAllowGroupSearch();
        
        if (!$limit) throw new SearchDeniedException();
        
        $name = $params->GetParam('name')->CheckFunction(
            function(string $v){ return mb_strlen($v) >= 3; })->GetName();
        
        return array_map(function(Group $group){ return $group->GetClientObject(); },
            Group::LoadAllMatchingName($this->database, $name, $limit));
    }
    
    /**
     * Returns a list of all registered accounts
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Account]
     * @see Account::GetClientObject()
     */
    protected function ListAccounts(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $limit = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
        
        $full = $params->GetOptParam("full",false)->GetBool();
        $type = $full ? Account::OBJECT_ADMIN : 0;
        
        $accounts = Account::LoadAll($this->database, $limit, $offset);
        
        return array_map(function(Account $account)use($type){ 
            return $account->GetClientObject($type); }, $accounts);
    }
    
    /**
     * Returns a list of all registered groups
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Group]
     * @see Group::GetClientObject()
     */
    protected function ListGroups(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $limit = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
        
        $groups = Group::LoadAll($this->database, $limit, $offset);
        
        return array_map(function(Group $group){ 
            return $group->GetClientObject(Group::OBJECT_ADMIN); }, $groups);
    }
    
    /**
     * Creates a new account group
     * @throws AuthenticationFailedException if not admin
     * @throws GroupExistsException if the group name exists already
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function CreateGroup(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $name = $params->GetParam("name")->CheckLength(127)->GetName();
        
        $priority = $params->GetOptParam('priority',null)->GetNullInt8();
        $comment = $params->GetOptParam('comment',null)->GetNullHTMLText();
        
        $duplicate = Group::TryLoadByName($this->database, $name);
        if ($duplicate !== null) throw new GroupExistsException();

        $group = Group::Create($this->database, $name, $priority, $comment);
        
        if ($actionlog) $actionlog->LogDetails('group',$group->ID()); 
        
        return $group->Initialize()->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }    
    
    /**
     * Edits properties of an existing group
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is not found
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function EditGroup(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($params->HasParam('name')) 
        {
            $name = $params->GetParam("name")->CheckLength(127)->GetName();
            
            $duplicate = Group::TryLoadByName($this->database, $name);
            if ($duplicate !== null) throw new GroupExistsException();
            
            $group->SetDisplayName($name);
        }
 
        if ($params->HasParam('priority')) $group->SetPriority($params->GetParam("priority")->GetInt8());
        if ($params->HasParam('comment')) $group->SetComment($params->GetParam("comment")->GetNullHTMLText());
        
        return $group->GetClientObject(Group::OBJECT_ADMIN);
    }
    
    /**
     * Returns the requested group object
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is invalid
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function GetGroup(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        return $group->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }

    /**
     * Deletes an account group
     * @throws AuthenticationFailedException if not admin 
     * @throws UnknownGroupException if the group does not exist
     */
    protected function DeleteGroup(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if ($actionlog && $actionlog->isFullDetails()) $actionlog->LogDetails('group',
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
    protected function AddGroupMember(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $params->GetParam("account",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
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
    protected function RemoveGroupMember(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $params->GetParam("account",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $account = Account::TryLoadByID($this->database, $accountid);
        if ($account === null) throw new UnknownAccountException();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();
        
        if (array_key_exists($group->ID(), $account->GetDefaultGroups()))
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
    protected function GetMembership(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $accountid = $params->GetParam("account",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
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
    protected function CreateAuthSource(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword();

        $manager = Auth\Manager::Create($this->database, $params);
        
        if ($params->HasParam('test_username'))
        {
            $params->AddParam('manager',$manager->ID());
            $this->TestAuthSource($params, $authenticator);
        }
        
        if ($actionlog) $actionlog->LogDetails('manager',$manager->ID()); 
        
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
    protected function TestAuthSource(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $manager = $params->GetParam('manager',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();        
        
        $testuser = self::getUsername($params->GetParam('test_username'));
        $testpass = $params->GetParam('test_password',SafeParams::PARAMLOG_NEVER)->GetRawString();
        
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
    protected function EditAuthSource(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword();
        
        $manager = $params->GetParam('manager',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();
        
        if ($params->HasParam('test_username')) 
            $this->TestAuthSource($params, $authenticator);
        
        return $manager->Edit($params)->GetClientObject(true);
    }
    
    /**
     * Removes an external auth source, deleting accounts associated with it!
     * @throws AuthenticationFailedException if not an admin
     * @throws UnknownAuthSourceException if the auth source does not exist
     */
    protected function DeleteAuthSource(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin()->RequirePassword()->TryRequireTwoFactor();
        
        $manager = $params->GetParam('manager',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $manager = Auth\Manager::TryLoadByID($this->database, $manager);
        if ($manager === null) throw new UnknownAuthSourceException();
        
        if ($actionlog && $actionlog->isFullDetails()) 
            $actionlog->LogDetails('manager', $manager->GetClientObject(true));
        
        $manager->Delete();
    }
    
    /**
     * Sets config on an account
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownAccountException if the account is not found
     * @return array Account
     * @see Account::GetClientObject()
     */
    protected function SetAccountProps(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $acctid = $params->GetParam("account",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $account = Account::TryLoadByID($this->database, $acctid);
        if ($account === null) throw new UnknownAccountException();
        
        if ($params->GetOptParam("expirepw",false)->GetBool()) 
            $account->resetPasswordDate();
        
        return $account->SetProperties($params)->GetClientObject(Account::OBJECT_ADMIN);
    }
    
    /**
     * Sets config on a group
     * @throws AuthenticationFailedException if not admin
     * @throws UnknownGroupException if the group is not found
     * @return array Group
     * @see Group::GetClientObject()
     */
    protected function SetGroupProps(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $groupid = $params->GetParam("group",SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $group = Group::TryLoadByID($this->database, $groupid);
        if ($group === null) throw new UnknownGroupException();

        return $group->SetProperties($params)->GetClientObject(Group::OBJECT_FULL | Group::OBJECT_ADMIN);
    }
    
    /**
     * Sends a message to the given account or group's contacts
     * @throws AuthenticationFailedException if not admin 
     * @throws UnknownGroupException if the given group is not found
     * @throws UnknownAccountException if the given account is not found
     */
    protected function SendMessage(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        if ($params->HasParam('group'))
        {
            $groupid = $params->GetParam('group',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            
            if (($dest = Group::TryLoadByID($this->database, $groupid)) === null) 
                throw new UnknownGroupException();
        }
        else if ($params->HasParam('account'))
        {
            $acctid = $params->GetParam('account',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
            
            if (($dest = Account::TryLoadByID($this->database, $acctid)) === null)
                throw new UnknownAccountException();
        }
        else throw new UnknownAccountException();
        
        $subject = $params->GetParam('subject')->GetUTF8String();
        
        $text = $params->GetParam('text',SafeParams::PARAMLOG_NEVER)->GetHTMLText();
        $html = $params->GetOptParam('html',SafeParams::PARAMLOG_NEVER)->GetRawString();
        
        $dest->SendMessage($subject, $html, $text);
    }
    
    /**
     * Adds a new entry to the account create whitelist
     * @throws AuthenticationFailedException if not admin
     * @return array Whitelist
     * @see Whitelist::GetClientObject()
     */
    protected function AddWhitelist(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $type = $params->GetParam('type',SafeParams::PARAMLOG_ALWAYS)
            ->FromWhitelist(array_keys(Whitelist::TYPES));
        
        $type = Whitelist::TYPES[$type];
        
        $value = self::getUsername($params->GetParam('value',SafeParams::PARAMLOG_ALWAYS));
        
        return Whitelist::Create($this->database, $type, $value)->GetClientObject();
    }
    
    /**
     * Removes an entry from the account create whitelist
     * @throws AuthenticationFailedException if not admin
     */
    protected function RemoveWhitelist(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $type = $params->GetParam('type',SafeParams::PARAMLOG_ALWAYS)
            ->FromWhitelist(array_keys(Whitelist::TYPES));
        
        $type = Whitelist::TYPES[$type];
        
        $value = self::getUsername($params->GetParam('value',SafeParams::PARAMLOG_ALWAYS));
        
        Whitelist::DeleteByTypeAndValue($this->database, $type, $value);
    }
    
    /**
     * Gets all entries in the account whitelist
     * @throws AuthenticationFailedException if not admin
     * @return array [id:Whitelist]
     * @see Whitelist::GetClientObject()
     */
    protected function GetWhitelist(?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $authenticator->RequireAdmin();
        
        $list = Whitelist::LoadAll($this->database);

        return array_map(function(Whitelist $w){ 
            return $w->GetClientObject(); }, $list);
    }
}

