<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\DecryptionFailedException;

/** An object that holds an encrypted copy of a crypto key */
trait KeySource
{
    /** The encrypted copy of the account master key */
    private FieldTypes\NullStringType $master_key;
    /** The nonce used to encrypt the master key */
    private FieldTypes\NullStringType $master_nonce;
    /** The salt used to encrypt the master key */
    private FieldTypes\NullStringType $master_salt;
    
    /** The decrypted master key, if in memory */
    private string $unlocked_key;
    
    protected function KeySourceCreateFields() : void
    {
        $fields = array();

        $this->master_key = $fields[] =   new FieldTypes\NullStringType('master_key');
        $this->master_nonce = $fields[] = new FieldTypes\NullStringType('master_nonce');
        $this->master_salt = $fields[] =  new FieldTypes\NullStringType('master_salt');
        
        $this->RegisterChildFields($fields);
    }
    
    /** Returns true if this key source contains key material */
    protected function hasCrypto() : bool { return $this->master_key->TryGetValue() !== null; }

    /**
     * Initializes crypto, storing an encrypted copy of the given key
     * @param string $key the key to be encrypted
     * @param string $wrapkey the key to use to encrypt
     * @throws CryptoAlreadyInitializedException if already initialized
     * @return $this
     */
    protected function InitializeCrypto(string $key, string $wrapkey) : self
    {
        if ($this->hasCrypto()) throw new CryptoAlreadyInitializedException();
        
        $this->unlocked_key = $key;
        
        $master_salt = Crypto::GenerateSalt();
        $master_nonce = Crypto::GenerateSecretNonce();
        $this->master_salt->SetValue($master_salt);
        $this->master_nonce->SetValue($master_nonce);
        
        $wrapkey = Crypto::DeriveKey($wrapkey, $master_salt, Crypto::SecretKeyLength(), true);
        $master_key = Crypto::EncryptSecret($key, $master_nonce, $wrapkey);
        $this->master_key->SetValue($master_key);
        
        return $this;
    }

    /**
     * Returns the decrypted key using the given key
     * @param string $wrapkey the key to use to decrypt
     * @throws CryptoNotInitializedException if no key material exists
     * @throws DecryptionFailedException if decryption fails
     */
    protected function TryGetUnlockedKey(string $wrapkey) : ?string
    {
        if (!$this->hasCrypto()) throw new CryptoNotInitializedException();
        
        $key = $this->master_key->TryGetValue();
        if ($key === null) return null;
        
        $master_salt = $this->master_salt->TryGetValue();
        $master_nonce = $this->master_nonce->TryGetValue();
        
        $wrapkey = Crypto::DeriveKey($wrapkey, $master_salt, Crypto::SecretKeyLength(), true);
        return $this->unlocked_key = Crypto::DecryptSecret($key, $master_nonce, $wrapkey);
    }

    /** Erases all key material from the object */
    protected function DestroyCrypto() : self
    {
        unset($this->unlocked_key);
        
        $this->master_key->SetValue(null);
        $this->master_salt->SetValue(null);
        $this->master_nonce->SetValue(null);
        
        return $this;
    }
}
