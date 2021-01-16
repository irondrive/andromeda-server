<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/ContactInfo.php");
require_once(ROOT."/apps/accounts/Client.php"); 
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/GroupStuff.php");
require_once(ROOT."/apps/accounts/KeySource.php");
require_once(ROOT."/apps/accounts/Session.php");
require_once(ROOT."/apps/accounts/RecoveryKey.php");

require_once(ROOT."/apps/accounts/auth/Local.php");

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoKey};
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CryptoUnlockRequiredException extends Exceptions\ServerException { public $message = "CRYPTO_UNLOCK_REQUIRED"; }
class CryptoNotInitializedException extends Exceptions\ServerException { public $message = "CRYPTO_NOT_INITIALIZED"; }
class CryptoAlreadyInitializedException extends Exceptions\ServerException { public $message = "CRYPTO_ALREADY_INITIALIZED"; }
class RecoveryKeyFailedException extends Exceptions\ServerException { public $message = "RECOVERY_KEY_UNLOCK_FAILED"; }

use Andromeda\Core\Database\NullValueException;
use Andromeda\Core\EmailUnavailableException;

class Account extends AuthEntity
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'username' => null, 
            'fullname' => null, 
            'unlockcode' => null,
            'comment' => null,
            'master_key' => null,
            'master_nonce' => null,
            'master_salt' => null,
            'password' => null,
            'dates__passwordset' => null,
            'dates__loggedon' => null,
            'dates__active' => new FieldTypes\Scalar(null, true),
            'authsource'    => new FieldTypes\ObjectPoly(Auth\External::class),
            'sessions'      => new FieldTypes\ObjectRefs(Session::class, 'account'),
            'contactinfos'  => new FieldTypes\ObjectRefs(ContactInfo::class, 'account'),
            'clients'       => new FieldTypes\ObjectRefs(Client::class, 'account'),
            'twofactors'    => new FieldTypes\ObjectRefs(TwoFactor::class, 'account'),
            'recoverykeys'  => new FieldTypes\ObjectRefs(RecoveryKey::class, 'account'),
            'groups'        => new FieldTypes\ObjectJoin(Group::class, 'accounts', GroupJoin::class)
        ));
    }
    
    use GroupInherit;
    
    protected static function GetInheritedFields() : array { return array(
        'max_session_age' => null,
        'max_password_age' => null,
        'features__admin' => false,
        'features__enabled' => true,
        'features__forcetf' => false,
        'features__allowcrypto' => true,
        'counters_limits__sessions' => null,
        'counters_limits__contactinfos' => null,
        'counters_limits__recoverykeys' => null
    ); }
    
    public function GetUsername() : string  { return $this->GetScalar('username'); }
    public function GetDisplayName() : string { return $this->TryGetScalar('fullname') ?? $this->GetUsername(); }
    public function SetFullName(string $data) : self { return $this->SetScalar('fullname',$data); }
    
    public function GetDefaultGroups() : array
    {
        $default = Config::GetInstance($this->database)->GetDefaultGroup();
        $retval[$default->ID()] = $default;
        
        $authman = $this->GetAuthSource();
        if ($authman instanceof Auth\External) 
        {
            $default = $authman->GetManager()->GetDefaultGroup();
            $retval[$default->ID()] = $default;
        }
        
        return array_filter($retval);
    }
    
    public function GetGroups() : array { return array_merge($this->GetDefaultGroups(), $this->GetMyGroups()); }
    
    public function GetMyGroups() : array            { return $this->GetObjectRefs('groups'); }
    public function AddGroup(Group $group) : self    { return $this->AddObjectRef('groups', $group); }
    public function RemoveGroup(Group $group) : self { return $this->RemoveObjectRef('groups', $group); }
    
    private static $group_handlers = array();
    
    public static function RegisterGroupChangeHandler(callable $func){ array_push(static::$group_handlers,$func); }

    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if ($field === 'groups') foreach (static::$group_handlers as $func) $func($this->database, $this, $object, true);
        
        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if ($field === 'groups') foreach (static::$group_handlers as $func) $func($this->database, $this, $object, false);
        
        return parent::RemoveObjectRef($field, $object, $notification);
    }
    
    public function GetGroupAddedDate(Group $group) : ?int 
    {
        $joinobj = $this->TryGetJoinObject('groups', $group);
        return ($joinobj !== null) ? $joinobj->GetDateCreated() : null;
    }

    public function GetAuthSource() : Auth\ISource
    { 
        $authsource = $this->TryGetObject('authsource');
        if ($authsource !== null) return $authsource;
        else return Auth\Local::GetInstance();
    }
    
    public function GetClients() : array        { return $this->GetObjectRefs('clients'); }
    public function CountClients() : int        { return $this->CountObjectRefs('clients'); }
    public function DeleteClients() : self      { $this->DeleteObjects('clients'); return $this; }
    
    public function GetSessions() : array       { return $this->GetObjectRefs('sessions'); }
    public function CountSessions() : int       { return $this->CountObjectRefs('sessions'); }

    public function GetContactInfos() : array   { return $this->GetObjectRefs('contactinfos'); }    
    public function CountContactInfos() : int   { return $this->CountObjectRefs('contactinfos'); }
    
    private function GetRecoveryKeys() : array  { return $this->GetObjectRefs('recoverykeys'); }
    public function HasRecoveryKeys() : bool    { return $this->CountObjectRefs('recoverykeys') > 0; }
    
    private function GetTwoFactors() : array    { return $this->GetObjectRefs('twofactors'); }
    public function HasTwoFactor() : bool       { return $this->CountObjectRefs('recoverykeys') > 0; }
    
    public function GetForceTwoFactor() : bool  { return $this->TryGetFeature('forcetf') ?? self::GetInheritedFields()['features__forcetf']; }    
    public function GetAllowCrypto() : bool     { return $this->TryGetFeature('allowcrypto') ?? self::GetInheritedFields()['features__allowcrypto']; }
    
    public function isAdmin() : bool            { return $this->TryGetFeature('admin') ?? self::GetInheritedFields()['features__admin']; }
    public function isEnabled() : bool          { return $this->TryGetFeature('enabled') ?? self::GetInheritedFields()['features__enabled']; }
    
    public function setAdmin(?bool $val) : self         { return $this->SetFeature('admin', $val); }
    public function setEnabled(?bool $val) : self       { return $this->SetFeature('enabled', $val); }
    
    public function getUnlockCode() : ?string           { return $this->TryGetScalar('unlockcode'); }
    public function setUnlockCode(?string $code) : self { return $this->SetScalar('unlockcode', $code); }
    
    public function getActiveDate() : int       { return $this->GetDate('active'); }
    public function setActiveDate() : self      { return $this->SetDate('active'); }
    public function getLoggedonDate() : int     { return $this->GetDate('loggedon'); }
    public function setLoggedonDate() : self    { return $this->SetDate('loggedon'); }
    
    public function getPasswordDate() : int     { return $this->GetDate('passwordset'); }
    private function setPasswordDate() : self   { return $this->SetDate('passwordset'); }
    public function resetPasswordDate() : self  { return $this->SetDate('passwordset', 0); }
    
    public function GetMaxSessionAge() : ?int   { return $this->TryGetScalar('max_session_age') ?? self::GetInheritedFields()['max_session_age']; }
    public function GetMaxPasswordAge() : ?int  { return $this->TryGetScalar('max_password_age') ?? self::GetInheritedFields()['max_password_age']; }
    
    public static function SearchByFullName(ObjectDatabase $database, string $fullname) : array
    {
        $q = new QueryBuilder(); return parent::LoadByQuery($database, $q->Where($q->Like('fullname',$fullname)));
    }
    
    public static function TryLoadByUsername(ObjectDatabase $database, string $username) : ?self
    {
        return static::TryLoadUniqueByKey($database, 'username', $username);
    }
    
    public static function TryLoadByContactInfo(ObjectDatabase $database, string $info) : ?self
    {
        $info = ContactInfo::TryLoadByInfo($database, $info);
        if ($info === null) return null; else return $info->GetAccount();
    }
    
    public static function LoadByAuthSource(ObjectDatabase $database, Auth\Manager $authman) : array
    {
        return parent::LoadByObject($database, 'authsource', $authman->GetAuthSource(), true);
    }
    
    public static function DeleteByAuthSource(ObjectDatabase $database, Auth\Manager $authman) : void
    {
        parent::DeleteByObject($database, 'authsource', $authman->GetAuthSource(), true);
    }
    
    public function GetEmailRecipients(bool $redacted = false) : array
    {
        $name = $this->GetDisplayName();
        $emails = ContactInfo::GetEmails($this->GetContactInfos());
        
        return array_map(function($email) use($name,$redacted){
            if ($redacted) return ContactInfo::RedactEmail($email);
            else return new EmailRecipient($email, $name);
        }, $emails);
    }
    
    public function GetMailTo() : array
    {
        $recipients = $this->GetEmailRecipients();        
        if (!count($recipients)) throw new EmailUnavailableException();
        return $recipients;
    }
    
    public function GetMailFrom() : ?EmailRecipient
    {
        // TODO have a notion of which contact info is preferred rather than just [0]
        
        $from = $this->GetEmailRecipients();
        return count($from) ? $from[0] : null;
    }
    
    public static function Create(ObjectDatabase $database, Auth\ISource $source, string $username, string $password) : self
    {        
        $account = parent::BaseCreate($database)->SetScalar('username',$username);
        
        if ($source instanceof Auth\External) 
            $account->SetObject('authsource',$source);
        else $account->ChangePassword($password);

        return $account;
    }
    
    private static $delete_handlers = array();
    
    public static function RegisterDeleteHandler(callable $func){ array_push(static::$delete_handlers,$func); }
    
    public function Delete() : void
    {
        foreach (static::$delete_handlers as $func) $func($this->database, $this);
        
        // preload these in one query (optimization)
        $this->GetSessions(); $this->GetClients();
        
        $this->DeleteObjectRefs('sessions');
        $this->DeleteObjectRefs('clients');
        $this->DeleteObjectRefs('twofactors');
        $this->DeleteObjectRefs('contactinfos');
        $this->DeleteObjectRefs('recoverykeys');
        
        parent::Delete();
    }
    
    const OBJECT_SIMPLE = 0; const OBJECT_USER = 1; const OBJECT_ADMIN = 2;
    
    public function GetClientObject(int $level = self::OBJECT_USER) : array
    {
        $mapobj = function($e) { return $e->GetClientObject(); };
        
        $data = array(
            'id' => $this->ID(),
            'username' => $this->GetUsername(),
            'dispname' => $this->GetDisplayName(),
        );   
        
        if ($level & self::OBJECT_USER || $level & self::OBJECT_ADMIN)
        {
            $data = array_merge($data, array(
                'dates' => $this->GetAllDates(),
                'counters' => $this->GetAllCounters(),
                'limits' => $this->GetAllCounterLimits(),
                'features' => $this->GetAllFeatures(),
                'max_session_age' => $this->GetMaxSessionAge(),
                'max_password_age' => $this->GetMaxPasswordAge()
            ));
        }
        
        if ($level & self::OBJECT_USER)
        {
            $data = array_merge($data, array(
                'contactinfos' => array_map($mapobj, $this->GetContactInfos()),
                'clients' => array_map($mapobj, $this->GetClients()),
                'twofactors' => array_map($mapobj, $this->GetTwoFactors()),
            ));
        }
        
        if ($level & self::OBJECT_ADMIN)
        {
            $getAuth = function($k){ return $this->TryGetInheritsScalarFrom($k); };
            
            $showAuth = function (?AuthEntity $e){ return $e ? array($e->ID() => Utilities::ShortClassName(get_class($e))) : null; };
            
            $data = array_merge($data, array(
                'twofactor' => $this->HasValidTwoFactor(),
                'comment' => $this->TryGetScalar('comment'),
                'groups' => array_keys($this->GetGroups()),
                'limits_from' => array_map($showAuth, $this->GetAllCounterLimits($getAuth)),
                'features_from' => array_map($showAuth, $this->GetAllFeatures($getAuth)),
                'max_session_age_from' => $showAuth($this->TryGetInheritsScalarFrom('max_session_age')),
                'max_password_age_from' => $showAuth($this->TryGetInheritsScalarFrom('max_password_age'))
            ));
        }
        else
        {
            unset($data['dates']['modified']);
            unset($data['counters']['refs_groups']);
        }

        return $data;
    }
    
    public function HasValidTwoFactor() : bool
    {
        if ($this->CountObjectRefs('recoverykeys') <= 0) return false;
        
        foreach ($this->GetTwoFactors() as $twofactor) {
            if ($twofactor->GetIsValid()) return true; }
        return false;
    }    
    
    public function CheckTwoFactor(string $code, bool $force = false) : bool
    {
        if (!$force && !$this->HasValidTwoFactor()) return false;  
        
        foreach ($this->GetTwoFactors() as $twofactor) { 
            if ($twofactor->CheckCode($code)) return true; }        
        return false;
    }
    
    public function CheckRecoveryKey(string $key) : bool
    {
        if (!$this->HasRecoveryKeys()) return false; 
        
        $obj = RecoveryKey::LoadByFullKey($this->database, $this, $key);

        if ($obj === null) return false;
        else return $obj->CheckFullKey($key);
    }
    
    public function VerifyPassword(string $password) : bool
    {
        return $this->GetAuthSource()->VerifyAccountPassword($this, $password);
    }    
    
    public function CheckPasswordAge() : bool
    {
        if (!($this->GetAuthSource() instanceof Auth\Local)) return true;
        
        $date = $this->getPasswordDate(); $max = $this->GetMaxPasswordAge();
        
        if ($date < 0) return false; else return 
            ($max === null || Main::GetInstance()->GetTime() - $date < $max);
    }
    
    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    
    private bool $cryptoAvailable = false; public function CryptoAvailable() : bool { return $this->cryptoAvailable; }
    
    public function ChangePassword(string $new_password) : Account
    {
        if ($this->hasCrypto())
        {
           $this->InitializeCrypto($new_password, true);
        }
        
        if ($this->GetAuthSource() instanceof Auth\Local)
            $this->SetPasswordHash(Auth\Local::HashPassword($new_password));

        return $this->setPasswordDate();
    }
    
    public function GetPasswordHash() : string { return $this->GetScalar('password'); }
    public function SetPasswordHash(string $hash) : self { return $this->SetScalar('password',$hash); }
    
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();    
        
        $master = $this->GetScalar('master_key');
        return CryptoSecret::Encrypt($data, $nonce, $master);
    }
    
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();
        
        $master = $this->GetScalar('master_key');
        return CryptoSecret::Decrypt($data, $nonce, $master);
    }

    public function GetEncryptedMasterKey(string $nonce, string $key) : string
    {
        if (!$this->cryptoAvailable) throw new CryptoUnlockRequiredException();
        return CryptoSecret::Encrypt($this->GetScalar('master_key'), $nonce, $key);
    }
    
    public function UnlockCryptoFromPassword(string $password) : self
    {
        if ($this->cryptoAvailable) return $this;         
        else if (!$this->hasCrypto())
           throw new CryptoNotInitializedException();

        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $password_key = CryptoKey::DeriveKey($password, $master_salt, CryptoSecret::KeyLength());        
        $master = CryptoSecret::Decrypt($master, $master_nonce, $password_key);
        
        $this->SetScalar('master_key', $master, true);
        
        $this->cryptoAvailable = true; return $this;
    }
    
    public function UnlockCryptoFromKeySource(KeySource $source) : self
    {
        if ($this->cryptoAvailable) return $this;
        else if (!$this->hasCrypto())
            throw new CryptoNotInitializedException();
        
        $master = $source->GetUnlockedKey();
        
        $this->SetScalar('master_key', $master, true);
        
        $this->cryptoAvailable = true; return $this;
    }
    
    public function UnlockCryptoFromRecoveryKey(string $key) : self
    {
        if ($this->cryptoAvailable) return $this;
        else if (!$this->hasCrypto())
            throw new CryptoNotInitializedException();
        
        if (!$this->HasRecoveryKeys()) throw new RecoveryKeyFailedException();
        
        $obj = RecoveryKey::LoadByFullKey($this->database, $this, $key);
        if ($obj === null) throw new RecoveryKeyFailedException();
        
        return $this->UnlockCryptoFromKeySource($obj, $key);
    }
    
    private static $crypto_handlers = array();
    
    public static function RegisterCryptoHandler(callable $func){ array_push(static::$crypto_handlers,$func); }

    public function InitializeCrypto(string $password, bool $rekey = false) : self
    {
        if ($rekey && !$this->cryptoAvailable) 
            throw new CryptoUnlockRequiredException();
        
        if (!$rekey && $this->hasCrypto())
            throw new CryptoAlreadyInitializedException();
        
        $master_salt = CryptoKey::GenerateSalt(); 
        $this->SetScalar('master_salt', $master_salt);
        
        $master_nonce = CryptoSecret::GenerateNonce(); 
        $this->SetScalar('master_nonce',  $master_nonce);   
        
        $password_key = CryptoKey::DeriveKey($password, $master_salt, CryptoSecret::KeyLength());
        
        $master = $rekey ? $this->GetScalar('master_key') : CryptoSecret::GenerateKey();
        $master_encrypted = CryptoSecret::Encrypt($master, $master_nonce, $password_key);
  
        $this->SetScalar('master_key', $master_encrypted);         
        $this->SetScalar('master_key', $master, true); sodium_memzero($master);      
        
        $this->cryptoAvailable = true; 
        
        foreach (static::$crypto_handlers as $func) $func($this->database, $this, true);
        
        return $this;
    }
    
    public function DestroyCrypto() : self
    {
        foreach (static::$crypto_handlers as $func) $func($this->database, $this, false);

        foreach ($this->GetSessions() as $session) $session->DestroyCrypto();
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
    
    protected function TryGetInheritsScalarFrom(string $field) : ?BaseObject
    {
        return $this->TryGetInheritable($field)->GetSource();
    }
    
    protected function TryGetInheritsObjectFrom(string $field) : ?BaseObject
    {
        return $this->TryGetInheritable($field, true)->GetSource();
    }
    
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
            
            if ($temp_value !== null && ($temp_priority > $priority || $priority == null))
            {
                $value = $temp_value; $source = $group;
                $priority = $temp_priority;
            }
        }
        
        $value ??= self::GetInheritedFields()[$field];
        
        return new InheritedProperty($value, $source);
    }
}

