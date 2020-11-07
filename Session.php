<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/KeySource.php");

class Session extends KeySource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(  
            'dates__active' => new FieldTypes\Scalar(null, true),
            'account' => new FieldTypes\ObjectRef(Account::class, 'sessions'),
            'client' => new FieldTypes\ObjectRef(Client::class, 'session', false)
        ));
    }
    
    public function GetClient() : Client { return $this->GetObject('client'); }

    public function getActiveDate() : int     { return $this->GetDate('active'); }
    public function setActiveDate() : Session { return $this->SetDate('active'); }
    
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : Session
    {
        return parent::CreateKeySource($database, $account)->SetObject('client',$client);
    }

    public function CheckKeyMatch(string $key) : bool
    {
        $max = $this->GetAccount()->GetMaxSessionAge();
        
        if ($max !== null && time()-$this->getActiveDate() > $max)
        {
            $this->Delete(); return false;
        }
        
        return parent::CheckKeyMatch($key);
    }

    public function GetClientObject(int $level = 0) : array
    {
        return array_merge(parent::GetClientObject($level), array(
            'id' => $this->ID(),
            'client' => $this->GetClient()->ID(),
            'dates' => $this->GetAllDates(),
        ));
    }
}
