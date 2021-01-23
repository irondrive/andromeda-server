<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\GroupJoin;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");
require_once(ROOT."/apps/files/limits/AuthObj.php");

trait GroupCommon
{
    protected static function GetObjectClass() : string { return Group::class; }
    public function GetGroupID() : string { return $this->GetObjectID('object'); }
    public function GetGroup() : Group  { return $this->GetObject('object'); }
    public function GetPriority() : int { return $this->GetGroup()->GetPriority(); }
    
    private static int $TRACK_ACCOUNTS = 1; private static int $TRACK_WHOLE_GROUP = 2;
    
    protected function canTrackItems() : bool { return ($this->TryGetFeature('track_items') ?? 0) >= self::$TRACK_WHOLE_GROUP; }
    protected function canTrackDLStats() : bool { return ($this->TryGetFeature('track_dlstats') ?? 0) >= self::$TRACK_WHOLE_GROUP; }

    // the group's limits apply only to its component accounts
    protected function IsCounterOverLimit(string $name, int $delta = 0) : bool { return false; }
    
    public function ProcessAccountChange(array $accounts, bool $add) : void
    {
        $mul = $add ? 1 : -1;
        $this->CountDownloads($mul*array_sum(array_map(function(Base $lim){ return $lim->GetDownloads(); }, $accounts)));
        $this->CountBandwidth($mul*array_sum(array_map(function(Base $lim){ return $lim->GetBandwidth(); }, $accounts)));
        $this->CountSize($mul*array_sum(array_map(function(Base $lim){ return $lim->GetSize(); }, $accounts)));
        $this->CountItems($mul*array_sum(array_map(function(Base $lim){ return $lim->GetItems(); }, $accounts)));
        $this->CountShares($mul*array_sum(array_map(function(Base $lim){ return $lim->GetShares(); }, $accounts)));
    }
    
    public static function GetBaseUsage() : string { return "[--track_items ?(0|1|2) [--track_dlstats ?(0|1|2)"; }
    
    protected function SetBaseLimits(Input $input) : void
    {
        if ($input->HasParam('track_items')) $this->SetFeature('track_items', $input->TryGetParam('track_items', SafeParam::TYPE_INT));
        if ($input->HasParam('track_dlstats')) $this->SetFeature('track_dlstats', $input->TryGetParam('track_dlstats', SafeParam::TYPE_INT));
    }
    
    public static function ConfigLimits(ObjectDatabase $database, Group $group, Input $input) : self
    {
        return static::BaseConfigLimits($database, $group, $input);
    }    
    
    public function Delete() : void
    {
        foreach ($this->GetAccounts() as $aclim)
            $aclim->ProcessGroupRemove($this);
        
        parent::Delete();
    }
}


class GroupTotal extends AuthTotal          
{ 
    use GroupCommon; 
    
    protected array $acctlims;
    
    // load account limits via join and cache
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->Equals($this->database->GetClassTableName(GroupJoin::class).'.groups', $this->GetGroupID()))
                ->Join($this->database, GroupJoin::class, 'accounts', AccountTotal::class, 'object', Account::class);
            
            $this->acctlims = AccountTotal::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() as $account)
            {
                $acctlim = AccountTotal::LoadByAccount($this->database, $account, false);
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            $aclim = AccountTotal::LoadByGroup($database, $group);
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }
    
    public function GetAllowEmailShare() : ?bool { return $this->TryGetFeature('emailshare'); }
    public function GetAllowUserStorage() : ?bool { return $this->TryGetFeature('userstorage'); }

    public static function LoadByGroup(ObjectDatabase $database, Group $group) : ?self
    {
        return static::LoadByClient($database, $group);
    }

    protected function Initialize() : self
    {
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
        {
            $aclim = AccountTotal::ForceLoadByAccount($this->database, $account);
            
            if ($this->canTrackItems())
                $this->ProcessAccountChange($aclim, true);
        }

        return $this;
    }
}

Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
{
    $gl = GroupTotal::LoadByGroup($database, $group); if ($gl !== null)
        $gl->ProcessAccountChange(array($account), $added);
});


class GroupTimed extends AuthTimed
{ 
    use GroupCommon;
    
    protected array $acctlims;
    
    // load account limits via join and cache
    protected function GetAccounts() : array
    {
        if (!isset($this->acctlims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->And($q->Equals('timeperiod',$this->GetTimePeriod()),$q->Equals($this->database->GetClassTableName(GroupJoin::class).'.groups', $this->GetGroupID())))
                ->Join($this->database, GroupJoin::class, 'accounts', AccountTimed::class, 'object', Account::class);
            
            $this->acctlims = AccountTimed::LoadByQuery($this->database, $q);
            
            foreach ($this->GetGroup()->GetDefaultAccounts() as $account)
            {
                $acctlim = AccountTimed::LoadByAccount($this->database, $account, false);
                if ($acctlim !== null) $this->acctlims[$acctlim->ID()] = $acctlim;
            }
        }
        
        return $this->acctlims;
    }
    
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            $aclim = AccountTimed::LoadByGroup($database, $group, $this->GetTimePeriod());
            if ($aclim === null || !isset($this->acctlims)) return;
            
            if ($added) $this->acctlims[$aclim->ID()] = $aclim;
            else unset($this->acctlims[$aclim->ID()]);
        });
    }

    public static function LoadByGroup(ObjectDatabase $database, Group $group, int $period) : ?self
    {
        return static::LoadByClientAndPeriod($database, $group, $period);
    }
    
    public static function LoadAllForGroup(ObjectDatabase $database, Group $group) : array
    {
        return static::LoadAllForClient($database, $group);
    }

    protected function Initialize() : self
    {
        // force create rows for each account
        foreach ($this->GetGroup()->GetAccounts() as $account)
            AccountTimed::ForceLoadByAccount($this->database, $account, $this->GetTimePeriod());
        
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

Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
{
    foreach (GroupTimed::LoadAllForGroup($database, $group) as $lim)
        $lim->ProcessAccountChange(array($account), $added);
});

