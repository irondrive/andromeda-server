<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\{Account, GroupInherit};
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\GroupJoin;

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;
require_once(ROOT."/apps/files/Item.php"); use Andromeda\Apps\Files\Item;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");
require_once(ROOT."/apps/files/limits/AuthObj.php");
require_once(ROOT."/apps/files/limits/Group.php");

trait AccountCommon
{
    use GroupInherit;
    
    protected static function GetObjectClass() : string { return Account::class; }
    protected function GetAccountID() : string { return $this->GetObjectID('object'); }
    protected function GetAccount() : Account { return $this->GetObject('object'); }

    // want to show the actual limited object to the client, not the limiters
    protected function TryGetInheritsScalarFrom(string $field) : ?BaseObject
    {
        $obj = $this->TryGetInheritable($field)->GetSource();
        return ($obj !== null) ? $obj->GetLimitedObject() : null;
    }
    
    public function GetsMaxStatsAgeFrom() : ?BaseObject
    {
        return $this->TryGetInheritable('max_stats_age')->GetSource();
    }
        
    public function GetClientObject(bool $isadmin = false) : array
    {
        $data = parent::GetClientObject();
        
        if ($isadmin)
        {
            $data['features_from'] = $this->ToInheritsFromClient([$this,'GetAllFeatures']);
            $data['limits_from'] = $this->ToInheritsFromClient([$this,'GetAllCounterLimits']);            
        }
        
        return $data;
    }

    public static function GetBaseUsage() : string { return "[--track_items ?bool] [--track_dlstats ?bool]"; }
    
    protected function SetBaseLimits(Input $input) : void
    {
        if ($input->HasParam('track_items')) $this->SetFeature('track_items', $input->TryGetParam('track_items', SafeParam::TYPE_BOOL));
        if ($input->HasParam('track_dlstats')) $this->SetFeature('track_dlstats', $input->TryGetParam('track_dlstats', SafeParam::TYPE_BOOL));
    }    
    
    public static function ConfigLimits(ObjectDatabase $database, Account $account, Input $input) : self
    {
        return static::BaseConfigLimits($database, $account, $input);
    }    

    public function ProcessGroupRemove(GroupTotal $grlim) : void
    {
        // see if the account has any properties specific to it
        foreach (array_keys($this->GetInheritedProperties()) as $field)
        {
            if ($this->TryGetInheritsScalarFrom($field) === $this) return;
        }
        
        // see if the account is subject to any other group limits
        foreach ($this->GetGroups() as $grlim2)
            if ($grlim2 !== $grlim) return;
        
        $this->Delete();
    }
}


class AccountTotal extends AuthTotal  
{ 
    use AccountCommon;
    
    protected array $grouplims;

    // load group limits via join and cache
    protected function GetGroups() : array
    {
        if (!isset($this->grouplims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->Equals($this->database->GetClassTableName(GroupJoin::class).'.accounts', $this->GetAccountID()))
                ->Join($this->database, GroupJoin::class, 'groups', GroupTotal::class, 'object', Group::class);
            
            $this->grouplims = GroupTotal::LoadByQuery($this->database, $q);
            
            foreach ($this->GetAccount()->GetDefaultGroups() as $group)
            {
                $grouplim = GroupTotal::LoadByGroup($this->database, $group);
                if ($grouplim !== null) $this->grouplims[$grouplim->ID()] = $grouplim;
            }
        }
        
        return $this->grouplims;
    }
    
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            $grlim = GroupTotal::LoadByGroup($database, $group);
            if ($grlim === null || !isset($this->grouplims)) return;
            
