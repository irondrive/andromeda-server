<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/AuthObject.php");
require_once(ROOT."/apps/accounts/Config.php");

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
        return parent::BaseCreate($database)
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
        $data = array_merge(parent::GetClientObject($level), array(
            'id' => $this->ID(),
            'lastaddr' => $this->GetLastAddress(),
            'useragent' => $this->GetUserAgent(),
            'dates' => $this->GetAllDates(),
        ));

        if (($session = $this->GetSession()) === null) $data['session'] = null;
        else $data['session'] = $session->GetClientObject($level);

        return $data;        
    }
}
