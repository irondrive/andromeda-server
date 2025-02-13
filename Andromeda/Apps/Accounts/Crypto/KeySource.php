<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\FieldTypes;

use Andromeda\Core\Exceptions\DecryptionFailedException;

interface IKeySource
{
    /** 
     * Returns the key source's master key - DO NOT use directly 
     * @throws Exceptions\CryptoUnlockRequiredException if crypto has not been unlocked
     */
    public function GetMasterKey() : string;

    /**
     * Encrypts a value using the source's crypto
     * @param string $data the plaintext to be encrypted
     * @param string $nonce the nonce to use for crypto
     * @throws Exceptions\CryptoUnlockRequiredException if crypto has not been unlocked
     * @return string the ciphertext encrypted with the source's secret key
     */
    public function EncryptSecret(string $data, string $nonce) : string;
    
    /**
     * Decrypts a value using the source's crypto
     * @param string $data the ciphertext to be decrypted
     * @param string $nonce the nonce used for encryption
     * @throws Exceptions\CryptoUnlockRequiredException if crypto has not been unlocked
     * @throws DecryptionFailedException if the key is wrong for the data
     * @return string the plaintext decrypted with the source's key
     */
    public function DecryptSecret(string $data, string $nonce) : string;
}

/** An object that holds an encrypted copy of a crypto key */
trait KeySource
{
    /** The encrypted copy of the source master key */
    private FieldTypes\NullStringType $master_key;
    /** The nonce used to encrypt the master key */
    private FieldTypes\NullStringType $master_nonce;
    /** The salt used to encrypt the master key */
    private FieldTypes\NullStringType $master_salt;

    protected function KeySourceCreateFields() : void
    {
        $fields = array();

        $this->master_key = $fields[] =   new FieldTypes\NullStringType('master_key');
        $this->master_nonce = $fields[] = new FieldTypes\NullStringType('master_nonce');
        $this->master_salt = $fields[] =  new FieldTypes\NullStringType('master_salt');
        
        $this->RegisterChildFields($fields);
    }

    /** Returns true if server-side crypto is available on the source */
    public function hasCrypto() : bool { return $this->master_key->TryGetValue() !== null; }
    
    /** The decrypted master key if available */
    private string $master_raw;
    
    /** Returns true if crypto has been unlocked in this request and is available for operations */
    public function isCryptoAvailable() : bool { return isset($this->master_raw); }

    public function GetMasterKey() : string
    { 
        if (!isset($this->master_raw))
            throw new Exceptions\CryptoUnlockRequiredException();
        return $this->master_raw;
    }
    
    /**
     * Initializes crypto with a new key, storing an encrypted copy of the given key
     * @param string $wrappass the key to use to wrap the master key
     * @param bool $fast if true, does a very fast transformation (use only if the password is itself a key)
     * @param bool $rekey true if crypto exists and we want to keep the same master key
     * @throws Exceptions\CryptoUnlockRequiredException if crypto is not unlocked, and rekeying
     * @throws Exceptions\CryptoAlreadyInitializedException if crypto already exists and not re-keying
     * @return $this
     */
    protected function BaseInitializeCrypto(string $wrappass, bool $fast = false, bool $rekey = false) : self
    {
        if (!$rekey && $this->hasCrypto())
            throw new Exceptions\CryptoAlreadyInitializedException();

        if ($rekey && !isset($this->master_raw))
            throw new Exceptions\CryptoUnlockRequiredException();

        $master_salt = Crypto::GenerateSalt();
        $master_nonce = Crypto::GenerateSecretNonce();
        $this->master_salt->SetValue($master_salt);
        $this->master_nonce->SetValue($master_nonce);
        
        $wrapkey = Crypto::DeriveKey($wrappass, $master_salt, Crypto::SecretKeyLength(), $fast);
        $this->master_raw = $rekey ? $this->master_raw : Crypto::GenerateSecretKey();
        $master_key = Crypto::EncryptSecret($this->master_raw, $master_nonce, $wrapkey);
        $this->master_key->SetValue($master_key);
        
        return $this;
    }

    /**
     * Attempts to unlock crypto using the given password
     * @param bool $fast if true, does a very fast transformation (use only if the password is itself a key)
     * @throws Exceptions\CryptoNotInitializedException if no key material exists
     * @throws DecryptionFailedException if decryption fails (password is wrong)
     * @return $this
     */
    protected function UnlockCrypto(string $wrappass, bool $fast = false) : self
    {
        if (isset($this->master_raw)) return $this; // already unlocked
        
        $key = $this->master_key->TryGetValue();
        $master_salt = $this->master_salt->TryGetValue();
        $master_nonce = $this->master_nonce->TryGetValue();

        if ($key === null || $master_salt === null || $master_nonce === null)
            throw new Exceptions\CryptoNotInitializedException();
        
        $wrapkey = Crypto::DeriveKey($wrappass, $master_salt, Crypto::SecretKeyLength(), $fast);
        $this->master_raw = Crypto::DecryptSecret($key, $master_nonce, $wrapkey);

        return $this;
    }

    /** 
     * Re-locks crypto by trashing the unlocked key 
     * @return $this
     */
    protected function LockCrypto() : self
    {
        unset($this->master_raw);
        return $this;
    }
    
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (!isset($this->master_raw))
            throw new Exceptions\CryptoUnlockRequiredException();    
        
        return Crypto::EncryptSecret($data, $nonce, $this->master_raw);
    }
    
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (!isset($this->master_raw)) 
            throw new Exceptions\CryptoUnlockRequiredException();
        
        return Crypto::DecryptSecret($data, $nonce, $this->master_raw);
    }

    /** 
     * Erases all key material from the object
     * @return $this
     */
    public function DestroyCrypto() : self
    {
        unset($this->master_raw);
        $this->master_key->SetValue(null);
        $this->master_salt->SetValue(null);
        $this->master_nonce->SetValue(null);   
        return $this;
    }
}
