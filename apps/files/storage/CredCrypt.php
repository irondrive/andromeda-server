<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, KeyNotFoundException};
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

use Andromeda\Apps\Files\FilesApp;

/** Exception indicating that crypto has not been unlocked on the requied account */
class CredentialsEncryptedException extends Exceptions\ClientErrorException { public $message = "STORAGE_CREDENTIALS_ENCRYPTED"; }

/** Exception indicating that crypto has not been initialized on the required account */
class CryptoNotAvailableException extends Exceptions\ClientErrorException { public $message = "ACCOUNT_CRYPTO_NOT_AVAILABLE"; }

/** Exception indicating that crypto cannot be used without an account given */
class CryptoWithoutOwnerException extends Exceptions\ClientErrorException { public $message = "CANNOT_CREATE_CRYPTO_WITHOUT_OWNER"; }

/**
 * Trait for storage classes that store a possibly-encrypted username and password
 * 
 * The encryption uses the owner account's secret-key crypto (only accessible by them)
 */
trait CredCrypt
{
    /** Gets the extra DB fields required for this trait */
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'username' => null,
            'password' => null,
            'username_nonce' => null,
            'password_nonce' => null,
        ));
    }
    
    /** 
     * Returns the printable client object of this trait
     * @return array `{username:?string, password:bool}`
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'username' => $this->TryGetUsername(),
            'password' => boolval($this->TryGetPassword()),
        ));
    }
    
    /** Returns the command usage for Create() */
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." [--username ?alphanum] [--password ?raw] [--credcrypt bool]"; }

    /** Performs cred-crypt level initialization on a new storage */
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $credcrypt = $input->GetOptParam('credcrypt', SafeParam::TYPE_BOOL) ?? false;
        if ($filesystem->GetOwner() === null && $credcrypt) throw new CryptoWithoutOwnerException();
        
        return parent::Create($database, $input, $filesystem)
                   ->SetPassword($input->GetNullParam('password', SafeParam::TYPE_RAW), $credcrypt)
                   ->SetUsername($input->GetNullParam('username', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(255)), $credcrypt);
    }
    
    /** Returns the command usage for Edit() */
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--username alphanum] [--password raw] [--credcrypt bool]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function Edit(Input $input) : self 
    { 
        $credcrypt = $input->GetOptParam('credcrypt', SafeParam::TYPE_BOOL);
        if ($credcrypt !== null) $this->SetEncrypted($credcrypt);
        
        if ($input->HasParam('password')) $this->SetPassword($input->GetNullParam('password', SafeParam::TYPE_RAW), $credcrypt);
        if ($input->HasParam('username')) $this->SetUsername($input->GetNullParam('username', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(255)), $credcrypt);
                
        return parent::Edit($input);
    }
    
    /** Returns the decrypted username */
    protected function TryGetUsername() : ?string { return $this->TryGetEncryptedScalar('username'); }
    
    /** Returns the decrypted password */
    protected function TryGetPassword() : ?string { return $this->TryGetEncryptedScalar('password'); }
    
    /**
     * Sets the stored username
     * @param ?string $username username
     * @param bool $credcrypt if true, encrypt
     * @return $this
     */
    protected function SetUsername(?string $username, bool $credcrypt) : self { return $this->SetEncryptedScalar('username',$username,$credcrypt); }
    
    /**
     * Sets the stored password
     * @param ?string $password password
     * @param bool $credcrypt if true, encrypt
     * @return $this
     */
    protected function SetPassword(?string $password, bool $credcrypt) : self { return $this->SetEncryptedScalar('password',$password,$credcrypt); }

    /** Returns true if the given DB field is encrypted */
    protected function hasCryptoField($field) : bool { return $this->TryGetScalar($field."_nonce") !== null; }
    
    /** Stores fields decrypted in memory */
    private array $crypto_cache = array();
    
    /**
     * Decrypts and returns the value of the given field
     * @param string $field field name
     * @throws KeyNotFoundException if the value is null
     * @return string decrypted value
     * @see CredCrypt::TryGetEncryptedScalar()
     */
    protected function GetEncryptedScalar(string $field) : string
    {
        $value = $this->TryGetEncryptedScalar($field);
        if ($value !== null) return $value;
        else throw new KeyNotFoundException($field);
    }
    
    /**
     * Decrypts and returns the value of the given field
     * @param string $field field name
     * @throws CredentialsEncryptedException if account crypto is not unlocked
     * @return string|NULL decrypted value
     */
    protected function TryGetEncryptedScalar(string $field) : ?string
    {
        if (array_key_exists($field, $this->crypto_cache))
            return $this->crypto_cache[$field];
            
        $account = $this->GetAccount();
        $value = $this->TryGetScalar($field);
        if ($value !== null && $this->hasCryptoField($field))
        {
            if (!$account->CryptoAvailable())
                FilesApp::needsCrypto();   
            
            if (!$account->CryptoAvailable())
                throw new CredentialsEncryptedException();
                
            $nonce = $this->GetScalar($field."_nonce");
            $value = $account->DecryptSecret($value, $nonce);
        }
        
        $this->crypto_cache[$field] = $value; return $value;
    }
    
    /**
     * Sets the value of the given field
     * @param string $field field to set
     * @param string $value value to set
     * @param bool $credcrypt if true, encrypt
     * @throws CryptoNotAvailableException if account crypto is not unlocked
     * @return $this
     */
    protected function SetEncryptedScalar(string $field, ?string $value, bool $credcrypt) : self
    {
        $this->crypto_cache[$field] = $value;

        $account = $this->GetAccount(); $nonce = null;
        if ($value !== null && $credcrypt)
        {
            if (!$account->hasCrypto())
                throw new CryptoNotAvailableException();
            
            if (!$account->CryptoAvailable())
                FilesApp::needsCrypto();
        
            if (!$account->CryptoAvailable())
                throw new CredentialsEncryptedException();
        
            $nonce = CryptoSecret::GenerateNonce();
            $value = $account->EncryptSecret($value, $nonce);            
        }
        $this->SetScalar($field."_nonce", $nonce);
        return $this->SetScalar($field,$value);
    }
    
    /**
     * Sets the crypto state of all stored fields
     * @param bool $crypt true to encrypted, false if not
     * @return $this
     */
    protected function SetEncrypted(bool $crypt) : self
    {
        $this->SetUsername($this->TryGetUsername(), $crypt);
        $this->SetPassword($this->TryGetPassword(), $crypt);        
        return $this;
    }
      
    /**
     * Loads any storages for the given account and decrypts their fields
     * @param ObjectDatabase $database database reference
     * @param Account $account account to load by
     */
    public static function DecryptAccount(ObjectDatabase $database, Account $account) : void 
    { 
        $q = new QueryBuilder();
        
        $q->Join($database, FSManager::class, 'id', static::class, 'filesystem');
        $w = $q->Equals($database->GetClassTableName(FSManager::class).'.owner', $account->ID());
        
        $storages = static::LoadByQuery($database, $q->Where($w));
        
        foreach ($storages as $storage) $storage->SetEncrypted(false);
    }
}
