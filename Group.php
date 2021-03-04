<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Contact.php");
require_once(ROOT."/apps/accounts/GroupStuff.php");

require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

/**
 * A group of user accounts
 * 
 * Used primarily to manage config for multiple accounts at once, in a many-to-many relationship.
 * Groups use a priority number to resolve conflicting properties.
 */
class Group extends AuthEntity
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'comment' => null,
            'priority' => new FieldTypes\Scalar(0),       
            'accounts' => new FieldTypes\ObjectJoin(Account::class, GroupJoin::class, 'groups')
        ));
    }
    
    /** Gets the short name of the group */
    public function GetDisplayName() : string { return $this->GetScalar('name'); }
    
    /** Sets the short name of the group */
    public function SetDisplayName(string $name) : self { return $this->SetScalar('name',$name); }
    
    /** Gets the comment for the group (or null) */
    public function GetComment() : ?string { return $this->TryGetScalar('comment'); }
    
    /** Sets the comment for the group (or null) */
    public function SetComment(?string $comment) : self { return $this->SetScalar('comment',$comment); }
    
    /** Gets the priority assigned to the group. Higher number means conflicting config takes precedent */
    public function GetPriority() : int { return $this->GetScalar('priority'); }
    
    /** Sets the priority assigned to the group */
    public function SetPriority(int $priority) { return $this->SetScalar('priority', $priority); }
    
    /**
     * Gets the list of accounts that are implicitly part of this group
     * @return array<string, Account> Accounts indexed by ID or null if not a default group
     */
    public function GetDefaultAccounts() : ?array
    {
        if (Config::GetInstance($this->database)->GetDefaultGroup() === $this)
        {
            return Account::LoadAll($this->database);
        }
        
        foreach (Auth\Manager::LoadAll($this->database) as $authman)
        {
            if ($authman->GetDefaultGroup() === $this)
            {
                return Account::LoadByAuthSource($this->database, $authman);
            }
        }
        
        return null;
    }

    /**
     * Gets the list of all accounts in this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetAccounts() : array { return $this->GetDefaultAccounts() ?? $this->GetMyAccounts(); }
    
    /**
     * Gets the list of accounts that are explicitly part of this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetMyAccounts() : array { return $this->GetObjectRefs('accounts'); }
    
    /** Adds a new account to this group */
    public function AddAccount(Account $account) : self { return $this->AddObjectRef('accounts', $account); }
    
    /** Removes an account from this group */
    public function RemoveAccount(Account $account) : self { return $this->RemoveObjectRef('accounts', $account); }
    
    /** Gets the date that an account became a member of this group (or null) */
    public function GetAccountAddedDate(Account $account) : ?float
    {
        $joinobj = $this->TryGetJoinObject('accounts', $account);
        return ($joinobj !== null) ? $joinobj->GetDateCreated() : null;
    }
    
    /** Returns the object joining this group to the given account */
    public function GetAccountJoin(Account $account) : ?GroupJoin
    {
        return $this->TryGetJoinObject('accounts', $account);
    }
    
    /** Tries to load a group by name, returning null if not found */
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return static::TryLoadUniqueByKey($database, 'name', $name);
    }
    
    /**
     * Loads all groups matching the given name
     * @param ObjectDatabase $database database reference
     * @param string $name name to match (wildcard)
     * @param int $limit max number to load - returns nothing if exceeded
     * @return array Group
     * @see Group::GetClientObject()
     */
    public static function LoadAllMatchingName(ObjectDatabase $database, string $name, int $limit) : array
    {
        $q = new QueryBuilder(); $name = QueryBuilder::EscapeWildcards($name).'%'; // search by prefix
        
        $loaded = parent::LoadByQuery($database, $q->Where($q->Like('name',$name,true))->Limit($limit+1));
        
        return (count($loaded) >= $limit+1) ? array() : $loaded;
    }

    /**
     * Gets contact objects for all accounts in this group
     * @return array <string, Contact> indexed by ID
     * @see Account::GetContacts()
     */
    public function GetContacts() : array
    {
        $output = array();
        
        foreach($this->GetAccounts() as $account)
            array_push($output, ...$account->GetContacts());
        
        return $output;
    }
    
    /**
     * Sends a message to all of this group's accounts' valid contacts (with BCC)
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        Contact::SendMessageMany($subject, $html, $plain, $this->GetContacts(), true, $from);
    }
    
    /** Creates and returns a new group with the given name, priority, and comment */
    public static function Create(ObjectDatabase $database, string $name, ?int $priority = null, ?string $comment = null) : self
    {
        $group = parent::BaseCreate($database)->SetScalar('name', $name)->SetScalar('priority', $priority ?? 0);
        
        if ($comment !== null) $group->SetScalar('comment', $comment);
        
        return $group;
    }
    
    /** Initializes a newly created group by running group change handlers on its implicit accounts */
    public function Initialize() : self
    {
        if (!$this->isCreated()) return $this;        
        
        foreach ($this->GetDefaultAccounts() ?? array() as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, true);
        
        return $this;
    }
    
    private static array $delete_handlers = array();
    
    /** Registers a function to be run when a group is deleted */
    public static function RegisterDeleteHandler(callable $func){ array_push(static::$delete_handlers,$func); }
    
    /**
     * Deletes this group and all associated objects
     * @see BaseObject::Delete()
     */
    public function Delete() : void
    {
        foreach ($this->GetDefaultAccounts() ?? array() as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, false);
            
        foreach (static::$delete_handlers as $func) $func($this->database, $this);    
            
        parent::Delete();
    }
    
    const OBJECT_FULL = 1; const OBJECT_ADMIN = 2;
    
    /**
     * Gets this group as a printable object
     * @param int $level if FULL, show list of account IDs, if ADMIN, show details
     * @return array `{id:string,name:string,priority:int,comment:?string,dates:{created:float}}` \
        if full, add `{accounts:[id]}` \
        also returns all inheritable account properties
     * @see Account::GetClientObject()
     */
    public function GetClientObject(int $level = 0) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'name' => $this->GetDisplayName()
        );
        
        if ($level && self::OBJECT_ADMIN)
        {
            $retval = array_merge($retval, array(
                'priority' => $this->GetPriority(),
                'comment' => $this->GetComment(),
                'dates' => $this->GetAllDates(),
                'features' => $this->GetAllFeatures(),
                'counters' => $this->GetAllCounters(),
                'limits' => $this->GetAllCounterLimits(),
                'session_timeout' => $this->TryGetScalar('session_timeout'),
                'max_password_age' => $this->TryGetScalar('max_password_age')
            ));
        }            
        
        if ($level && self::OBJECT_FULL) $retval['accounts'] = array_map(function($e){ return $e->ID(); }, array_values($this->GetAccounts()));
        
        return $retval;
    }
}
