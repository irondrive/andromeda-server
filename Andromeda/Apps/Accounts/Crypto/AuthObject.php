<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\{Crypto, Utilities};
use Andromeda\Core\Database\FieldTypes;

/** 
 * Represents an object that holds an authentication code that can be checked 
 * 
 * The key is stored as a hash and cannot be retrieved unless provided
 * This is ONLY for hashing generated keys, not low-entropy passwords
 */
trait AuthObject
{
    /** 
     * Return the string length of the auth key
     * @return positive-int
     */
    protected static function GetKeyLength() : int { return 32; }
    
    /** The hashed auth key stored in DB */
    private FieldTypes\NullStringType $authkey;
    
    /** The actual auth key if in memory */
    private string $authkey_raw;
    
    protected function AuthObjectCreateFields() : void
    {
        $fields = array();
        
        $this->authkey = $fields[] = new FieldTypes\NullStringType('authkey');

        $this->RegisterChildFields($fields);
    }

    /** Returns the auth subkey of the given high-entropy key to store in the DB (fast) */
    protected function GetFastHash(string $key) : string
    {
        $superkey = Crypto::FastHash($key, Crypto::SuperKeyLength());
        $hashlen = max(Crypto::SubkeySizeRange()[0], static::GetKeyLength());
        return Crypto::DeriveSubkey($superkey, 1, "a2authob", $hashlen);
    }

    /** Returns true if the given key is valid, and stores it in memory for TryGetAuthKey() */
    public function CheckKeyMatch(string $key) : bool
    {
        $goodhash = $this->authkey->TryGetValue();
        if ($goodhash === null || strlen($goodhash) === 0) return false;

        $inhash = $this->GetFastHash($key);
        if (sodium_memcmp($goodhash, $inhash) !== 0) return false;

        $this->authkey_raw = $key;
        return true;
    }

    /**
     * Returns the raw auth key if available or null if none
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory
     */
    protected function TryGetAuthKey() : ?string
    {
        if ($this->authkey->TryGetValue() === null)
            return null; // no key/hash
        return $this->GetAuthKey();
    }

    /**
     * Returns the raw auth key 
     * @throws Exceptions\RawKeyNotAvailableException if the real key is not in memory or is null
     */
    protected function GetAuthKey() : string
    {
        if (!isset($this->authkey_raw))
            throw new Exceptions\RawKeyNotAvailableException();
        return $this->authkey_raw;
    }

    /** 
     * Sets the auth key to a new random value
     * @return string the new auth key
     */
    protected function InitAuthKey() : string
    {
        $key = random_bytes(static::GetKeyLength());
        $this->SetAuthKey($key);
        return $key;
    }
    
    /**
     * Sets the auth key to the given value and hashes it
     * @param string $key new auth key
     * @return $this
     */
    protected function SetAuthKey(?string $key) : self 
    {
        if ($key === null)
        {
            unset($this->authkey_raw);
            $hash = null;
        }
        else
        {
            $hash = $this->GetFastHash($key);
            $this->authkey_raw = $key;
        }
        
        $this->authkey->SetValue($hash);
        return $this;
    }
}
