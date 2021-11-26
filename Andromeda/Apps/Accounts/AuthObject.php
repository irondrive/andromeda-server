<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the raw (non-hashed) key does not exist in memory */
class RawKeyNotAvailableException extends Exceptions\ServerException { public $message = "AUTHOBJECT_KEY_NOT_AVAILABLE"; }

/** 
 * Represents an object that holds an authentication code that can be checked 
 * 
 * The key is stored as a hash and cannot be retrieved unless provided
 */
abstract class AuthObject extends StandardObject
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'authkey' => null
        ));
    }
    
    protected const KEY_LENGTH = 32; // ~160 bits
    
    /** a long random string doesn't need purposefully-slow hashing */
    private const SETTINGS = array('time_cost' => 1, 'memory_cost' => 1024);
    
    /**
     * Attemps to load the object with the given account and ID
     * @param ObjectDatabase $database database reference
     * @param Account $account account that owns this object
     * @param string $id the ID of this object
     * @return self|NULL loaded object or null if not found
     */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }

    /**
     * Creates a new auth object
     * @param ObjectDatabase $database database reference
     * @param bool $withKey if true, create an auth key
     * @return $this
     */
    public static function BaseCreate(ObjectDatabase $database, bool $withKey = true) : self
    {
        $obj = parent::BaseCreate($database);
        if (!$withKey) return $obj;
        
        $key = Utilities::Random(static::KEY_LENGTH);
        return $obj->ChangeAuthKey($key);
    }
    
    /** Returns true if the given key is valid, and stores it in memory for GetAuthKey() */
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->GetAuthKey(true);
        $correct = password_verify($key, $hash);
        
        if ($correct)
        {
            $this->haveKey = true; $algo = Utilities::GetHashAlgo();
            
            if (password_needs_rehash($this->GetAuthKey(true), $algo, self::SETTINGS))
            {
                $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
            }
            
            $this->SetScalar('authkey', $key, true);
        }
        
        return $correct;
    }
    
    /**
     * Returns the auth key or auth key hash
     * @param bool $asHash if false, get the real key not the hash
     * @throws RawKeyNotAvailableException if the real key is not in memory
     */
    public function GetAuthKey(bool $asHash = false) : string
    {
        if (!$asHash && !$this->haveKey) 
            throw new RawKeyNotAvailableException();
        return $this->GetScalar('authkey', !$asHash);
    }
    
    private $haveKey = false;
    
    /** Sets the auth key to a new random value */
    protected function InitAuthKey() : self
    {
        return $this->ChangeAuthKey(Utilities::Random(static::KEY_LENGTH));
    }
    
    /**
     * Sets the auth key to the given value and hashes it
     * @param string $key new auth key
     * @return $this
     */
    protected function ChangeAuthKey(?string $key) : self 
    {
        $this->haveKey = true; $algo = Utilities::GetHashAlgo(); 
        
        if ($key === null) return $this->SetScalar('authkey', null);

        $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        
        return $this->SetScalar('authkey', $key, true);
    }
}

/** A trait for getting a serialized user key with both the ID and auth key */
trait FullAuthKey
{    
    /**
     * Tries to load an AuthObject by the full serialized key - DOES NOT CheckFullKey()!
     * @param ObjectDatabase $database database reference
     * @param string $code the full user/serialized code
     * @param Account $account the owner of the authObject or null for any
     * @return self|NULL loaded object or null if not found
     */
    public static function TryLoadByFullKey(ObjectDatabase $database, string $code, ?Account $account = null) : ?self
    {
        $code = explode(":", $code, 3);
        
        if (count($code) !== 3 || $code[0] !== static::GetFullKeyPrefix()) return null;
        
        $q = new QueryBuilder(); $w = $q->Equals('id',$code[1]); 
        
        if ($account !== null) $w = $q->And($w,$q->Equals('account',$account->ID()));

        return static::TryLoadUniqueByQuery($database, $q->Where($w));
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
    public function GetFullKey() : string
    {
        return implode(":",array(static::GetFullKeyPrefix(),$this->ID(),$this->GetAuthKey()));
    }    
}