            if ($added) $this->grouplims[$grlim->ID()] = $grlim;
            else unset($this->grouplims[$grlim->ID()]);
        });
    }

    protected function GetInheritedFields() : array { return array(
        'features__itemsharing' => true,
        'features__shareeveryone' => false,
        'features__emailshare' => true,
        'features__publicupload' => false,
        'features__publicmodify' => false,
        'features__randomwrite' => true,
        'features__userstorage' => false,
        'features__track_items' => false,
        'features__track_dlstats' => false,
        'counters_limits__size' => null,
        'counters_limits__items' => null,
        'counters_limits__shares' => null,
    ); }
    
    public function GetAllowRandomWrite() : bool { return $this->GetFeature('randomwrite'); }
    public function GetAllowPublicModify() : bool { return $this->GetFeature('publicmodify'); }
    public function GetAllowPublicUpload() : bool { return $this->GetFeature('publicupload'); }
    public function GetAllowItemSharing() : bool { return $this->GetFeature('itemsharing'); }
    public function GetAllowShareEveryone() : bool { return $this->GetFeature('shareeveryone'); }
    
    public function GetAllowEmailShare() : bool { return $this->GetFeature('emailshare'); }
    public function GetAllowUserStorage() : bool { return $this->GetFeature('userstorage'); }
    
    public static function LoadByAccount(ObjectDatabase $database, ?Account $account, bool $require = true) : ?self
    {
        $obj = ($account !== null) ? $obj = static::LoadByClient($database, $account) : null;
        
        // optionally return a fake object so the caller can get default limits/features
        if ($obj === null && $require) $obj = new AccountTotalDefault($database);
        
        return $obj;
    }
    
    public static function ForceLoadByAccount(ObjectDatabase $database, Account $account) : self
    {
        return static::LoadByClient($database, $account) ?? static::Create($database, $account)->Initialize();
    }
    
    public static function LoadByAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $retval = array();
        
        $aclim = static::LoadByAccount($database, $account, false);
        
        if ($aclim !== null)
        {
            $retval = $aclim->GetGroups();
            $retval[$aclim->ID()] = $aclim;
        }        
        return $retval;
    }
  
    protected function Initialize() : self
    {
        if (!$this->canTrackItems()) return $this;
        
        $files = File::LoadByOwner($this->database, $this->GetAccount());
        $folders = Folder::LoadByOwner($this->database, $this->GetAccount());        
        $this->CountItems(count($files) + count($folders));
        
        $files_global = array_filter($files, function(File $file){ return !$file->onOwnerFS(); });
        $this->CountSize(array_sum(array_map(function(File $file){ return $file->GetSize(); }, $files_global)));  
        
        $this->CountShares(array_sum(array_map(function(Item $item){ return $item->GetNumShares(); }, array_merge($files,$folders))));
                
        return $this;
    }
}

class AccountTotalDefault extends AccountTotal
{
    public function __construct(ObjectDatabase $database) { parent::__construct($database, array()); }
    
    protected function GetGroups() : array { return array(); }
}

Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
{
    $grlim = GroupTotal::LoadByGroup($database, $group); if ($grlim === null) return;
    
    $aclim = AccountTotal::ForceLoadByAccount($database, $account);
    
    if (!$added) $aclim->ProcessGroupRemove($grlim);
});


class AccountTimed extends AuthTimed 
{
    use AccountCommon;
    
    protected array $grouplims;
    
    // load group limits via join and cache
    protected function GetGroups() : array
    {
        if (!isset($this->grouplims))
        {
            $q = new QueryBuilder();
            
            $q->Where($q->And($q->Equals('timeperiod',$this->GetTimePeriod()),$q->Equals($this->database->GetClassTableName(GroupJoin::class).'.accounts', $this->GetAccountID())))
                ->Join($this->database, GroupJoin::class, 'groups', GroupTimed::class, 'object', Group::class);
            
            $this->grouplims = GroupTimed::LoadByQuery($this->database, $q);
            
            foreach ($this->GetAccount()->GetDefaultGroups() as $group)
            {
                $grouplim = GroupTimed::LoadByGroup($this->database, $group, $this->GetTimePeriod());
                if ($grouplim !== null) $this->grouplims[$grouplim->ID()] = $grouplim;
            }
        }
        
        return $this->grouplims;
    }
    
    protected function SubConstruct() : void
    {
        Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
        {
            $grlim = GroupTimed::LoadByGroup($database, $group, $this->GetTimePeriod());
            if ($grlim === null || !isset($this->grouplims)) return;
            
            if ($added) $this->grouplims[$grlim->ID()] = $grlim;
            else unset($this->grouplims[$grlim->ID()]);
        });
    }
    
    protected function GetInheritedFields() : array { return array(
        'max_stats_age' => null,
        'features__track_items' => false,
        'features__track_dlstats' => false,
        'counters_limits__downloads' => null,
        'counters_limits__bandwidth' => null
    ); }
    
    public static function ForceLoadByAccount(ObjectDatabase $database, Account $account, int $period) : self
    {
        $obj = static::LoadByClientAndPeriod($database, $account, $period);        
        $obj ??= static::CreateTimed($database, $account, $period);        
        return $obj;
    }

    public static function LoadAllForAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $retval = static::LoadAllForClient($database, $account);
        
        foreach ($retval as $aclim)
        {
            $retval = array_merge($retval, $aclim->GetGroups());
        }
        
        return $retval;
    }
}

Account::RegisterGroupChangeHandler(function(ObjectDatabase $database, Account $account, Group $group, bool $added)
{
    foreach (GroupTimed::LoadAllForGroup($database, $group) as $grlim)
    {
        $aclim = AccountTimed::ForceLoadByAccount($database, $account, $grlim->GetTimePeriod());
        
        if (!$added) $aclim->ProcessGroupRemove($grlim);
    }
});
