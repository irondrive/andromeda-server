<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes, QueryBuilder};

use Andromeda\Apps\Accounts\Resource\Contact;

/**
 * A group of user accounts
 * 
 * Used primarily to manage config for multiple accounts at once, in a many-to-many relationship.
 * Groups use a priority number to resolve conflicting properties.
 * 
 * @phpstan-type GroupJ array{id:string, name:string}
 */
class Group extends PolicyBase
{
    use TableTypes\TableNoChildren;
    
    /** The short name of the group */
    private FieldTypes\StringType $name;
    /** The priority of the group's permissions */
    private FieldTypes\IntType $priority;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->name = $fields[] = new FieldTypes\StringType('name');
        $this->priority = $fields[] = new FieldTypes\IntType('priority');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'name';
        return $ret;
    }
    
    /** Gets the short name of the group */
    public function GetDisplayName() : string { return $this->name->GetValue(); }
    
    /** 
     * Sets the short name of the group
     * @return $this 
     */
    public function SetDisplayName(string $name) : self { $this->name->SetValue($name); return $this; }
    
    /** Gets the priority assigned to the group. Higher number means conflicting config takes precedent */
    public function GetPriority() : int { return $this->priority->GetValue(); }
    
    /** 
     * Sets the priority assigned to the group 
     * @return $this
     */
    public function SetPriority(int $priority) : self { $this->priority->SetValue($priority); return $this; }
    
    /**
     * Gets the list of accounts that are implicitly part of this group
     * @return array<string, Account> Accounts indexed by ID or null if not a default group
     */
    public function GetDefaultAccounts() : ?array
    {
        if (Config::GetInstance($this->database)->GetDefaultGroup() === $this)
            return Account::LoadAll($this->database);
        
        foreach (AuthSource\External::LoadAll($this->database) as $authman)
        {
            if ($authman->GetDefaultGroup() === $this)
                return Account::LoadByAuthSource($this->database, $authman);
        }
        
        return null; // not a default group
    }

    /**
     * Gets the list of all accounts in this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetAccounts() : array { return $this->GetDefaultAccounts() ?? $this->GetJoinedAccounts(); }
    
    /**
     * Gets the list of accounts that are explicitly part of this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetJoinedAccounts() : array { return GroupJoin::LoadAccounts($this->database, $this); }
    
    /** Adds a new account to this group */
    public function AddAccount(Account $account) : self { return $this; } // TODO RAY !! $this->AddObjectRef('accounts', $account); return $this; }
    
    /** Removes an account from this group */
    public function RemoveAccount(Account $account) : self { return $this; } // TODO RAY !! $this->RemoveObjectRef('accounts', $account); return $this; }
    
    /** Returns the object joining this group to the given account */
    public function GetAccountJoin(Account $account) : ?GroupJoin
    {
        return null;
        //return $this->TryGetJoinObject('accounts', $account);
        // TODO RAY !! not sure what to do here. load all accounts and pull out of array? or load groupjoin separately? what is usage?
    }
    
    /** Tries to load a group by name, returning null if not found */
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return $database->TryLoadUniqueByKey(static::class, 'name', $name);
    }
    
    /**
     * Loads all groups matching the given name
     * @param ObjectDatabase $database database reference
     * @param string $name name to match (wildcard)
     * @param positive-int $limit max number to load - returns nothing if exceeded
     * @return array<string, static>
     * @see Group::GetClientObject()
     */
    public static function LoadAllMatchingName(ObjectDatabase $database, string $name, int $limit) : array
    {
        // TODO possible security issue here, see note in QueryBuilder... maybe just REMOVE all wildcard characters? safer hard though because _ counts!
        
        $q = new QueryBuilder(); $name = QueryBuilder::EscapeWildcards($name).'%'; // search by prefix
        
        $loaded = $database->LoadObjectsByQuery(static::class, $q->Where($q->Like('name',$name,true))->Limit($limit+1));
        
        return (count($loaded) >= $limit+1) ? array() : $loaded;
    }

    /**
     * Gets contact objects for all accounts in this group
     * @return array<string, Contact> indexed by ID
     * @see Account::GetContacts()
     */
    public function GetContacts() : array
    {
        $output = array();
        
        foreach ($this->GetAccounts() as $account)
        {
            // TODO RAY !! foreach is inefficient here, use += or array_merge?
            foreach ($account->GetContacts() as $contact)
                $output[$contact->ID()] = $contact;
        }
        
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
        $group = $database->CreateObject(static::class);
        // TODO RAY !! PolicyBase needs a base create that sets date created
        
        $group->name->SetValue($name);
        $group->priority->SetValue($priority ?? 0);
        
        if ($comment !== null) 
            $group->comment->SetValue($comment);
        
        return $group;
    }
    
    /** Initializes a newly created group by running group change handlers on its implicit accounts */
    public function Initialize() : self
    {
        //if (!$this->isCreated()) return $this;  // TODO RAY !! don't have isCreated anymore, only PostConstruct bool $created...
        // why does Initialize need to exist? something about do this after setting the default... seems like a good reason 
        
        foreach (($this->GetDefaultAccounts() ?? array()) as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, true);
        
        return $this;
    }
    
    /** @var array<callable(ObjectDatabase, self): void> */
    private static array $delete_handlers = array();
    
    /** 
     * Registers a function to be run when a group is deleted
     * @param callable(ObjectDatabase, self): void $func
     */
    public static function RegisterDeleteHandler(callable $func) : void { 
        self::$delete_handlers[] = $func; }
    
    public function NotifyPreDeleted() : void
    {
        foreach (($this->GetDefaultAccounts() ?? array()) as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, false);
            
        foreach (self::$delete_handlers as $func) 
            $func($this->database, $this);
    }
    
    public const OBJECT_FULL = 1; 
    public const OBJECT_ADMIN = 2;
    
    /**
     * Gets this group as a printable object
     * @param int $level if FULL, show list of account IDs, if ADMIN, show details
     * @return GroupJ
     * return array<mixed> `{id:id, name:string}` \
        if FULL, add `{accounts:[id]}` \
        if ADMIN, add `{priority:int,comment:?string,dates:{created:float,modified:?float}, session_timeout:?int, client_timeout:?int, max_password_age:?int, \
            config:{admin:?bool,disabled:?int,forcetf:?bool,allowcrypto:?bool,accountsearch:?int,groupsearch:?int,userdelete:bool}, \
            counters:{accounts:int}, limits:{sessions:?int,contacts:?int,recoverykeys:?int}}`
     * @see Account::GetClientObject()
     */
    public function GetClientObject(int $level = 0) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'name' => $this->GetDisplayName()
        );
        
        /*if (($level & self::OBJECT_ADMIN) !== 0)
        {
            $retval += array(
                'dates' => array( // TODO RAY !! remove subarrays here
                    'created' => $this->date_created->GetValue(),
                    'modified' => $this->date_modified->TryGetValue()
                ),
                'config' => array_merge( // TODO RAY !! implement/fix me
                    Utilities::array_map_keys(function($p){ return $this->GetFeatureBool($p); },
                        array('admin','forcetf','allowcrypto','userdelete')),
                    Utilities::array_map_keys(function($p){ return $this->GetFeatureInt($p); },
                        array('disabled','accountsearch','groupsearch'))
                ),
                'counters' => array(
                    'accounts' => $this->CountObjectRefs('accounts')
                ),
                'limits' => Utilities::array_map_keys(function($p){ return $this->TryGetCounterLimit($p); },
                    array('sessions','contacts','recoverykeys')
                ),
                'priority' => $this->GetPriority(),
                'comment' => $this->GetComment(),
                'session_timeout' => $this->TryGetScalar('session_timeout'),
                'client_timeout' => $this->TryGetScalar('client_timeout'),
                'max_password_age' => $this->TryGetScalar('max_password_age')
            );
        }            
        
        if (($level & self::OBJECT_FULL) !== 0) 
            $retval['accounts'] = array_keys($this->GetAccounts());*/
        
        return $retval;
    }
}
