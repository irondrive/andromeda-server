<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\{Crypto, EmailRecipient, Utilities};
use Andromeda\Core\DecryptionFailedException;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder};

use Andromeda\Apps\Accounts\Resource\{Contact, Client, RecoveryKey, Session, TwoFactor};
use Andromeda\Apps\Accounts\Crypto\KeySource;

/**
 * Class representing a user account in the database
 * 
 * Can inherit properties from groups.  Can have any number
 * of registered clients, can have registered two factor, 
 * can provide secret-key crypto services, provides contact info
 */
class Account extends PolicyBase
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'username' => new FieldTypes\StringType(), 
            'fullname' => new FieldTypes\StringType(), 
            'comment' => new FieldTypes\StringType(),
            'master_key' => new FieldTypes\StringType(),
            'master_nonce' => new FieldTypes\StringType(),
            'master_salt' => new FieldTypes\StringType(),
            'password' => new FieldTypes\StringType(),
            'date_passwordset' => new FieldTypes\Timestamp(),
            'date_loggedon' => new FieldTypes\Timestamp(),
            'date_active' => new FieldTypes\Timestamp(null, true),
            'obj_authsource'    => new FieldTypes\ObjectPoly(AuthSource\External::class),
            'objs_sessions'      => (new FieldTypes\ObjectRefs(Session::class, 'account'))->autoDelete(),
            'objs_contacts'      => (new FieldTypes\ObjectRefs(Contact::class, 'account'))->autoDelete(),
            'objs_clients'       => (new FieldTypes\ObjectRefs(Client::class, 'account'))->autoDelete(),
            'objs_twofactors'    => (new FieldTypes\ObjectRefs(TwoFactor::class, 'account'))->autoDelete(),
            'objs_recoverykeys'  => (new FieldTypes\ObjectRefs(RecoveryKey::class, 'account'))->autoDelete(),
            'objs_groups'        => new FieldTypes\ObjectJoin(Group::class, GroupJoin::class, 'accounts')
        ));
    }
    
    use GroupInherit;
    
    public const DISABLE_PERMANENT = 1;
    public const DISABLE_PENDING_CONTACT = 2;
    
    public const DEFAULT_SEARCH_MAX = 3;
    
    /**
     * Gets the fields that can be inherited from a group, with their default values
     * @return array<string, mixed>
     */
    protected static function GetInheritedFields() : array { return array(
        'session_timeout' => null,
        'client_timeout' => null,
        'max_password_age' => null,
        'admin' => false,
        'disabled' => false,
        'forcetf' => false,
        'allowcrypto' => true,
        'accountsearch' => self::DEFAULT_SEARCH_MAX,
        'groupsearch' => self::DEFAULT_SEARCH_MAX,
        'userdelete' => true,
        'limit_sessions' => null,
        'limit_contacts' => null,
        'limit_recoverykeys' => null
    ); }
    
    /** Returns the account's username */
    public function GetUsername() : string  { return $this->GetScalar('username'); }
    
    /** Returns the account's full name if set, else its username */
    public function GetDisplayName() : string { return $this->TryGetScalar('fullname') ?? $this->GetUsername(); }
    
    /** Sets the account's full name */
    public function SetFullName(string $data) : self { return $this->SetScalar('fullname',$data); }
    
    /**
     * Loads the groups that the account implicitly belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetDefaultGroups() : array
    {
        $retval = array();
        
        $default = Config::GetInstance($this->database)->GetDefaultGroup();
        if ($default !== null) $retval[$default->ID()] = $default;
        
        $authman = $this->GetAuthSource();
        if ($authman instanceof AuthSource\External) 
        {
            $default = $authman->GetManager()->GetDefaultGroup();
            if ($default !== null) $retval[$default->ID()] = $default;
        }
        
        return $retval;
    }
    
    /**
     * Returns a list of all groups that the account belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetGroups() : array { return array_merge($this->GetDefaultGroups(), $this->GetMyGroups()); }
    
    /**
     * Returns a list of all groups that the account explicitly belongs to
     * @return array<string, Group> groups indexed by ID
     */
    public function GetMyGroups() : array { return $this->GetObjectRefs('groups'); }
    
    /** Adds the account to the given group */
    public function AddGroup(Group $group) : self    { $this->AddObjectRef('groups', $group); return $this; }
    
    /** Removes the account from the given group */
    public function RemoveGroup(Group $group) : self { $this->RemoveObjectRef('groups', $group); return $this; }
    
    /** Returns true if the account is a member of the given group */
    public function HasGroup(Group $group) : bool { return array_key_exists($group->ID(), $this->GetGroups()); }
    
    private static array $group_handlers = array();
    
    /** Registers a function to be run when the account is added to or removed from a group */
    public static function RegisterGroupChangeHandler(callable $func){ self::$group_handlers[] = $func; }

    /** Runs all functions registered to handle the account being added to or removed from a group */
    public static function RunGroupChangeHandlers(ObjectDatabase $database, Account $account, Group $group, bool $added)
        { foreach (self::$group_handlers as $func) $func($database, $account, $group, $added); }
        // TODO check that it has the group first?
        
    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::AddObjectRef($field, $object, $notification);
        
        if ($field === 'groups' && $modified) static::RunGroupChangeHandlers($this->database, $this, $object, true);
        
        return $modified;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::RemoveObjectRef($field, $object, $notification);
        
        if ($field === 'groups' && $modified) static::RunGroupChangeHandlers($this->database, $this, $object, false);
        
        return $modified;
    }
    
    /** Returns the object joining this account to the given group */
    public function GetGroupJoin(Group $group) : ?GroupJoin 
    {
        return $this->TryGetJoinObject('groups', $group);
    }

    /** Returns the auth source the account authenticates against */
    public function GetAuthSource() : AuthSource\ISource
    { 
        $authsource = $this->TryGetObject('authsource');
        if ($authsource !== null) return $authsource;
        else return AuthSource\Local::GetInstance();
    }
    
    /**
     * Returns an array of clients registered to the account
     * @return array<string, Client> clients indexed by ID
     */
    public function GetClients() : array        { return $this->GetObjectRefs('clients'); }
    
    /** Deletes all clients registered to the account */
    public function DeleteClients() : self      { $this->DeleteObjects('clients'); return $this; }
    
    /**
     * Returns an array of sessions registered to the account
     * @return array<string, Session> sessions indexed by ID
     */
    public function GetSessions() : array       { return $this->GetObjectRefs('sessions'); }
    
    /**
     * Returns an array of recovery keys for the account
     * @return array<string, RecoveryKey> keys indexed by ID
     */
    private function GetRecoveryKeys() : array  { return $this->GetObjectRefs('recoverykeys'); }
    
    /** True if recovery keys exist for the account */
    public function HasRecoveryKeys() : bool    { return $this->CountObjectRefs('recoverykeys') > 0; }
    
    /**
     * Returns an array of twofactors for the account
     * @return array<string, TwoFactor> twofactors indexed by ID
     */
    private function GetTwoFactors() : array    { return $this->GetObjectRefs('twofactors'); }
    
    /** True if a two factor exists for the account */
    public function HasTwoFactor() : bool       { return $this->CountObjectRefs('twofactors') > 0; }
    
    /** True if two factor should be required to create a session even for a pre-existing client */
    public function GetForceUseTwoFactor() : bool  { return $this->TryGetFeatureBool('forcetf') ?? self::GetInheritedFields()['forcetf']; }
    
    /** True if account-based server-side crypto is allowed */
    public function GetAllowCrypto() : bool     { return $this->TryGetFeatureBool('allowcrypto') ?? self::GetInheritedFields()['allowcrypto']; }
    
    /** Returns 0 if account search is disabled, or N if up to N matches are allowed */
    public function GetAllowAccountSearch() : int { return $this->TryGetFeatureInt('accountsearch') ?? self::GetInheritedFields()['accountsearch']; }

    /** Returns 0 if group search is disabled, or N if up to N matches are allowed */
    public function GetAllowGroupSearch() : int { return $this->TryGetFeatureInt('groupsearch') ?? self::GetInheritedFields()['groupsearch']; }
    
    /** Returns true if the user is allowed to delete their account */
    public function GetAllowUserDelete() : bool { return $this->TryGetFeatureBool('userdelete') ?? self::GetInheritedFields()['userdelete']; }
    
    /** True if this account has administrator privileges */
    public function isAdmin() : bool            { return $this->TryGetFeatureBool('admin') ?? self::GetInheritedFields()['admin']; }
    
    /** True if this account is enabled */
    public function isEnabled() : bool       { return !(bool)($this->TryGetFeatureBool('disabled') ?? self::GetInheritedFields()['disabled']); }
    
    /** Sets this account's admin-status to the given value */
    public function SetAdmin(?bool $val) : self { return $this->SetFeatureBool('admin', $val); }
    
    /** Sets the account's disabled status to the given enum value */
    public function SetDisabled(?int $val = self::DISABLE_PERMANENT) : self { return $this->SetFeatureInt('disabled', $val); }    
    
    /** Gets the timestamp when this user was last active */
    public function getActiveDate() : ?float    { return $this->TryGetDate('active'); }
    
    /** Sets the last-active timestamp to now */
    public function SetActiveDate() : self      
    { 
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        return $this->SetDate('active'); 
    }
    
    /** Gets the timestamp when this user last created a session */
    public function getLoggedonDate() : ?float  { return $this->TryGetDate('loggedon'); }
    
    /** Sets the timestamp of last-login to now */
    public function SetLoggedonDate() : self    { return $this->SetDate('loggedon'); }
    
    private function getPasswordDate() : ?float { return $this->TryGetDate('passwordset'); }
    private function SetPasswordDate() : self   { return $this->SetDate('passwordset'); }
    
    /** Sets the account's last password change date to 0, potentially forcing a password reset */
    public function resetPasswordDate() : self  { return $this->SetDate('passwordset', 0); }
    
    /** Returns the maximum allowed time since a client was last active for it to be valid */
    public function GetClientTimeout() : ?int   { return $this->TryGetScalar('client_timeout') ?? self::GetInheritedFields()['client_timeout']; }
    
    /** Returns the maximum allowed time since a session was last active for it to be valid */
    public function GetSessionTimeout() : ?int   { return $this->TryGetScalar('session_timeout') ?? self::GetInheritedFields()['session_timeout']; }
    
    private function GetMaxPasswordAge() : ?int  { return $this->TryGetScalar('max_password_age') ?? self::GetInheritedFields()['max_password_age']; }
    
    /**
     * Returns an array of accounts with any part of their full name matching the name given
     * @param ObjectDatabase $database database reference 
     * @param string $fullname the name fragment to search for
     * @return array<string, Account> Accounts indexed by ID
     */
    public static function SearchByFullName(ObjectDatabase $database, string $fullname) : array
    {
        $q = new QueryBuilder(); return static::LoadByQuery($database, $q->Where($q->Like('fullname',$fullname)));
    }
    
    /**
     * Attempts to load an account with the given username
     * @param ObjectDatabase $database database reference
     * @param string $username username to load for
     * @return ?static loaded account or null if not found
     */
    public static function TryLoadByUsername(ObjectDatabase $database, string $username) : ?self
    {
        return static::TryLoadByUniqueKey($database, 'username', $username);
    }

    /**
     * Returns all accounts whose username, fullname or contacts match the given info
     * @param ObjectDatabase $database database reference
     * @param string $info username/other info to match by (wildcard)
     * @param int $limit max # to load - returns nothing if exceeded (in a single category)
     * @return array Account
     * @see Account::GetClientObject()
     */
    public static function LoadAllMatchingInfo(ObjectDatabase $database, string $info, int $limit) : array
    {
        $q1 = new QueryBuilder(); $q2 = new QueryBuilder(); 
        
        $info = QueryBuilder::EscapeWildcards($info).'%'; // search by prefix
        
        $loaded1 = static::LoadByQuery($database, $q1->Where($q1->Like('username',$info,true))->Limit($limit+1));
        if (count($loaded1) >= $limit+1) $loaded1 = array(); else $limit -= count($loaded1);
        
        $loaded2 = Contact::LoadAccountsMatchingValue($database, $info, $limit+1);
        if (count($loaded2) >= $limit+1) $loaded2 = array(); else $limit -= count($loaded2);
        
        $loaded3 = static::LoadByQuery($database, $q2->Where($q2->Like('fullname',$info,true))->Limit($limit+1));
        if (count($loaded3) >= $limit+1) $loaded3 = array(); else $limit -= count($loaded3);
        
        return $loaded1 + $loaded2 + $loaded3;
    }
    
    /**
     * Returns an array of all accounts based on the given auth source
     * @param ObjectDatabase $database database reference
     * @param AuthSource\External $authman authentication source
     * @return array<string, Account> accounts indexed by ID
     */
    public static function LoadByAuthSource(ObjectDatabase $database, AuthSource\External $authman) : array
    {
        return static::LoadByObject($database, 'authsource', $authman->GetAuthSource(), true);
    }
    
    /**
     * Deletes all accounts using the given auth source
     * @param ObjectDatabase $database database reference
     * @param AuthSource\External $authman authentication source
     */
    public static function DeleteByAuthSource(ObjectDatabase $database, AuthSource\External $authman) : void
    {
        static::DeleteByObject($database, 'authsource', $authman->GetAuthSource(), true);
    }   
    
    /**
     * Returns all contacts for this account
     * @param bool $valid if true return only validated contacts
     * @return array<string, Contact> contacts indexed by ID
     */
    public function GetContacts(bool $valid = true) : array
    {
        $contacts = $this->GetObjectRefs('contacts');
        
        if ($valid) $contacts = array_filter($contacts, 
            function(Contact $contact){ return $contact->GetIsValid(); });
        
        return $contacts;
    }
    
    /**
     * Returns EmailReceipient objects for all email contacts
     * @return array<string, EmailRecipient>
     */
    public function GetContactEmails() : array // TODO not wild about email-specific things in Account
    {
        $emails = array_filter($this->GetContacts(), 
            function(Contact $contact){ return $contact->isEmail(); });
        
        return array_map(function(Contact $contact){ 
            return $contact->GetAsEmailRecipient(); }, $emails);
    }

    /**
     * Returns the EmailRecipient to use for sending email FROM this account
     * @return ?EmailRecipient email recipient object or null if not set
     */
    public function GetEmailFrom() : ?EmailRecipient // TODO not wild about email-specific things in Account
    {
        $contact = Contact::TryLoadAccountFromContact($this->database, $this);
        
        return $contact->isEmail() ? $contact->GetAsEmailRecipient() : null;
    }
    
    /**
     * Sends a message to all of this account's valid contacts
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        Contact::SendMessageMany($subject, $html, $plain, $this->GetContacts(), false, $from);
    }    

    /** Sets this account to enabled if it was disabled pending a valid contact */
    public function NotifyValidContact() : self
    {
        return ($this->TryGetFeatureInt('disabled') === self::DISABLE_PENDING_CONTACT) ? $this->SetDisabled(null) : $this;
    }
        
    /**
     * Creates a new user account
     * @param ObjectDatabase $database database reference
     * @param string $username the account's username
     * @param ?AuthSource\IAuthSource $source the auth source for the account if external
     * @param ?string $password the account's password, if not external auth
     * @return static created account
     */
    public static function Create(ObjectDatabase $database, string $username, ?AuthSource\IAuthSource $source = null, ?string $password = null) : self
    {
        $account = $database->CreateObject(static::class)->SetScalar('username',$username);
        
        if ($source instanceof AuthSource\External) 
            $account->SetObject('authsource',$source);
        else $account->ChangePassword($password);
        
        foreach ($account->GetDefaultGroups() as $group)
            static::RunGroupChangeHandlers($database, $account, $group, true);

        return $account;
    }
    
    private static array $delete_handlers = array();
    
    /** Registers a function to be run when an account is deleted */
    public static function RegisterDeleteHandler(callable $func){ self::$delete_handlers[] = $func; }
    
    /**
     * Deletes this account and all associated objects
     * @see BaseObject::Delete()
     */
    public function Delete() : void
    {
        // non-default groups will be handled in ref setting
        foreach ($this->GetDefaultGroups() as $group)
            static::RunGroupChangeHandlers($this->database, $this, $group, false);
        
        foreach (self::$delete_handlers as $func) $func($this->database, $this);
        
        parent::Delete();
    }
    
    public const OBJECT_FULL = 1; 
    public const OBJECT_ADMIN = 2;
    
    /**
     * Gets this account as a printable object
     * @return array<mixed> `{id:id,username:string,dispname:string}` \
        if OBJECT_FULL or OBJECT_ADMIN, add: {dates:{created:float,passwordset:?float,loggedon:?float,active:?float}, 
            counters:{groups:int,sessions:int,contacts:int,clients:int,twofactors:int,recoverykeys:int}, 
            limits:{sessions:?int,contacts:?int,recoverykeys:?int}, config:{admin:bool,disabled:int,forcetf:bool,allowcrypto:bool
                accountsearch:int, groupsearch:int, userdelete:bool},session_timeout:?int,client_timeout:?int,max_password_age:?int} \
        if OBJECT_FULL, add: {contacts:[id:Contact], clients:[id:Client], twofactors:[id:TwoFactor]} \
        if OBJECT_ADMIN, add: {twofactor:bool, comment:?string, groups:[id], limits_from:[string:"id:class"], dates:{modified:?float},
            config_from:[string:"id:class"], session_timeout_from:"id:class", client_timeout_from:"id:class", max_password_age_from:"id:class"}
     * @see Contact::GetClientObject()
     * @see TwoFactor::GetClientObject()
     * @see Client::GetClientObject()
     */
    public function GetClientObject(int $level = 0) : array
    {
        $mapobj = function($e) { return $e->GetClientObject(); };
        
        $data = array(
            'id' => $this->ID(),
            'username' => $this->GetUsername(),
            'dispname' => $this->GetDisplayName()
        );

        if ($level & self::OBJECT_FULL || $level & self::OBJECT_ADMIN)
        {
            $data += array(
                'client_timeout' => $this->GetClientTimeout(),
                'session_timeout' => $this->GetSessionTimeout(),
                'max_password_age' => $this->GetMaxPasswordAge(),
                'dates' => array(
                    'created' => $this->GetDateCreated(),
                    'passwordset' => $this->getPasswordDate(),
                    'loggedon' => $this->getLoggedonDate(),
                    'active' => $this->getActiveDate()
                ),
                'config' => array_merge(
                    Utilities::array_map_keys(function($p){ return $this->GetFeatureBool($p); },
                        array('admin','forcetf','allowcrypto','userdelete')),
                    Utilities::array_map_keys(function($p){ return $this->GetFeatureInt($p); },
                        array('disabled','accountsearch','groupsearch'))
                ),
                'counters' => Utilities::array_map_keys(function($p){ return $this->CountObjectRefs($p); },
                    array('sessions','contacts','clients','twofactors','recoverykeys')
                ),
                'limits' => Utilities::array_map_keys(function($p){ return $this->TryGetCounterLimit($p); },
                    array('sessions','contacts','recoverykeys')
                )
            );
        }
        
        if ($level & self::OBJECT_FULL)
        {
            $data += array(
                'twofactors' => array_map($mapobj, $this->GetTwoFactors()),
                'contacts' => array_map($mapobj, $this->GetContacts(false)),
                'clients' => array_map($mapobj, $this->GetClients()),
            );
        }
        else
        {            
            $data['contacts'] = array_map($mapobj, array_filter($this->GetContacts(),
                function(Contact $c){ return $c->getIsPublic(); }));
        }

        if ($level & self::OBJECT_ADMIN)
        {
            $data += array(
                'twofactor' => $this->HasValidTwoFactor(),
                'comment' => $this->TryGetScalar('comment'),
                'groups' => array_keys($this->GetGroups()),
                'client_timeout_from' => static::toString($this->TryGetInheritsScalarFrom('client_timeout')),
                'session_timeout_from' => static::toString($this->TryGetInheritsScalarFrom('session_timeout')),
                'max_password_age_from' => static::toString($this->TryGetInheritsScalarFrom('max_password_age')),
                
                'config_from' => Utilities::array_map_keys(function($p){ 
                    return static::toString($this->TryGetInheritsScalarFrom("$p")); }, array_keys($data['config'])),
                    
                'limits_from' => Utilities::array_map_keys(function($p){ 
                    return static::toString($this->TryGetInheritsScalarFrom("limit_$p")); }, array_keys($data['limits'])),
            );
            
            $data['dates']['modified'] = $this->TryGetDate('modified');
            $data['counters']['groups'] = $this->CountObjectRefs('groups');
        }

        return $data;
    }

    /** Returns true if the account has a validated two factor and recovery keys */
    public function HasValidTwoFactor() : bool
    {
        if ($this->CountObjectRefs('recoverykeys') <= 0) return false;
        
        foreach ($this->GetTwoFactors() as $twofactor) {
            if ($twofactor->GetIsValid()) return true; }
        return false;
    }    
    
    /**
     * Checks a two factor code
     * @param string $code the given twofactor code
     * @param bool $force if true, accept non-valid twofactor sources
     * @return bool true if there is a valid twofactor (or force) and the code is valid
     */
    public function CheckTwoFactor(string $code, bool $force = false) : bool
    {
        if (!$force && !$this->HasValidTwoFactor()) return false;  
        
        foreach ($this->GetTwoFactors() as $twofactor) { 
            if ($twofactor->CheckCode($code)) return true; }        
        return false;
    }
    
    /** Returns true if the given recovery key matches one (and they exist) */
    public function CheckRecoveryKey(string $key) : bool
    {
        if (!$this->HasRecoveryKeys()) return false; 
        
        $obj = RecoveryKey::TryLoadByFullKey($this->database, $key, $this);

        if ($obj === null) return false;
        
        else return $obj->CheckFullKey($key);
    }
    
    /** Returns true if the given password is correct for this account */
    public function VerifyPassword(string $password) : bool
    {
        return $this->GetAuthSource()->VerifyAccountPassword($this, $password);
    }    
    
    /** Returns true if the account's password is not out of date, or is using external auth */
    public function CheckPasswordAge() : bool
    {
        if (!($this->GetAuthSource() instanceof AuthSource\Local)) return true;
        
        $date = $this->getPasswordDate(); 
        $max = $this->GetMaxPasswordAge();
        
        if ($date <= 0) return false; else return 
            ($max === null || $database->GetTime() - $date < $max);
    }
    
    /** Returns true if server-side crypto is unavailable on the account */
    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    
    private bool $cryptoAvailable = false; 
    
    /** Returns true if crypto has been unlocked in this request and is available for operations */
    public function isCryptoAvailable() : bool { return $this->cryptoAvailable; }
    
    /** Re-keys the account's crypto if it exists, and re-hashes its password (if using local auth) */
    public function ChangePassword(string $new_password) : Account
    {
        if ($this->hasCrypto())
        {
           $this->InitializeCrypto($new_password, true); // keeps same key
           
           foreach ($this->GetTwoFactors() as $tf) $tf->InitializeCrypto();
        }
        
        if ($this->GetAuthSource() instanceof AuthSource\Local)
            AuthSource\Local::SetPassword($this, $new_password);
        // TODO move check for external auth here

        return $this->SetPasswordDate();
    }
    
    /** Gets the account's password hash */
    public function GetPasswordHash() : string { return $this->GetScalar('password'); }
    
    /** Sets the account's password hash to the given value */
    public function SetPasswordHash(string $hash) : self { return $this->SetScalar('password',$hash); }
    
    /**
     * Encrypts a value using the account's crypto
     * @param string $data the plaintext to be encrypted
     * @param string $nonce the nonce to use for crypto
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the ciphertext encrypted with the account's secret key
     */
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();    
        
        $master = $this->GetScalar('master_key');
        return Crypto::EncryptSecret($data, $nonce, $master);
    }
    
    /**
     * Decrypts a value using the account's crypto
     * @param string $data the ciphertext to be decrypted
     * @param string $nonce the nonce used for encryption
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the plaintext decrypted with the account's key
     */
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();
        
        $master = $this->GetScalar('master_key');
        return Crypto::DecryptSecret($data, $nonce, $master);
    }

    /**
     * Gets a copy of the account's master key, encrypted
     * @param string $nonce the nonce to use for encryption
     * @param string $key the key to use for encryption
     * @throws CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the encrypted copy of the master key
     */
    public function GetEncryptedMasterKey(string $nonce, string $key) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();
        return Crypto::EncryptSecret($this->GetScalar('master_key'), $nonce, $key);
    }
    
    /**
     * Attempts to unlock crypto using the given password
     * @throws CryptoNotInitializedException if crypto does not exist
     * @throws DecryptionFailedException if decryption fails
     */
    public function UnlockCryptoFromPassword(string $password) : self
    {
        if ($this->cryptoAvailable) return $this;         
        else if (!$this->hasCrypto())
           throw new CryptoNotInitializedException();

        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $password_key = Crypto::DeriveKey($password, $master_salt, Crypto::SecretKeyLength());        // TODO commonize with KeySource
        $master = Crypto::DecryptSecret($master, $master_nonce, $password_key);
        
        $this->SetScalar('master_key', $master, true);
        
        $this->cryptoAvailable = true; return $this;
    }
    
    /**
     * Attempts to unlock crypto using the given unlocked key source
     * @throws CryptoNotInitializedException if crypto does not exist
     * @throws DecryptionFailedException if decryption fails
     * @return $this
     */
    public function UnlockCryptoFromKeySource(KeySource $source) : self
    {
        if ($this->cryptoAvailable) return $this;
        else if (!$this->hasCrypto())
            throw new CryptoNotInitializedException();
        
        $master = $source->GetUnlockedKey();
        
        $this->SetScalar('master_key', $master, true);
        
        $this->cryptoAvailable = true; return $this;
    }
    
    /**
     * Attempts to unlock crypto using a full recovery key
     * @throws CryptoNotInitializedException if crypto does not exist
     * @throws RecoveryKeyFailedException if the key is not valid
     * @throws DecryptionFailedException if decryption fails
     * @return $this
     */
    public function UnlockCryptoFromRecoveryKey(string $key) : self
    {
        if ($this->cryptoAvailable) return $this;
        else if (!$this->hasCrypto())
            throw new CryptoNotInitializedException();
        
        if (!$this->HasRecoveryKeys()) 
            throw new RecoveryKeyFailedException();
        
        $obj = RecoveryKey::TryLoadByFullKey($this->database, $key, $this);
        if ($obj === null) throw new RecoveryKeyFailedException();
        
        if (!$obj->CheckFullKey($key)) 
            throw new RecoveryKeyFailedException();
        
        return $this->UnlockCryptoFromKeySource($obj);
    }
    
    private static array $crypto_handlers = array();
    
    /** Registers a function to be run when crypto is enabled/disabled on the account */
    public static function RegisterCryptoHandler(callable $func){ self::$crypto_handlers[] = $func; }

    /**
     * Initializes secret-key crypto on the account
     * 
     * Accounts have a master-key for secret-key crypto. The master-key is generated randomly
     * and then wrapped using a key derived from the user's password and a nonce/salt.
     * Requests that require use of account crypto therefore must have the user's password
     * or some other key source material transmitted in each request.  The crypto is of course
     * done server-side, but the raw keys are only ever available in memory, not in the database.
     * @param string $password the password to derive keys from
     * @param bool $rekey true if crypto exists and we want to keep the same master key
     * @throws CryptoUnlockRequiredException if crypto is not unlocked
     * @throws CryptoAlreadyInitializedException if crypto already exists and not re-keying
     */
    public function InitializeCrypto(string $password, bool $rekey = false) : self
    {
        if ($rekey && !$this->cryptoAvailable) 
            throw new CryptoUnlockRequiredException();
        
        if (!$rekey && $this->hasCrypto())
            throw new CryptoAlreadyInitializedException();
        
        $master_salt = Crypto::GenerateSalt(); 
        $this->SetScalar('master_salt', $master_salt);
        
        $master_nonce = Crypto::GenerateSecretNonce(); 
        $this->SetScalar('master_nonce',  $master_nonce);   
        
        $password_key = Crypto::DeriveKey($password, $master_salt, Crypto::SecretKeyLength());
        
        $master = $rekey ? $this->GetScalar('master_key') : Crypto::GenerateSecretKey();
        $master_encrypted = Crypto::EncryptSecret($master, $master_nonce, $password_key);
  
        $this->SetScalar('master_key', $master_encrypted);         
        $this->SetScalar('master_key', $master, true); sodium_memzero($master);      
        
        $this->cryptoAvailable = true; 
        
        foreach ($this->GetTwoFactors() as $twofactor) $twofactor->InitializeCrypto();
        
        foreach (self::$crypto_handlers as $func) $func($this->database, $this, true);
        
        return $this;
    }
    
    /** Disables crypto on the account, stripping all keys */
    public function DestroyCrypto() : self
    {
        foreach (self::$crypto_handlers as $func) $func($this->database, $this, false);

        foreach ($this->GetSessions() as $session) $session->DestroyCrypto();
        foreach ($this->GetTwoFactors() as $twofactor) $twofactor->DestroyCrypto();
        foreach ($this->GetRecoveryKeys() as $recoverykey) $recoverykey->DestroyCrypto();
        
        $this->SetScalar('master_key', null);
        $this->SetScalar('master_salt', null);
        $this->SetScalar('master_nonce', null);
        
        $this->cryptoAvailable = false;
        return $this;
    }
    
    public function __destruct()
    {
        $this->scalars['master_key']->EraseValue();
    }
}

