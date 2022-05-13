<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, KeyNotFoundException};

require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Authenticator.php");

/**
 * Trait allowing objects to store fields encrypted with an account's crypto
 * 
 * The encryption uses the owner account's secret-key crypto (only accessible by them)
 */
trait FieldCrypt
{    
    /** Returns the list of fields encrypted in this object */
    protected abstract static function getEncryptedFields() : array;
    
    /** Returns the account that owns this object */
    protected abstract function GetAccount() : ?Account;
    
    /** Returns all objects owned by the given account */
    public abstract static function LoadByAccount(ObjectDatabase $database, Account $account) : array;
    
    /** Gets the extra DB fields required for this trait */
    public static function GetFieldCryptFieldTemplate() : array
    {
        return array_fill_keys(array_map(function(string $field){ 
            return $field."_nonce"; }, static::getEncryptedFields()), null);
    }   
    
    /** Returns true if the given DB field is encrypted */
    protected function isFieldEncrypted($field) : bool { 
        return $this->TryGetScalar($field."_nonce") !== null; }
    
    /** Returns true if field crypto is unlockable */
    protected function isCryptoAvailable() : bool 
    {
        $account = $this->GetAccount();
        return ($account !== null && $account->CryptoAvailable());
    }
    
    /** Stores fields decrypted in memory */
    private array $crypto_cache = array();
    
    /**
     * Decrypts and returns the value of the given field
     * @param string $field field name
     * @throws KeyNotFoundException if the value is null
     * @return string decrypted value
     * @see FieldCrypt::TryGetEncryptedScalar()
     */
    protected function GetEncryptedScalar(string $field) : string
    {
        $value = $this->TryGetEncryptedScalar($field);
        if ($value !== null) return $value;
        else throw new KeyNotFoundException($field);
    }
    
    /** Unlocks account crypto for usage and returns it */
    protected function RequireCrypto() : Account
    {
        if (($account = $this->GetAccount()) === null)
            throw new CryptoInitRequiredException();

        Authenticator::RequireCryptoFor($account);
        
        return $account;
    }
    
    /**
     * Decrypts and returns the value of the given field
     * @param string $field field name
     * @return string|NULL decrypted value
     */
    protected function TryGetEncryptedScalar(string $field) : ?string
    {
        if (array_key_exists($field, $this->crypto_cache))
            return $this->crypto_cache[$field];
            
        $value = $this->TryGetScalar($field);
        
        if ($value !== null && $this->isFieldEncrypted($field))
        {
            $account = $this->RequireCrypto();
                
            $nonce = $this->GetScalar($field."_nonce");
            $value = $account->DecryptSecret($value, $nonce);
        }
        
        $this->crypto_cache[$field] = $value; return $value;
    }
    
    /**
     * Sets the value of the given field
     * @param string $field field to set
     * @param string $value value to set
     * @param bool $fieldcrypt if true, encrypt - default current state
     * @return $this
     */
    protected function SetEncryptedScalar(string $field, ?string $value, ?bool $fieldcrypt = null) : self
    {
        $this->crypto_cache[$field] = $value;
        
        $fieldcrypt ??= $this->isFieldEncrypted($field);

        $nonce = $fieldcrypt ? true : null;
        
        if ($value !== null && $fieldcrypt)
        {
            $account = $this->RequireCrypto();
        
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
        foreach (static::getEncryptedFields() as $field)
        {
            $value = $this->TryGetEncryptedScalar($field);
            $this->SetEncryptedScalar($field, $value, $crypt);
        }            
        
        return $this;
    }
      
    /**
     * Loads any objects for the given account and decrypts their fields
     * @param ObjectDatabase $database database reference
     * @param Account $account account to load by
     */
    public static function DecryptAccount(ObjectDatabase $database, Account $account) : void 
    { 
        foreach (static::LoadByAccount($database, $account) as $obj) $obj->SetEncrypted(false);
    }
}

/**
 * Trait for storage classes that store a optionally-encrypted credential fields
 *
 * The encryption uses the owner account's secret-key crypto (only accessible by them)
 */
trait OptFieldCrypt
{
    use FieldCrypt;
    
    /** Returns the command usage for Create() */
    public static function GetFieldCryptCreateUsage() : string { return "[--fieldcrypt bool]"; }
    
    /** Performs cred-crypt level initialization on a new storage */
    public function FieldCryptCreate(SafeParams $params) : self
    {
        $fieldcrypt = $params->GetOptParam('fieldcrypt',false)->GetBool();
        
        return $this->SetEncrypted($fieldcrypt);
    }
    
    /** Returns the command usage for Edit() */
    public static function GetFieldCryptEditUsage() : string { return "[--fieldcrypt bool]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function FieldCryptEdit(SafeParams $params) : self
    {
        if ($params->HasParam('fieldcrypt')) 
            $this->SetEncrypted($params->GetParam('fieldcrypt')->GetBool()); 
        
        return $this;
    }
    
    /**
     * Returns the printable client object of this trait
     * @return array fields mapped to `{field_iscrypt:bool}`
     */
    public function GetFieldCryptClientObject() : array
    {
        $retval = array();
        
        foreach (static::getEncryptedFields() as $field)
        {
            $retval[$field."_iscrypt"] = $this->isFieldEncrypted($field);
        }
        
        return $retval;
    }
}
