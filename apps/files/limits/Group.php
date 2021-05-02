<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\GroupJoin;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");
require_once(ROOT."/apps/files/limits/AuthObj.php");

interface IGroupLimit 
{ 
    /** Track stats for component accounts by inheriting this property */
    const TRACK_ACCOUNTS = 1;
    
    /** Track stats for components accounts and also the group as a whole */
    const TRACK_WHOLE_GROUP = 2;
    
    const TRACK_TYPES = array('none'=>0,
        'accounts'=>self::TRACK_ACCOUNTS, 
        'wholegroup'=>self::TRACK_WHOLE_GROUP);  
}

/**
 * Group limits common between total and timed
 * 
 * The extra complexity with groups is because they operate as sums of their component
 * accounts, and must manage updating themselves when account memberships are changed.
 *  
 * Group limits apply to its component accounts individually, not the group as a whole.
 */
trait GroupCommon
{
    protected static function GetObjectClass() : string { return Group::class; }
    
    /** Returns the ID of the limited group */
    public function GetGroupID() : string { return $this->GetObjectID('object'); }
    
    /** Returns the limited group object */
    public function GetGroup() : Group  { return $this->GetObject('object'); }
    
    /** Returns the inheritance priority of the limited group */
    public function GetPriority() : int { return $this->GetGroup()->GetPriority(); }

    protected function canTrackItems() : bool { return ($this->TryGetFeature('track_items') ?? 0) >= self::TRACK_WHOLE_GROUP; }
    protected function canTrackDLStats() : bool { return ($this->TryGetFeature('track_dlstats') ?? 0) >= self::TRACK_WHOLE_GROUP; }

    // the group's limits apply only to its component accounts
    protected function IsCounterOverLimit(string $name, int $delta = 0) : bool { return false; }
    
    /**
     * Updates the group's stats by adding or subtracting an account's stats
     * @param AccountCommon $aclim the account limits
     * @param bool $add true to add, false to subtract
     */
    public function ProcessAccountChange(IAccountLimit $aclim, bool $add) : void
    {  
        $mul = $add ? 1 : -1;
        $this->CountPublicDownloads($mul*$aclim->GetPublicDownloads());
        $this->CountBandwidth($mul*$aclim->GetBandwidth());
        $this->CountSize($mul*$aclim->GetSize());
        $this->CountItems($mul*$aclim->GetItems());
        $this->CountShares($mul*$aclim->GetShares());
    }
    
    public static function GetBaseUsage() : string { return "[--track_items ?(".implode('|',array_keys(self::TRACK_TYPES)).")] ".
                                                            "[--track_dlstats ?(".implode('|',array_keys(self::TRACK_TYPES)).")]"; }
    
    protected static function GetTrackParam(Input $input, string $name) : ?int
    {
        $param = $input->GetNullParam($name, SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
            function($v){ return array_key_exists($v, self::TRACK_TYPES); });
        
        return ($param !== null) ? self::TRACK_TYPES[$param] : null;
    }
    
    protected function SetBaseLimits(Input $input) : void
    {        
        if ($input->HasParam('track_items'))
        {
            $this->SetFeature('track_items', static::GetTrackParam($input,'track_items'));
            
            if ($this->isFeatureModified('track_items')) $init = true;
        }
        
        if ($input->HasParam('track_dlstats'))
        {
            $this->SetFeature('track_dlstats', static::GetTrackParam($input,'track_dlstats'));
            
            if ($this->isFeatureModified('track_dlstats')) $init = true;
        }
        
        if ($init ?? false) $this->Initialize();
    }
    
    /** Configures limits for the given group with the given input */
    public static function ConfigLimits(ObjectDatabase $database, Group $group, Input $input) : self
    {
        return static::BaseConfigLimits($database, $group, $input);
    }    
    
    /** 
     * Deletes the group limits and potentially removes account limits 
     * @see AccountCommon::ProcessGroupRemove()
     */
    public function Delete() : void
    {
        foreach ($this->GetAccounts() as $aclim)
            $aclim->ProcessGroupRemove($this);
        
        parent::Delete();
    }
    
    /**
     * @return array add: `features:{track_items:string,track_dlstats:string}`
     * @see Total::GetClientObject()
     * @see Timed::GetClientObject()
     */
    public function GetClientObject() : array
    {
        $retval = parent::GetClientObject();
        
        $retval['features']['track_items'] = array_flip(self::TRACK_TYPES)[$this->GetFeature('track_items')];
        $retval['features']['track_dlstats'] = array_flip(self::TRACK_TYPES)[$this->GetFeature('track_dlstats')];
        
        return $retval;
    }
}

