<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class RawKeyNotAvailableException extends Exceptions\ServerException { public $message = "AUTHOBJECT_KEY_NOT_AVAILABLE"; }

abstract class AuthObject extends StandardObject
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'authkey' => null
        ));
    }
    
    const KEY_LENGTH = 32;
    
    const SETTINGS = array('time_cost' => 1, 'memory_cost' => 1024);
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        return self::LoadOneByQuery($database, $q->Where($w));
    }

    public static function BaseCreate(ObjectDatabase $database, bool $withKey = true) : self
    {
        $obj = parent::BaseCreate($database);
        if (!$withKey) return $obj;
        
        $key = Utilities::Random(self::KEY_LENGTH);
        return $obj->SetAuthKey($key, true);
    }
    
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->GetAuthKey(true);
        $correct = password_verify($key, $hash);
        if ($correct) $this->SetAuthKey($key);
        return $correct;
    }
    
    protected function GetAuthKey(bool $asHash = false) : string
    {
        if (!$asHash && !$this->haveKey) 
            throw new RawKeyNotAvailableException();
        return $this->GetScalar('authkey', !$asHash);
    }
    
    private $haveKey = false;
    
    protected function SetAuthKey(string $key, bool $forceHash = false) : self 
    {
        $this->haveKey = true; $algo = Utilities::GetHashAlgo(); 

        if ($forceHash || password_needs_rehash($this->GetAuthKey(true), $algo, self::SETTINGS)) 
        {
            $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        }
        
        return $this->SetScalar('authkey', $key, true);
    }
    
    public function GetClientObject(bool $secret = false) : array
    {
        return $secret ? array('authkey'=>$this->GetAuthKey()) : array();
    }
}
