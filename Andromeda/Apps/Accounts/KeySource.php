<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoKey};
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Account.php");

/** 
 * An object that holds an encrypted copy of an Account's master key 
 * 
 * Inherits from AuthObject, using the auth key as the key that wraps the master key.
 * This is used to provide methods of unlocking crypto in a request other than having the user's password.
 */
abstract class KeySource extends AuthObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'master_key' => null,
            'master_nonce' => null,
            'master_salt' => null
        ));
    }

    /** Returns true if this key source contains key material */
    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    
    /** Returns the account that owns this key source */
    public function GetAccount() : Account  { return $this->GetObject('account'); }
    
    /** Creates a new key source for the given account, initializing crypto if the account has it */
    public static function CreateKeySource(ObjectDatabase $database, Account $account) : self
    {
        $obj = parent::BaseCreate($database)->SetObject('account',$account);;
        
        return (!$account->hasCrypto()) ? $obj : $obj->InitializeCrypto();
    }
    
    /**
     * Initializes crypto, storing a copy of the account's master key
     * 
     * Crypto must be unlocked for the account to get a copy of the key
     * @throws CryptoAlreadyInitializedException if already initialized
     * @see Account::GetEncryptedMasterKey()
     * @return $this
     */
    public function InitializeCrypto() : self
    {
        if ($this->hasCrypto()) throw new CryptoAlreadyInitializedException();
        
        $master_salt = CryptoKey::GenerateSalt();
        $master_nonce = CryptoSecret::GenerateNonce();
        
        $key = CryptoKey::DeriveKey($this->GetAuthKey(), $master_salt, CryptoSecret::KeyLength(), true);
        $master_key = $this->GetAccount()->GetEncryptedMasterKey($master_nonce, $key);
        
        return $this
            ->SetScalar('master_salt', $master_salt)
            ->SetScalar('master_nonce', $master_nonce)
            ->SetScalar('master_key', $master_key);
    }

    /**
     * Returns the decrypted account master key
     * @throws CryptoNotInitializedException if no key material exists
     * @see AuthObject::GetAuthKey()
     */
    public function GetUnlockedKey() : string
    {
        if (!$this->hasCrypto()) throw new CryptoNotInitializedException();
        
        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $cryptokey = CryptoKey::DeriveKey($this->GetAuthKey(), $master_salt, CryptoSecret::KeyLength(), true);
        
        return CryptoSecret::Decrypt($master, $master_nonce, $cryptokey);
    }

    /** Erases all key material from the object */
    public function DestroyCrypto() : self
    {
        $this->SetScalar('master_key', null);
        $this->SetScalar('master_salt', null);
        $this->SetScalar('master_nonce', null);
        return $this;
    }    
    
    /**
     * Returns a printable client object
     * @param bool $secret if true, show the real key
     * @return array|NULL `{authkey:string}` if $secret, else null
     */
    public function GetClientObject(bool $secret = false) : array
    {
        return $secret ? array('authkey'=>$this->GetAuthKey()) : array();
    }
}

