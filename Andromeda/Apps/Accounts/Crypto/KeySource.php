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
    private FieldTypes\NullStringType $ssenc_key;
    /** The nonce used to encrypt the master key */
    private FieldTypes\NullStringType $ssenc_nonce;
    /** The salt used to encrypt the master key */
    private FieldTypes\NullStringType $ssenc_salt;

    protected function KeySourceCreateFields() : void
    {
        $fields = array();

        $this->ssenc_key = $fields[] =   new FieldTypes\NullStringType('ssenc_key');
        $this->ssenc_nonce = $fields[] = new FieldTypes\NullStringType('ssenc_nonce');
        $this->ssenc_salt = $fields[] =  new FieldTypes\NullStringType('ssenc_salt');
        
        $this->RegisterChildFields($fields);
    }

    /** Returns true if server-side crypto is available on the source */
    public function hasCrypto() : bool { return $this->ssenc_key->TryGetValue() !== null; }
    
    /** The decrypted master key if available */
    private string $ssenc_rawkey;
    
    /** Returns true if crypto has been unlocked in this request and is available for operations */
    public function isCryptoAvailable() : bool { return isset($this->ssenc_rawkey); }

    public function GetMasterKey() : string
    { 
        if (!isset($this->ssenc_rawkey))
            throw new Exceptions\CryptoUnlockRequiredException();
        return $this->ssenc_rawkey;
    }

    /** Returns the crypto subkey of the given high-entropy key (fast) */
    protected function GetFastKey(string $wrapkey) : string
    {
        $superkey = Crypto::FastHash($wrapkey, Crypto::SuperKeyLength());
        // we derive a subkey so that classes can use both AuthObject and KeySource
        return Crypto::DeriveSubkey($superkey, 0, "a2keysrc", Crypto::SecretKeyLength());
    }

    /**
     * Initializes crypto with a new key, storing an encrypted copy of the given key
     * @param string $wrapkey the key to use to wrap the master key
     * @param bool $fast if true, does a very fast transformation (use only if the password is itself a key)
     * @param bool $rekey true if crypto exists and we want to keep the same master key
     * @throws Exceptions\CryptoUnlockRequiredException if crypto is not unlocked, and rekeying
     * @throws Exceptions\CryptoAlreadyInitializedException if crypto already exists and not re-keying
     * @return $this
     */
    protected function BaseInitializeCrypto(string $wrapkey, bool $fast = false, bool $rekey = false) : self
    {
        if (!$rekey && $this->hasCrypto())
            throw new Exceptions\CryptoAlreadyInitializedException();

        if ($rekey && !isset($this->ssenc_rawkey))
            throw new Exceptions\CryptoUnlockRequiredException();

        $ssenc_nonce = Crypto::GenerateSecretNonce(); 
        $this->ssenc_nonce->SetValue($ssenc_nonce);

        if ($fast) $wrapkey = $this->GetFastKey($wrapkey);
        else
        {
            $ssenc_salt = Crypto::GenerateSalt();
            $this->ssenc_salt->SetValue($ssenc_salt);
            $wrapkey = Crypto::DeriveKey($wrapkey, $ssenc_salt, Crypto::SecretKeyLength());
        }

        if (!$rekey) $this->ssenc_rawkey = Crypto::GenerateSecretKey();
        $ssenc_key = Crypto::EncryptSecret($this->ssenc_rawkey, $ssenc_nonce, $wrapkey);
        $this->ssenc_key->SetValue($ssenc_key);
        return $this;
    }

    /**
     * Attempts to unlock crypto using the given password
     * @param bool $fast if true, does a very fast transformation (use only if the password is itself a key)
     * @throws Exceptions\CryptoNotInitializedException if no key material exists
     * @throws DecryptionFailedException if decryption fails (password is wrong)
     * @return $this
     */
    protected function UnlockCrypto(string $wrapkey, bool $fast = false) : self
    {
        if (isset($this->ssenc_rawkey)) return $this; // already unlocked
        
        $key = $this->ssenc_key->TryGetValue();
        $ssenc_salt = $this->ssenc_salt->TryGetValue();
        $ssenc_nonce = $this->ssenc_nonce->TryGetValue();

        if ($key === null || $ssenc_nonce === null)
            throw new Exceptions\CryptoNotInitializedException();
        
        if ($fast) $wrapkey = $this->GetFastKey($wrapkey);
        else
        {
            if ($ssenc_salt === null)
                throw new Exceptions\CryptoNotInitializedException();
            $wrapkey = Crypto::DeriveKey($wrapkey, $ssenc_salt, Crypto::SecretKeyLength());
        }

        $this->ssenc_rawkey = Crypto::DecryptSecret($key, $ssenc_nonce, $wrapkey);
        return $this;
    }

    /** 
     * Re-locks crypto by trashing the unlocked key 
     * @return $this
     */
    protected function LockCrypto() : self
    {
        unset($this->ssenc_rawkey);
        return $this;
    }
    
    public function EncryptSecret(string $data, string $nonce) : string
    {
        if (!isset($this->ssenc_rawkey))
            throw new Exceptions\CryptoUnlockRequiredException();    
        
        return Crypto::EncryptSecret($data, $nonce, $this->ssenc_rawkey);
    }
    
    public function DecryptSecret(string $data, string $nonce) : string
    {
        if (!isset($this->ssenc_rawkey)) 
            throw new Exceptions\CryptoUnlockRequiredException();
        
        return Crypto::DecryptSecret($data, $nonce, $this->ssenc_rawkey);
    }

    /** 
     * Erases all key material from the object
     * @return $this
     */
    public function DestroyCrypto() : self
    {
        unset($this->ssenc_rawkey);
        $this->ssenc_key->SetValue(null);
        $this->ssenc_salt->SetValue(null);
        $this->ssenc_nonce->SetValue(null);   
        return $this;
    }
}
