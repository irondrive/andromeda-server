<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Config.php");

class AuthObject extends StandardObject
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'authkey' => null
        ));
    }
    
    const KEY_LENGTH = 32;
    
    const SETTINGS = array('time_cost' => 1, 'memory_cost' => 1024);
    
    public function GetAuthKey(bool $asHash = false) : string {
        return $this->GetScalar('authkey', !$asHash);
    }
    
    protected function SetAuthKey(string $key) : self {
        $algo = Utilities::GetHashAlgo();
        $dohash = password_needs_rehash($this->GetAuthKey(true), $algo, self::SETTINGS);
        if ($dohash) $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        return $this->SetScalar('authkey', $key, true);
    }
    
    public function CreateAuthKey() : self {
        $algo = Utilities::GetHashAlgo();
        $key = Utilities::Random(self::KEY_LENGTH);
        $this->SetScalar('authkey', password_hash($key, $algo, self::SETTINGS));
        return $this->SetScalar('authkey', $key, true);        
    }
    
    public function CheckKeyMatch(string $key) : bool
    {
        $hash = $this->GetAuthKey(true);
        $correct = password_verify($key, $hash);
        if ($correct) $this->SetAuthKey($key);
        return $correct;
    }
}

class Client extends AuthObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'lastaddr' => null,
            'useragent' => null,
            'dates__loggedon' => null,            
            'dates__active' => new FieldTypes\Scalar(null, true),
            'account' => new FieldTypes\ObjectRef(Account::class, 'clients'),
            'session' => new FieldTypes\ObjectRef(Session::class, 'client', false)
        ));
    }
    
    public function GetLastAddress() : string { return $this->GetScalar('lastaddr'); }
    public function GetUserAgent() : string { return $this->GetScalar('useragent'); }
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetSession() : ?Session { return $this->TryGetObject('session'); }
    
    public function getActiveDate() : int       { return $this->GetDate('active'); }
    public function setActiveDate() : Client    { return $this->SetDate('active'); }
    public function getLoggedonDate() : int     { return $this->GetDate('loggedon'); }
    public function setLoggedonDate() : Client  { return $this->SetDate('loggedon'); }
    
    public static function Create(IOInterface $interface, ObjectDatabase $database, Account $account) : Client
    {
        $client = parent::BaseCreate($database);
        
        return $client->CreateAuthKey()
            ->SetScalar('lastaddr',$interface->GetAddress())
            ->SetScalar('useragent',$interface->GetUserAgent())
            ->SetObject('account',$account);
    }

    public function CheckAgentMatch(IOInterface $interface) : bool
    {
        $good = $interface->GetUserAgent() === $this->GetUserAgent();        
        if ($good) $this->SetScalar('lastaddr', $interface->GetAddress());        
        return $good;
    }
    
    public function CheckMatch(IOInterface $interface, string $key) : bool
    {
        $max = $this->GetAccount()->GetMaxClientAge();
        
        if ($max !== null && time()-$this->getActiveDate() > $max)
        {
            $this->Delete(); return false;
        }
        
        return $this->CheckAgentMatch($interface) && $this->CheckKeyMatch($key);
    }
    
    public function Delete() : void
    {
        if ($this->HasObject('session'))
            $this->DeleteObject('session'); 
        
        parent::Delete();
    }
    
    const OBJECT_METADATA = 0; const OBJECT_WITHSECRET = 1;
    
    public function GetClientObject(int $level = 0) : array
    {
        $data = array(
            'id' => $this->ID(),
            'lastaddr' => $this->GetLastAddress(),
            'useragent' => $this->GetUserAgent(),
            'dates' => $this->GetAllDates(),
        );     
        
        if ($level === self::OBJECT_WITHSECRET)
            $data['authkey'] = $this->GetAuthKey();
        
        if (($session = $this->GetSession()) === null) $data['session'] = null;
        else $data['session'] = $session->GetClientObject($level);

        return $data;        
    }
}
