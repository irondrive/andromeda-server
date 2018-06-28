<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\ClientObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");
require_once(ROOT."/apps/accounts/MasterKeySource.php");

class Session extends MasterKeySource implements ClientObject
{
    public function GetKey() : string { return $this->GetScalar('authkey'); } 
    public function GetClient() : Client { return $this->GetObject('client'); }
    
    public function getActiveDate() : int     { return $this->GetDate('active'); }
    public function setActiveDate() : Session { return $this->SetDate('active'); }
    
    const KEY_LENGTH = 32; 
    
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : Session
    {
        $session = parent::BaseCreate($database);
        
        $session->SetScalar('authkey',Utilities::Random(self::KEY_LENGTH))
            ->SetObject('account',$account)->SetObject('client',$client);
        
        $client->SetObject('session',$session);
        
        return $session;
    }
    
    public function CheckMatch(string $key) : bool
    {
        $max = $this->GetAccount()->GetMaxSessionAge();  
        
        if ($max !== null && time()-$this->getActiveDate() > $max) 
        { 
            $this->Delete(); return false; 
        }       
        
        else return strlen($key) > 0 && $this->GetKey() === $key;
    }
    
    public function GetClientObject(int $level = 0) : array
    {
        return array(
            'id' => $this->ID(),
            'client' => $this->GetClient()->ID(),
            'dates' => $this->GetAllDates(),
        );
    }
}
