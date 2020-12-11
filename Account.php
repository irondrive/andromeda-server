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

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoKey};
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class CryptoUnlockRequiredException extends Exceptions\ServerException { public $message = "CRYPTO_UNLOCK_REQUIRED"; }
class CryptoNotInitializedException extends Exceptions\ServerException { public $message = "CRYPTO_NOT_INITIALIZED"; }
class CryptoAlreadyInitializedException extends Exceptions\ServerException { public $message = "CRYPTO_ALREADY_INITIALIZED"; }
class RecoveryKeyFailedException extends Exceptions\ServerException { public $message = "RECOVERY_KEY_UNLOCK_FAILED"; }

use Andromeda\Core\Database\KeyNotFoundException;
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
            'dates__modified' => null,
            'max_session_age__inherits' => null,
            'max_password_age__inherits' => null,
            'features__admin__inherits' => null,
            'features__enabled__inherits' => null,
            'features__forcetf__inherits' => null,
            'features__allowcrypto__inherits' => null,
            'counters_limits__sessions__inherits' => null,
            'counters_limits__contactinfos__inherits' => null,
            'counters_limits__recoverykeys__inherits' => null,
            'authsource'    => new FieldTypes\ObjectPoly(Auth\External::class),
            'sessions'      => new FieldTypes\ObjectRefs(Session::class, 'account'),
            'contactinfos'  => new FieldTypes\ObjectRefs(ContactInfo::class, 'account'),
            'clients'       => new FieldTypes\ObjectRefs(Client::class, 'account'),
            'twofactors'    => new FieldTypes\ObjectRefs(TwoFactor::class, 'account'),
            'recoverykeys'  => new FieldTypes\ObjectRefs(RecoveryKey::class, 'account'),
            'groups'        => new FieldTypes\ObjectJoin(Group::class, 'accounts', GroupJoin::class)
        ));
    }
    
    public function GetInheritableDefault(string $field)
    {
        switch ($field)
        {
            case 'features__admin':       return false;
            case 'features__enabled':     return true;
            case 'features__forcetf':     return false;
            case 'features__allowcrypto': return true;
        }
        return null;
    }
    
    public function GetUsername() : string  { return $this->GetScalar('username'); }
    public function GetDisplayName() : string { return $this->TryGetScalar('fullname') ?? $this->GetUsername(); }
    public function SetFullName(string $data) : self { return $this->SetScalar('fullname',$data); }
    
    public function GetDefaultGroups() : array
    {
        $retval = array(Config::Load($this->database)->GetDefaultGroup());
        
        $authman = $this->GetAuthSource();
        if ($authman instanceof Auth\External) 
            array_push($retval, $authman->GetManager()->GetDefaultGroup());
        
        return array_filter($retval);
    }
    
    public function GetGroups() : array { return array_merge($this->GetDefaultGroups(), $this->GetMyGroups()); }
    
    public function GetMyGroups() : array { return $this->GetObjectRefs('groups'); }
    public function AddGroup(Group $group) : self { return $this->AddObjectRef('groups', $group); }
    public function RemoveGroup(Group $group) : self { return $this->RemoveObjectRef('groups', $group); }
    
    public function GetGroupAddedDate(Group $group) : ?int {
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
    public function CountClients() : int          { return $this->CountObjectRefs('clients'); }
    public function DeleteClients() : self      { $this->DeleteObjects('clients'); return $this; }
    
    public function GetSessions() : array       { return $this->GetObjectRefs('sessions'); }
    public function CountSessions() : int         { return $this->CountObjectRefs('sessions'); }
   
    public function GetContactInfos() : array   { return $this->GetObjectRefs('contactinfos'); }    
    public function CountContactInfos() : int     { return $this->CountObjectRefs('contactinfos'); }
    
    private function GetRecoveryKeys() : array  { return $this->GetObjectRefs('recoverykeys'); }
    public function HasRecoveryKeys() : bool    { return $this->CountObjectRefs('recoverykeys') > 0; }
    
    public function HasTwoFactor() : bool { return $this->CountObjectRefs('recoverykeys') > 0; }
    private function GetTwoFactors() : array    { return $this->GetObjectRefs('twofactors'); }
    
    public function ForceTwoFactor() : bool     { return $this->HasValidTwoFactor() && ($this->TryGetFeature('forcetf') ?? $this->GetInheritableDefault('features__forcetf')); }
    
    public function GetAllowCrypto() : bool     { return $this->TryGetFeature('allowcrypto') ?? $this->GetInheritableDefault('features__allowcrypto'); }
    
    public function isAdmin() : bool            { return $this->TryGetFeature('admin') ?? $this->GetInheritableDefault('features__admin'); }
    public function isEnabled() : bool          { return $this->TryGetFeature('enabled') ?? $this->GetInheritableDefault('features__enabled'); }
    
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
    
    public function GetMaxSessionAge() : ?int   { return $this->TryGetScalar('max_session_age') ?? $this->GetInheritableDefault('max_session_age'); }
    public function GetMaxPasswordAge() : ?int  { return $this->TryGetScalar('max_password_age') ?? $this->GetInheritableDefault('max_password_age'); }
    
    public static function SearchByFullName(ObjectDatabase $database, string $fullname) : array
    {
        $q = new QueryBuilder(); return parent::LoadByQuery($database, $q->Where($q->Like('fullname',$fullname)));
    }
    
    public static function TryLoadByUsername(ObjectDatabase $database, string $username) : ?self
    {
        return static::TryLoadByUniqueKey($database, 'username', $username);
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

                'features' => $this->GetAllFeatures(),
                'dates' => $this->GetAllDates(),
                'counters' => $this->GetAllCounters(),
                'limits' => $this->GetAllCounterLimits(),
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
            $data = array_merge($data, array(
                'twofactor' => $this->HasValidTwoFactor(),
                'comment' => $this->TryGetScalar('comment'),
                'groups' => array_map(function($e){ return $e->ID(); }, $this->GetGroups())
            ));
        }
        else
        {
            unset($data['counters']['groups']);
        }

        return $data;
    }
    
    protected function GetScalar(string $field, bool $allowTemp = true)
    {
        if ($this->ExistsScalar($field)) $value = parent::GetScalar($field, $allowTemp);
        else if ($this->ExistsScalar($field.'__inherits')) $value = $this->TryGetInheritable($field)->GetValue();
        else throw new KeyNotFoundException($field);
        
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetScalar(string $field, bool $allowTemp = true)
    {
        if ($this->ExistsScalar($field)) return parent::TryGetScalar($field, $allowTemp);
        else if ($this->ExistsScalar($field.'__inherits')) return $this->TryGetInheritable($field)->GetValue();
        else return null;
    }
    
    protected function SetScalar(string $field, $value, bool $temp = false) : self
    {
        if ($this->ExistsScalar($field.'__inherits')) $field .= "__inherits";
        return parent::SetScalar($field, $value, $temp);
    }
    
    protected function GetObject(string $field) : BaseObject
    {
        if ($this->ExistsObject($field)) $value = parent::GetObject($field);
        else if ($this->ExistsObject($field.'__inherits')) $value = $this->TryGetInheritable($field, true)->GetValue();
        else throw new KeyNotFoundException($field);
        
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetObject(string $field) : ?BaseObject
    {
        if ($this->ExistsObject($field)) return parent::TryGetObject($field);
        else if ($this->ExistsObject($field.'__inherits')) return $this->TryGetInheritable($field, true)->GetValue();
        else return null;
    }
    
    protected function SetObject(string $field, ?BaseObject $object, bool $notification = false) : self
    {
        if ($this->ExistsObject($field.'__inherits')) $field .= "__inherits";
        return parent::SetObject($field, $object, $notification);
    }
    
    protected function TryGetInheritsScalarFrom(string $field) : ?AuthEntity
    {
        return $this->TryGetInheritable($field)->GetSource();
    }
    
    protected function TryGetInheritsObjectFrom(string $field) : ?AuthEntity
    {
        return $this->TryGetInheritable($field, true)->GetSource();
    }

    protected function TryGetInheritable(string $field, bool $useobj = false) : InheritedProperty
    {
        if ($useobj) $value = parent::TryGetObject($field.'__inherits');
        else $value = parent::TryGetScalar($field.'__inherits');
        
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
        
        $value ??= $this->GetInheritableDefault($field);
        
        return new InheritedProperty($value, $source);
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
        
        if ($date < 0) return false; else return ($max === null || time()-$date < $max);
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
    
    private static $crypto_init_handlers = array();
    private static $crypto_delete_handlers = array();
    
    public static function RegisterCryptoInitHandler(callable $func){ array_push(static::$crypto_init_handlers,$func); }
    public static function RegisterCryptoDeleteHandler(callable $func){ array_push(static::$crypto_delete_handlers,$func); }

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
        
        foreach (static::$crypto_init_handlers as $func) $func($this->database, $this);
        
        return $this;
    }
    
    public function DestroyCrypto() : self
    {
        foreach (static::$crypto_delete_handlers as $func) $func($this->database, $this);

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

