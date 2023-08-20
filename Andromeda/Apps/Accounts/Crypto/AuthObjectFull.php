<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

require_once(ROOT."/Apps/Accounts/Crypto/AuthObject.php");

/** A trait for getting a serialized user key with both the ID and auth key */
trait AuthObjectFull
{
    use AuthObject;
    
    protected abstract static function GetFullKeyPrefix() : string;

    /**
     * Returns the object ID from a full serialized ID+key
     * @param string $code the full user serialized code
     * @return string the object ID the code is for or null if invalid
     */
    public static function TryGetIDFromFullKey(string $code) : ?string
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return null;
        
        return $code[1];
    }
    
    /** Checks the given full/serialized key for validity, returns result */
    public function CheckFullKey(string $code) : bool
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return false;
        
        return $this->CheckKeyMatch($code[2]);
    }
    
    /**
     * Gets the full serialized key value for the user
     *
     * The serialized string contains both the key ID and key value
     */
    protected function TryGetFullKey() : ?string
    {
        $key = $this->TryGetAuthKey();
        if ($key === null) return null;
        
        return implode(":",array(static::GetFullKeyPrefix(), $this->ID(), $key));
    }
}
