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
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\BaseObject;
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
            'max_client_age__inherits' => null,
            'max_session_age__inherits' => null,
            'max_password_age__inherits' => null,
            'features__admin__inherits' => null,
            'features__enabled__inherits' => null,
            'features__forcetwofactor__inherits' => null,
            'authsource'    => new FieldTypes\ObjectPoly(Auth\ISource::class),
            'sessions'      => new FieldTypes\ObjectRefs(Session::class, 'account'),
            'contactinfos'  => new FieldTypes\ObjectRefs(ContactInfo::class, 'account'),
            'clients'       => new FieldTypes\ObjectRefs(Client::class, 'account'),
            'twofactors'    => new FieldTypes\ObjectRefs(TwoFactor::class, 'account'),
            'recoverykeys'  => new FieldTypes\ObjectRefs(RecoveryKey::class, 'account'),
            'groups'        => new FieldTypes\ObjectJoin(Group::class, 'accounts', GroupJoin::class)
        ));
    }
    
    public function GetUsername() : string  { return $this->GetScalar('username'); }
    public function GetFullName() : ?string { return $this->TryGetScalar('fullname'); }
    public function SetFullName(string $data) : self { return $this->SetScalar('fullname',$data); }

    public function GetGroups() : array { return $this->GetObjectRefs('groups'); }
    public function CountGroups() : int { return $this->CountObjectRefs('groups'); }
    
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
        else return Auth\Local::Load($this->database);
    }
    
    public function GetClients() : array        { return $this->GetObjectRefs('clients'); }
    public function HasClients() : int          { return $this->CountObjectRefs('clients') > 0; }
    public function DeleteClients() : self      { $this->DeleteObjects('clients'); return $this; }
    
    public function GetSessions() : array       { return $this->GetObjectRefs('sessions'); }
    public function HasSessions() : int         { return $this->CountObjectRefs('sessions') > 0; }
   
    public function GetContactInfos() : array   { return $this->GetObjectRefs('contactinfos'); }    
    public function HasContactInfos() : int     { return $this->TryCountObjectRefs('contactinfos'); }
    
    private function GetRecoveryKeys() : array  { return $this->GetObjectRefs('recoverykeys'); }
    public function HasRecoveryKeys() : bool    { return $this->TryCountObjectRefs('recoverykeys') > 0; }
    
    public function HasTwoFactor() : bool { return $this->TryCountObjectRefs('recoverykeys') > 0; }    
    
    public function HasValidTwoFactor() : bool
    {
        if ($this->TryCountObjectRefs('recoverykeys') <= 0) return false;
        
        foreach ($this->GetTwoFactors() as $twofactor) {
            if ($twofactor->GetIsValid()) return true; }
            return false;
    }    
    
    private function GetTwoFactors() : array    { return $this->GetObjectRefs('twofactors'); }
    public function ForceTwoFactor() : bool     { return ($this->TryGetFeature('forcetwofactor') ?? false) && $this->HasValidTwoFactor(); }
    
    public function isAdmin() : bool                    { return $this->TryGetFeature('admin') ?? false; }
    public function isEnabled() : bool                  { return $this->TryGetFeature('enabled') ?? true; }
    public function setEnabled(?bool $val) : self       { return $this->SetFeature('enabled', $val); }
    
    public function getUnlockCode() : ?string               { return $this->TryGetScalar('unlockcode'); }
    public function setUnlockCode(?string $code) : self     { return $this->SetScalar('unlockcode', $code); }
    
    public function getActiveDate() : int       { return $this->GetDate('active'); }
    public function setActiveDate() : self      { return $this->SetDate('active'); }
    public function getLoggedonDate() : int     { return $this->GetDate('loggedon'); }
    public function setLoggedonDate() : self    { return $this->SetDate('loggedon'); }
    public function getPasswordDate() : int     { return $this->GetDate('passwordset'); }
    private function setPasswordDate() : self   { return $this->SetDate('passwordset'); }
    
    public function GetMaxClientAge() : ?int    { return $this->TryGetScalar('max_client_age'); }
    public function GetMaxSessionAge() : ?int   { return $this->TryGetScalar('max_session_age'); }
    public function GetMaxPasswordAge() : ?int  { return $this->TryGetScalar('max_password_age'); }
    
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
    
    public function GetEmailRecipients(bool $redacted = false) : array
    {
        $name = $this->GetFullName();
        $emails = ContactInfo::GetEmails($this->GetContactInfos());
        
        return array_map(function($email) use($name,$redacted){
            if ($redacted) return ContactInfo::RedactEmail($email);
            else return new EmailRecipient($email, $name);
        }, $emails);
    }
    
    public function SendMailTo(Emailer $mailer, string $subject, string $message, ?EmailRecipient $from = null)
    {
        $recipients = $this->GetEmailRecipients();
        
        if (count($recipients) == 0) throw new EmailUnavailableException();
        
        $mailer->SendMail($subject, $message, $recipients, $from);
    }
    
    public static function Create(ObjectDatabase $database, Auth\ISource $source, string $username, string $password) : self
    {        
        $account = parent::BaseCreate($database); $config = Config::Load($database);
        
        $account->SetScalar('username',$username)->ChangePassword($password);
        
        if (!($source instanceof Auth\Local)) $account->SetObject('authsource',$source);

        $defaults = array_filter(array($config->GetDefaultGroup(), $source->GetAccountGroup()));
        foreach ($defaults as $group) $account->AddGroup($group);

        return $account;
    }
    
    private static $delete_handlers = array();
    
    public static function RegisterDeleteHandler(callable $func){ array_push(static::$delete_handlers,$func); }
    
    public function Delete() : void
    {
        foreach (static::$delete_handlers as $func) $func($this->database, $this);
        
        if ($this->HasSessions()) $this->DeleteObjectRefs('sessions'); 
        if ($this->HasClients()) $this->DeleteObjectRefs('clients');
        if ($this->HasTwoFactor()) $this->DeleteObjectRefs('twofactors');
        if ($this->HasContactInfos()) $this->DeleteObjectRefs('contactinfos');
        if ($this->HasRecoveryKeys()) $this->DeleteObjectRefs('recoverykeys');
        
        parent::Delete();
    }
    
    const OBJECT_SIMPLE = 0; const OBJECT_FULL = 1;     
    const OBJECT_USER = 0; const OBJECT_ADMIN = 2;
    
    public function GetClientObject(int $level = self::OBJECT_FULL | self::OBJECT_USER) : array
    {
        $mapobj = function($e) { return $e->GetClientObject(); };
        
        $data = array(
            'id' => $this->ID(),
            'username' => $this->GetUsername(),
            'fullname' => $this->GetFullName(),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
            'features' => $this->GetAllFeatures(),
            'timeout' => $this->GetMaxSessionAge(),
        );   
        
        if ($level & self::OBJECT_FULL)
        {
            $data['clients'] = array_map($mapobj, $this->GetClients());
            $data['twofactors'] = array_map($mapobj, $this->GetTwoFactors());
            $data['contactinfos'] = array_map($mapobj, $this->GetContactInfos());
        }
        
        if ($level & self::OBJECT_ADMIN)
        {
            $data['comment'] = $this->TryGetScalar('comment');            
            $data['groups'] = array_map(function($e){ return $e->ID(); }, $this->GetGroups());
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
        else if ($this->ExistsScalar($field."__inherits")) $value = $this->InheritableSearch($field)->GetValue();
        else throw new KeyNotFoundException($field);
        
        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetScalar(string $field, bool $allowTemp = true)
    {
        if ($this->ExistsScalar($field)) return parent::TryGetScalar($field, $allowTemp);
        else if ($this->ExistsScalar($field."__inherits")) return $this->InheritableSearch($field)->GetValue();
        else return null;
    }
    
    protected function SetScalar(string $field, $value, bool $temp = false) : self
    {  
        if ($this->ExistsScalar($field."__inherits"))
            $field .= "__inherits";
        return parent::SetScalar($field, $value, $temp);
    }
    
    protected function GetObject(string $field) : self
    {
        if ($this->ExistsObject($field)) $value = parent::GetObject($field);
        else if ($this->ExistsObject($field."__inherits")) $value = $this->InheritableSearch($field, true)->GetValue();
        else throw new KeyNotFoundException($field);

        if ($value !== null) return $value; else throw new NullValueException();
    }
    
    protected function TryGetObject(string $field) : ?self
    {
        if ($this->ExistsObject($field)) return parent::TryGetObject($field);
        else if ($this->ExistsObject($field."__inherits")) return $this->InheritableSearch($field, true)->GetValue();
        else return null;
    }
    
    protected function SetObject(string $field, ?BaseObject $object, bool $notification = false) : self
    {
        if ($this->ExistsObject($field."__inherits"))
            $field .= "__inherits";
        return parent::SetObject($field, $object, $notification);
    }
    
    protected function TryGetInheritsScalarFrom(string $field) : ?AuthEntity
    {
        return $this->InheritableSearch($field)->GetSource();
    }
    
    protected function TryGetInheritsObjectFrom(string $field) : ?AuthEntity
    {
        return $this->InheritableSearch($field, true)->GetSource();
    }
    
    private function InheritableSearch(string $field, bool $useobj = false) : InheritedProperty
    {
        if ($useobj) $value = parent::TryGetObject($field."__inherits");
        else $value = parent::TryGetScalar($field."__inherits");
        
        if ($value !== null) return new InheritedProperty($value, $this);
        
        $priority = null; $source = null;
        
        foreach ($this->GetGroups() as $group)
        {
            if ($useobj) $temp_value = $group->TryGetMembersObject($field);
            else $temp_value = $group->TryGetMembersScalar($field);
            
            $temp_priority = $group->GetPriority();
            
            if ($temp_value !== null && ($temp_priority > $priority || $priority == null))
            {
                $value = $temp_value; $source = $group; 
                $priority = $temp_priority;
            }
        }
        
        return new InheritedProperty($value, $source);
    }
    
    public function CheckTwoFactor(string $code) : bool
    {
        if (!$this->HasValidTwoFactor()) return false;  
        
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
        return $this->GetAuthSource()->VerifyPassword($this->GetUsername(), $password);
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
            $this->SetScalar('password', Auth\Local::HashPassword($new_password));

        return $this->setPasswordDate();
    }
    
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

