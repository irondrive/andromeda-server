<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

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
    
    private const KEY_LENGTH = 32;
    
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
        
        $key = Utilities::Random(self::KEY_LENGTH);
        return $obj->SetAuthKey($key, true);
    }
    
    /** Returns true if the given key is valid, and stores it in memory for GetAuthKey() */
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->GetAuthKey(true);
        $correct = password_verify($key, $hash);
        if ($correct) $this->SetAuthKey($key);
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
    
    /**
     * Sets the auth key to the given value (places it in memory)
     * @param string $key new auth key
     * @param bool $forceHash if true, update the stored hash
     * @return $this
     */
    protected function SetAuthKey(string $key, bool $forceHash = false) : self 
    {
        $this->haveKey = true; $algo = Utilities::GetHashAlgo(); 

        if ($forceHash || password_needs_rehash($this->GetAuthKey(true), $algo, self::SETTINGS)) 
        {
            $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        }
        
        return $this->SetScalar('authkey', $key, true);
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
