<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\{StandardObject, ClientObject};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");

class Session extends AuthObject implements ClientObject
{
    public function GetClient() : Client { return $this->GetObject('client'); }
    public function GetAccount() : Account  { return $this->GetObject('account'); }
    
    public function getActiveDate() : int     { return $this->GetDate('active'); }
    public function setActiveDate() : Session { return $this->SetDate('active'); }
    
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : Session
    {
        $session = parent::BaseCreate($database);
        
        $session->CreateAuthKey()->SetObject('account',$account)->SetObject('client',$client);
        
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
        
        return $this->CheckKeyMatch($key);
    }
    
    const OBJECT_METADATA = 0; const OBJECT_WITHSECRET = 1;
    
    public function GetClientObject(int $level = 0) : array
    {
        $data = array(
            'id' => $this->ID(),
            'client' => $this->GetClient()->ID(),
            'dates' => $this->GetAllDates(),
        );
        
        if ($level === self::OBJECT_WITHSECRET) 
            $data['authkey'] = $this->GetAuthKey();
        
        return $data;
    }
}
