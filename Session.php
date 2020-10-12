<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\ClientObject;
use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/Client.php");
require_once(ROOT."/apps/accounts/Config.php");

class Session extends AuthObject implements ClientObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'master_key' => null,
            'master_nonce' => null,
            'master_salt' => null,    
            'dates__active' => new FieldTypes\Scalar(null, true),
            'account' => new FieldTypes\ObjectRef(Account::class, 'sessions'),
            'client' => new FieldTypes\ObjectRef(Client::class, 'session', false)
        ));
    }
    
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
    
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {        
        parent::DeleteManyMatchingAll($database, array('account' => $account->ID()));
        //throw new Andromeda\Core\Exceptions\ServerException("hooray");
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
