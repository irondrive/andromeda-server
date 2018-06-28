<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\{StandardObject, ClientObject};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Config.php");

class Client extends StandardObject implements ClientObject
{
    public function GetKey() : string { return $this->GetScalar('authkey'); }
    public function GetLastAddress() : string { return $this->GetScalar('lastaddr'); }
    public function GetUserAgent() : string { return $this->GetScalar('useragent'); }
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetSession() : ?Session { return $this->TryGetObject('session'); }
    
    public function getActiveDate() : int       { return $this->GetDate('active'); }
    public function setActiveDate() : Client    { return $this->SetDate('active'); }
    public function getLoggedonDate() : int     { return $this->GetDate('loggedon'); }
    public function setLoggedonDate() : Client  { return $this->SetDate('loggedon'); }
    
    const KEY_LENGTH = 32;
    
    public static function Create(IOInterface $interface, ObjectDatabase $database, Account $account) : Client
    {
        $client = parent::BaseCreate($database);
        
        return $client
            ->SetScalar('authkey',Utilities::Random(self::KEY_LENGTH))
            ->SetScalar('lastaddr',$interface->getAddress())
            ->SetScalar('useragent',$interface->getUserAgent())
            ->SetObject('account',$account);
    }
    
    public function CheckAgentMatch(IOInterface $interface) : bool
    {
        $good = $interface->getUserAgent() === $this->GetUserAgent();        
        if ($good) $this->SetScalar('lastaddr', $interface->getAddress());        
        return $good;
    }
    
    public function CheckKeyMatch(string $key) : bool
    {
        $max = $this->GetAccount()->GetMaxClientAge();  
        
        if ($max !== null && time()-$this->getActiveDate() > $max) 
        { 
            $this->Delete(); return false; 
        }
        
        else return strlen($key) > 0 && $this->GetKey() === $key;
    }
    
    public function CheckMatch(IOInterface $interface, string $key) : bool
    {
        return $this->CheckAgentMatch($interface) && $this->CheckKeyMatch($key);
    }
    
    public function Delete() : void
    {
        $session = $this->GetSession();
        if ($session !== null) $session->Delete();
        
        parent::Delete();
    }
    
    public function GetClientObject(int $level = 0) : array
    {
        $data = array(
            'id' => $this->ID(),
            'lastaddr' => $this->GetLastAddress(),
            'useragent' => $this->GetUserAgent(),
            'dates' => $this->GetAllDates(),
        );     
        
        if (($session = $this->GetSession()) === null) $data['session'] = null;
        else $data['session'] = $session->GetClientObject();

        return $data;        
    }
}
