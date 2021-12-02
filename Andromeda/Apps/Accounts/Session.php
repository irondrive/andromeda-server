<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Apps/Accounts/KeySource.php");

/**
 * Implements an account session, the primary implementor of authentication
 *
 * Also stores a copy of the account's master key, encrypted by the session key.
 * This allowed account crypto to generally be unlocked for any user command.
 */
class Session extends KeySource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'dates__active' => null,
            'account' => new FieldTypes\ObjectRef(Account::class, 'sessions'),
            'client' => new FieldTypes\ObjectRef(Client::class, 'session', false)
        ));
    }
    
    /** Returns the client that owns this session */
    public function GetClient() : Client { return $this->GetObject('client'); }
    
    /** Create a new session for the given account and client */
    public static function Create(ObjectDatabase $database, Account $account, Client $client) : Session
    {
        return parent::CreateKeySource($database, $account)->SetObject('client',$client);
    }
    
    /** Deletes all sessions for the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        $q = new QueryBuilder(); $w = $q->Equals('account',$account->ID());
        
        parent::DeleteByQuery($database, $q->Where($w));
    }
    
    /** Deletes all sessions for the given account except the given session */
    public static function DeleteByAccountExcept(ObjectDatabase $database, Account $account, Session $session) : void
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->NotEquals('id',$session->ID()));
        
        parent::DeleteByQuery($database, $q->Where($w));
    }
        
    /** Gets the last timestamp this client was active */
    public function getActiveDate() : ?float { return $this->TryGetDate('active'); }
    
    /** Sets the timestamp this client was active to now */
    public function setActiveDate() : self
    {
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        return $this->SetDate('active');
    }
    
    /** 
     * Prunes old sessions from the DB that have expired 
     * @param ObjectDatabase $database reference
     * @param Account $account to check sessions for
     */
    public static function PruneOldFor(ObjectDatabase $database, Account $account) : void
    {
        if (($maxage = $account->GetSessionTimeout()) === null) return;
        
        $mintime = Main::GetInstance()->GetTime() - $maxage;
        
        $q = new QueryBuilder(); $q->Where($q->And(
            $q->Equals('account',$account->ID()),$q->LessThan('dates__active', $mintime)));
        
        static::DeleteByQuery($database, $q);
    }
    
    /**
     * Authenticates the given info claiming to be this session and checks the timeout
     * @param string $key the session authentication key
     * @return bool true if success, false if invalid
     * @see AuthObject::CheckKeyMatch()
     */
    public function CheckMatch(string $key) : bool
    {
        if (!$this->CheckKeyMatch($key)) return false;
        
        $time = Main::GetInstance()->GetTime();
        $maxage = $this->GetAccount()->GetSessionTimeout(); 
        
        if ($maxage !== null && $time - $this->getActiveDate() > $maxage) return false;
        
        return true;
    }
    
    /**
     * Returns a printable client object for this session
     * @return array `{id:id,client:id,dates:{created:float,active:?float}}`
     * @see AuthObject::GetClientObject()
     */
    public function GetClientObject(bool $secret = false) : array
    {
        return array_merge(parent::GetClientObject($secret), array(
            'id' => $this->ID(),
            'client' => $this->GetClient()->ID(),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'active' => $this->getActiveDate()
            ),
        ));
    }
}
