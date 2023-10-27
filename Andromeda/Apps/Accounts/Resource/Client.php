<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/Crypto/AuthObject.php"); use Andromeda\Apps\Accounts\Crypto\AuthObject;

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
class Client extends BaseObject
{    
    use TableTypes\TableNoChildren;

    use AuthObject { CheckKeyMatch as BaseCheckKeyMatch; }
    
    /** The last remote address used with this client */
    private FieldTypes\StringType $lastaddr;
    /** The last user agent used with this client */
    private FieldTypes\StringType $useragent;
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;
    /** The last date this client was active */
    private FieldTypes\NullTimestamp $date_active;
    /** The last date a session was created for this client */
    private FieldTypes\NullTimestamp $date_loggedon;
    /** The optional user label of this client */
    private FieldTypes\NullStringType $name;
    /** 
     * The account this client is for
     * @var FieldTypes\ObjectRefT<Account>  
     */
    private FieldTypes\ObjectRefT $account;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->lastaddr =      $fields[] = new FieldTypes\StringType('lastaddr');
        $this->useragent =     $fields[] = new FieldTypes\StringType('useragent');
        
        $this->date_created =  $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_active =   $fields[] = new FieldTypes\NullTimestamp('date_active');
        $this->date_loggedon = $fields[] = new FieldTypes\NullTimestamp('date_loggedon');
        
        $this->name =          $fields[] = new FieldTypes\NullStringType('name');
        $this->account =       $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterFields($fields, self::class);
        
        $this->AuthObjectCreateFields();
        
        parent::CreateFields();
    }

    /** Gets the interface address last used with this client */
    public function GetLastAddress() : string { return $this->lastaddr->GetValue(); }
    
    /** Gets the interface user agent last used with this client */
    public function GetUserAgent() : string { return $this->useragent->GetValue(); }
    
    /** Gets the account that owns this client */
    public function GetAccount() : Account { return $this->account->GetObject(); }

    /** Sets the timestamp this client was active to now */
    public function SetActiveDate() : self
    {
        if ($this->GetApiPackage()->GetConfig()->isReadOnly()) return $this; // TODO move up a level
        
        $this->date_active->SetTimeNow(); return $this;
    }
    
    /** Sets the timestamp when the client last created a session to now */
    public function SetLoggedonDate() : self 
    { 
        $this->date_loggedon->SetTimeNow(); return $this; 
    }
    
    /**
     * Prunes old clients from the DB that have expired
     * @param ObjectDatabase $database reference
     * @param Account $account to check clients for
     * @return int the number of deleted clients
     */
    public static function PruneOldFor(ObjectDatabase $database, Account $account) : int
    {
        if (($maxage = $account->GetClientTimeout()) === null) return 0;
        
        $mintime = $database->GetApiPackage()->GetTime() - $maxage;
        
        $q = new QueryBuilder(); $q->Where($q->And(
            $q->Equals('account',$account->ID()),
            $q->LessThan('date_active', $mintime)));
        
        return $database->DeleteObjectsByQuery(static::class, $q);
    }

    /**
     * Creates a new client object
     * @param IOInterface $interface the interface used for the request
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns this client
     * @param string $name custom name to show for the client
     * @return static new Client
     */
    public static function Create(IOInterface $interface, ObjectDatabase $database, Account $account, ?string $name = null) : self
    {
        $obj = static::BaseCreate($database);
        $obj->date_created->SetTimeNow();
        
        $obj->lastaddr->SetValue($interface->GetAddress());
        $obj->useragent->SetValue($interface->GetUserAgent());
        
        $obj->name->SetValue($name);
        $obj->account->SetObject($account);
        
        $obj->InitAuthKey(); return $obj;
    }
    
    public function NotifyDeleted() : void
    {
        Session::DeleteByClient($this->database, $this); // TODO not gonna work! foreign keys must be removed first!
        
        parent::NotifyDeleted();
    }
    
    /**
     * Authenticates the given info claiming to be this client and checks the timeout
     * @param IOInterface $interface the interface used for the request
     * @param string $key the client authentication key
     * @return bool true if success, false if invalid
     * @see AuthObject::CheckKeyMatch()
     */
    public function CheckKeyMatch(IOInterface $interface, string $key) : bool
    {        
        if (!$this->BaseCheckKeyMatch($key)) return false;
        
        $time = $this->GetApiPackage()->GetTime();
        $maxage = $this->GetAccount()->GetClientTimeout(); 
        $active = $this->date_active->TryGetValue();
        // TODO active probably shouldn't be nullable? below won't work
        
        if ($maxage !== null && $time - $active > $maxage) return false;
        
        $this->useragent->SetValue($interface->getUserAgent());
        $this->lastaddr->SetValue($interface->GetAddress());  
        
        return true;
    }
    
    /**
     * Gets this client as a printable object
     * @return array<mixed> `{id:id, name:?string, lastaddr:string, useragent:string, \
            dates_created:float, date_loggedon:?float, date_active:?float, session:Session}`
     * @see Session::GetClientObject()
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->name->TryGetValue(),
            'lastaddr' => $this->lastaddr->GetValue(),
            'useragent' => $this->useragent->GetValue(),
            'date_created' => $this->date_created->GetValue(),
            'date_loggedon' => $this->date_loggedon->TryGetValue(),
            'date_active' => $this->date_active->TryGetValue()
        );
        
        if ($secret) $data['authkey'] = $this->TryGetAuthKey();
        
        $session = Session::TryLoadByClient($this->database, $this);
        
        $data['session'] = ($session !== null) ? $session->GetClientObject($secret) : null;

        return $data;        
    }
}
