<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");
require_once(ROOT."/apps/files/limits/Account.php");

trait GroupT
{
    public function GetGroup() : Group  { return $this->GetObject('object'); }
    public function GetPriority() : int { return $this->GetGroup()->GetPriority(); }
    
    // the group's limits apply only to its component accounts
    protected function IsCounterOverLimit(string $name, int $delta = 0) : bool { return false; }
    
    public function AddAccountCounts(array $accounts, bool $add = true) : void
    {
        $mul = $add ? 1 : -1;
        $this->CountDownloads($mul*array_sum(array_map(function(Base $lim){ return $lim->GetDownloads(); }, $accounts)));
        $this->CountBandwidth($mul*array_sum(array_map(function(Base $lim){ return $lim->GetBandwidth(); }, $accounts)));
        $this->CountSize($mul*array_sum(array_map(function(Base $lim){ return $lim->GetSize(); }, $accounts)),true);
        $this->CountItems($mul*array_sum(array_map(function(Base $lim){ return $lim->GetItems(); }, $accounts)));
        $this->CountShares($mul*array_sum(array_map(function(Base $lim){ return $lim->GetShares(); }, $accounts)));
    }
}

Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added){
    $gl = GroupTotal::LoadByGroup($database, $group); if ($gl !== null) $gl->AddAccountCounts(array($account), $added);
    foreach (GroupTimed::LoadAllForGroup($database, $group) as $lim) $lim->AddAccountCounts(array($account), $added);
});

class GroupTotal extends Total          
{ 
    use GroupT;

    public static function LoadByGroup(ObjectDatabase $database, Group $group) : ?self
    {
        return static::LoadByClient($database, $group);
    }
    
    protected function GetAccounts() : array 
    {
        return array_filter(array_map(function(Account $account){
            return AccountTotal::LoadByAccount($this->database, $account, true); },  // TODO load by join?
        $this->GetGroup()->GetAccounts()));
    }    
    
    protected function FinalizeCreate() : Total
    {
        if (!$this->canTrackItems()) return $this;
        $this->AddAccountCounts($this->GetAccounts());
        return $this;
    }
}

class GroupTimed extends Timed
{ 
    use GroupT;

    public static function LoadByGroup(ObjectDatabase $database, Group $group, int $period) : ?self
    {
        return static::LoadByClientAndPeriod($database, $group, $period);
    }
    
    public static function LoadAllForGroup(ObjectDatabase $database, Group $group) : array
    {
        return static::LoadAllForClient($database, $group);
    }
    
    protected function GetAccounts() : array
    {
        return array_filter(array_map(function(Account $account){
            return AccountTimed::LoadByAccount($this->database, $account, $this->GetTimePeriod()); },  // TODO load by join?
        $this->GetGroup()->GetAccounts()));
    }
}

