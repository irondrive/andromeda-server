<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/GroupStuff.php");

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
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetDefaultAccounts() : array
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
        
        return array();
    }

    /**
     * Gets the list of all accounts in this group
     * @return array<string, Account> Accounts indexed by ID
     */
    public function GetAccounts() : array { return array_merge($this->GetDefaultAccounts(), $this->GetMyAccounts()); }
    
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
    
    /** Tries to load a group by name, returning null if not found */
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return static::TryLoadUniqueByKey($database, 'name', $name);
    }

    // TODO clean this up (see account)
    public function GetMailTo() : array
    {
        $accounts = $this->GetAccounts(); $output = array();
        
        foreach($accounts as $account)
        {
            if (!$account->isEnabled()) continue;
            $emails = $account->GetEmailRecipients();
            foreach ($emails as $email) array_push($output, $email);
        }
        return $output;
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
        
        foreach ($this->GetDefaultAccounts() as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, true);
        
        return $this;
    }

    public function Delete() : void
    {
        foreach ($this->GetDefaultAccounts() as $account)
            Account::RunGroupChangeHandlers($this->database, $account, $this, false);
        
        parent::Delete();
    }
    
    /**
     * Gets this group as a printable object
     * @param bool $full if true, show the list of account IDs
     * @return array `{id:string,name:string,priority:int,comment:?string,dates:{created:float}}` \
        if full, add `{accounts:[id]}` \
        also returns all inheritable account properties
     * @see Account::GetClientObject()
     */
    public function GetClientObject(bool $full = false) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'name' => $this->GetDisplayName(),
            'priority' => $this->GetPriority(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
            'features' => $this->GetAllFeatures(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
            'max_session_age' => $this->TryGetScalar('max_session_age'),
            'max_password_age' => $this->TryGetScalar('max_password_age')
        );
        
        if ($full) $retval['accounts'] = array_map(function($e){ return $e->ID(); }, array_values($this->GetAccounts()));
        
        return $retval;
    }

}
