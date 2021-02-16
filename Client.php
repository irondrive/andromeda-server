<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/AuthObject.php");
require_once(ROOT."/apps/accounts/Config.php");

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
            'session' => new FieldTypes\ObjectRef(Session::class, 'client', false)
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
    public function getActiveDate() : float { return $this->GetDate('active'); }
    
    /** Sets the timestamp this client was active to now */
    public function setActiveDate() : self { return $this->SetDate('active'); }
    
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
     * Authenticates the given info claiming to be this client
     * @param IOInterface $interface the interface used for the request
     * @param string $key the client authentication key
     * @return bool true if success, false if invalid
     */
    public function CheckMatch(IOInterface $interface, string $key) : bool
    {        
        $good = $this->CheckKeyMatch($key);
        
        if ($good)
        {
            $this->SetScalar('useragent', $interface->getUserAgent());
            $this->SetScalar('lastaddr', $interface->GetAddress());  
        }
        
        return $good;
    }

    /** Deletes this client and its session */
    public function Delete() : void
    {
        if ($this->HasObject('session'))
            $this->DeleteObject('session'); 
        
        parent::Delete();
    }
    
    /**
     * Gets this client as a printable object
     * @return array `{id:string, name:?string, lastaddr:string, useragent:string, dates:{created:float, active:float, loggedon:float}, session:Session}`
     * @see AuthObject::GetClientObject()
     * @see Session::GetClientObject()
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array_merge(parent::GetClientObject($secret), array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'lastaddr' => $this->GetLastAddress(),
            'useragent' => $this->GetUserAgent(),
            'dates' => $this->GetAllDates(),
        ));

        $session = $this->GetSession();
        
        $data['session'] = ($session !== null) ? $session->GetClientObject($secret) : null;

        return $data;        
    }
}
