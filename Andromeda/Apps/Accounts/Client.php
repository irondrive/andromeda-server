<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/AuthObject.php");
require_once(ROOT."/Apps/Accounts/Config.php");

/**
 * A client registered for authenticating as an account
 *
 * Extends AuthObject, using a hashed key for authentication.
 * Each client can contain exactly one registered session.
 * 
 * The client is separate from the session mainly so that a user
 * can sign-out securely and not need to use two factor (or remember
 * any other client-specific values) on the next sign in
 */
class Client extends AuthObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'lastaddr' => null,
            'useragent' => null,
            'dates__loggedon' => null,            
            'dates__active' => new FieldTypes\Scalar(null, true),
            'account' => new FieldTypes\ObjectRef(Account::class, 'clients'),
            'session' => (new FieldTypes\ObjectRef(Session::class, 'client', false))->autoDelete()
        ));
    }
    
    /** Gets the interface address last used with this client */
    public function GetLastAddress() : string { return $this->GetScalar('lastaddr'); }
    
    /** Gets the interface user agent last used with this client */
    public function GetUserAgent() : string { return $this->GetScalar('useragent'); }
    
    /** Gets the account that owns this client */
    public function GetAccount() : Account { return $this->GetObject('account'); }
    
    /** Gets the session in use on this client, if one exists */
    public function GetSession() : ?Session { return $this->TryGetObject('session'); }
    
    /** Deletes an existing session for this client */
    public function DeleteSession() : self { return $this->DeleteObject('session'); }
    
    /** Gets the last timestamp this client was active */
    public function getActiveDate() : ?float { return $this->TryGetDate('active'); }
    
    /** Sets the timestamp this client was active to now */
    public function setActiveDate() : self
    {
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        return $this->SetDate('active');
    }
    
    /**
     * Prunes old clients from the DB that have expired
     * @param ObjectDatabase $database reference
     * @param Account $account to check clients for
     */
    public static function PruneOldFor(ObjectDatabase $database, Account $account) : void
    {
        if (($maxage = $account->GetClientTimeout()) === null) return;
        
        $mintime = Main::GetInstance()->GetTime() - $maxage;
        
        $q = new QueryBuilder(); $q->Where($q->And(
            $q->Equals('account',$account->ID()),$q->LessThan('dates__active', $mintime)));
        
        static::DeleteByQuery($database, $q);
    }
    
    /** Gets the last timestamp the client created a session */
    public function getLoggedonDate() : float { return $this->GetDate('loggedon'); }
    
    /** Sets the timestamp when the client last created a session */
    public function setLoggedonDate() : self { return $this->SetDate('loggedon'); }
    
    /**
     * Creates a new client object
     * @param IOInterface $interface the interface used for the request
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns this client
     * @param string $name custom name to show for the client
     * @return self new Client
     */
    public static function Create(IOInterface $interface, ObjectDatabase $database, Account $account, ?string $name = null) : self
    {
        return parent::BaseCreate($database)->SetScalar('name',$name)
            ->SetScalar('lastaddr',$interface->GetAddress())
            ->SetScalar('useragent',$interface->GetUserAgent())
            ->SetObject('account',$account);
    }

    /**
     * Authenticates the given info claiming to be this client and checks the timeout
     * @param IOInterface $interface the interface used for the request
     * @param string $key the client authentication key
     * @return bool true if success, false if invalid
     * @see AuthObject::CheckKeyMatch()
     */
    public function CheckMatch(IOInterface $interface, string $key) : bool
    {        
        if (!$this->CheckKeyMatch($key)) return false;
        
        $time = Main::GetInstance()->GetTime();
        $maxage = $this->GetAccount()->GetClientTimeout(); 
        
        if ($maxage !== null && $time - $this->getActiveDate() > $maxage) return false;

        $this->SetScalar('useragent', $interface->getUserAgent());
        $this->SetScalar('lastaddr', $interface->GetAddress());  
        
        return true;
    }
    
    /**
     * Gets this client as a printable object
     * @return array `{id:id, name:?string, lastaddr:string, useragent:string, \
            dates:{created:float, loggedon:float, active:?float}, session:Session}`
     * @see AuthObject::GetClientObject()
     * @see Session::GetClientObject()
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'lastaddr' => $this->GetLastAddress(),
            'useragent' => $this->GetUserAgent(),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'loggedon' => $this->getLoggedonDate(),
                'active' => $this->getActiveDate()
            )
        );

        $session = $this->GetSession();
        
        $data['session'] = ($session !== null) ? $session->GetClientObject($secret) : null;
        
        if ($secret) $data['authkey'] = $this->GetAuthKey();

        return $data;        
    }
}