/** Concrete class providing group config and total stats */
class GroupTotal extends AuthEntityTotal implements IGroupLimit
{ 
    use GroupCommon; 
    
    /** cache of account limits that apply to this group */
    protected array $acctlims;
    
    /**
     * loads account limits via a JOIN, caches, and returns them
     * @return array<string, AccountTotal> account limits indexed by ID
     */
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->Equals($this->database->GetClassTableName(GroupJoin::class).'.groups', $this->GetGroupID()))
                ->Join($this->database, GroupJoin::class, 'accounts', AccountTotal::class, 'object', Account::class);
            
            $this->acctlims = AccountTotal::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() ?? array() as $account)
            {
                $acctlim = AccountTotal::LoadByAccount($this->database, $account, false);
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    /** register a group change handler that updates this specific object's accountlim cache */
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $group !== $this->GetGroup()) return;
            
            $aclim = AccountTotal::LoadByAccount($database, $account);
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }
    
    /** Returns true if the group's members are allowed to email share links */
    public function GetAllowEmailShare() : ?bool { return $this->TryGetFeature('emailshare'); }
    
    /** Returns true if the group's members are allowed to add new filesystems */
    public function GetAllowUserStorage() : ?bool { return $this->TryGetFeature('userstorage'); }

    /** Returns the total limits for the given group (or none) */
    public static function LoadByGroup(ObjectDatabase $database, Group $group) : ?self
    {
        return static::LoadByClient($database, $group);
    }

    /** Initializes group limits by adding a limit for each member account and adding stats */
    protected function Initialize() : self
    {
        parent::Initialize();
        
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
        {
            $aclim = AccountTotal::ForceLoadByAccount($this->database, $account);
            
            $this->ProcessAccountChange($aclim->Initialize(), true);
        }

        return $this;
    }
}

/** Concrete class providing timed group member limits */
class GroupTimed extends AuthEntityTimed implements IGroupLimit
{ 
    use GroupCommon;
    
    /** cache of account limits that apply to this group */
    protected array $acctlims;
    
    /**
     * loads account limits via a JOIN, caches, and returns them
     * @return array<string, AccountTimed> account limits indexed by ID
     */
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->And($q->Equals('timeperiod',$this->GetTimePeriod()),$q->Equals($this->database->GetClassTableName(GroupJoin::class).'.groups', $this->GetGroupID())))
                ->Join($this->database, GroupJoin::class, 'accounts', AccountTimed::class, 'object', Account::class);
            
            $this->acctlims = AccountTimed::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() ?? array() as $account)
            {
                $acctlim = AccountTimed::LoadByAccount($this->database, $account, false);
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    /** register a group change handler that updates this specific object's accountlim cache */
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            if ($this->isDeleted() || $group !== $this->GetGroup()) return;
            
            $aclim = AccountTimed::LoadByAccount($database, $account, $this->GetTimePeriod());
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }

    /** Loads the timed limit for the given group and time period */
    public static function LoadByGroup(ObjectDatabase $database, Group $group, int $period) : ?self
    {
        return static::LoadByClientAndPeriod($database, $group, $period);
    }
    
    /**
     * Returns all timed limits for the given group
     * @param ObjectDatabase $database database reference
     * @param Group $group group of interest
     * @return array<string, GroupTimed> limits indexed by ID
     */
    public static function LoadAllForGroup(ObjectDatabase $database, Group $group) : array
    {
        return static::LoadAllForClient($database, $group);
    }

    /** Initializes group limits by adding a limit for each member account */
    protected function Initialize() : self
    {        
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
        {
            $aclim = AccountTimed::ForceLoadByAccount($this->database, $account, $this->GetTimePeriod());
            
            $aclim->Initialize();
        }            
        
        return $this;
    }
    
    protected static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $glim = parent::BaseConfigLimits($database, $obj, $input);
        
        // prune stats for member accounts also
        foreach ($glim->GetAccounts() as $aclim)
        {
            if ($aclim->GetsMaxStatsAgeFrom() === $glim)
                TimedStats::PruneStatsByLimit($database, $aclim);
        }
        
        return $glim;
    }
}

/** Handle deleting limits when a group is deleted */
Group::RegisterDeleteHandler(function(ObjectDatabase $database, Group $group)
{
    GroupTotal::DeleteByClient($database, $group);
    GroupTimed::DeleteByClient($database, $group);
});