/** 
 * Trait that overrides some BaseObject functions to allow inheriting properties from Groups 
 * 
 * Classes using this trait must implement GetGroups()
 */
trait GroupInherit
{
    protected function GetScalar(string $field, bool $allowTemp = true)
    {
        if (array_key_exists($field, self::GetInheritedFields()))
            $value = $this->TryGetInheritable($field)->GetValue();
        else $value = parent::GetScalar($field, $allowTemp);
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetScalar(string $field, bool $allowTemp = true)
    {
        if (array_key_exists($field, self::GetInheritedFields()))
            return $this->TryGetInheritable($field)->GetValue();
        else return parent::TryGetScalar($field, $allowTemp);
    }
    
    protected function GetObject(string $field) : BaseObject
    {
        if (array_key_exists($field, self::GetInheritedFields()))
            $value = $this->TryGetInheritable($field, true)->GetValue();
        else $value = parent::GetObject($field);
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetObject(string $field) : ?BaseObject
    {
        if (array_key_exists($field, self::GetInheritedFields()))
            return $this->TryGetInheritable($field, true)->GetValue();
        else return parent::TryGetObject($field);
    }
    
    /** Returns the object that the value of the given field is inherited from */
    protected function TryGetInheritsScalarFrom(string $field) : ?BaseObject
    {
        return $this->TryGetInheritable($field)->GetSource();
    }
    
    /** Returns the object that the value of the given field is inherited from */
    protected function TryGetInheritsObjectFrom(string $field) : ?BaseObject
    {
        return $this->TryGetInheritable($field, true)->GetSource();
    }
    
    /**
     * Returns an inherited property value and source pair
     * 
     * Values can be inherited from this account, from any group it is 
     * a member of, or if using a default value, null
     * @param string $field the inherited property to find
     * @param bool $useobj true if this is an object reference, not a scalar
     * @return InheritedProperty value/source pair
     */
    protected function TryGetInheritable(string $field, bool $useobj = false) : InheritedProperty
    {
        if ($useobj) $value = parent::TryGetObject($field);
        else $value = parent::TryGetScalar($field);
        
        if ($value !== null) return new InheritedProperty($value, $this);
        
        $priority = null; $source = null;
        
        foreach ($this->GetGroups() as $group)
        {
            if ($useobj) $temp_value = $group->TryGetObject($field);
            else $temp_value = $group->TryGetScalar($field);
            
            $temp_priority = $group->GetPriority();
            
            if ($temp_value !== null && ($temp_priority > $priority || $priority === null))
            {
                $value = $temp_value; $source = $group;
                $priority = $temp_priority;
            }
        }
        
        $value ??= self::GetInheritedFields()[$field];
        
        return new InheritedProperty($value, $source);
    }
}

