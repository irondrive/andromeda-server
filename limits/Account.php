<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\{Account, GroupInherit};
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");
require_once(ROOT."/apps/files/limits/Group.php");

trait AccountT
{
    use GroupInherit;
    
    protected function GetAccount() : Account { return $this->GetObject('object'); }
    
    // don't count storage usage for non-local (external user-added) storages
    public function CountSize(int $size, bool $global) : Base { 
        return $global ? parent::CountSize($size, $global) : $this; }
}

class AccountTotal extends Total  
{ 
    use AccountT;

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
    
    protected function GetGroups() : array { return static::StaticGetGroups($this->database, $this->GetAccount()); }
    
    protected static function StaticGetGroups(ObjectDatabase $database, Account $account) : array
    {
        return array_filter(array_map(function(Group $group)use($database){
            return GroupTotal::LoadByGroup($database, $group); },  // TODO load by join?
        $account->GetGroups()));
    }
    
    public static function LoadByAccount(ObjectDatabase $database, ?Account $account, bool $require = true) : ?self
    {
        $obj = null; if ($account !== null)
        {
            $obj = static::LoadByClient($database, $account);
            
            if ($obj === null)
            {
                // if any of the account's groups have a limit row, the account should also
                if (count(static::StaticGetGroups($database, $account)))
                    $obj = static::Create($database, $account)->FinalizeCreate();
            }
        }
        
        // optionally return a fake object so the caller can get default limits/features
        if ($obj === null && $require) $obj = new self($database, array());
        
        return $obj;
    }
    
    public static function LoadByAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $retval = static::StaticGetGroups($database, $account);
        
        $aclim = static::LoadByAccount($database, $account, false);
        if ($aclim !== null) array_push($retval, $aclim);
        
        return $retval;
    }    
    
    protected function FinalizeCreate() : Total
    {
        if (!$this->canTrackItems()) return $this;
        
        $files = File::LoadByOwner($this->database, $this->GetAccount());
        $folders = Folder::LoadByOwner($this->database, $this->GetAccount());
        
        $this->CountItems(count($files) + count($folders));
        
        $files_global = array_filter($files, function(File $file){ return $file->isGlobalFS(); });
        $this->CountSize(array_sum(array_map(function(File $file){ return $file->GetSize(); }, $files_global)), true);
        
        return $this;
        // TODO CountShares() + add this to things tracked by folders...
    }
}

class AccountTimed extends Timed 
{
    use AccountT;

    protected function GetInheritedFields() : array { return array(
        'features__history' => false,
        'features__track_items' => false,
        'features__track_dlstats' => false,
        'counters_limits__size' => null,
        'counters_limits__items' => null,
        'counters_limits__shares' => null,
    ); }
    
    protected function GetGroups() : array
    { 
        return array_filter(array_map(function(Group $group){
            return GroupTimed::LoadByGroup($this->database, $group, $this->GetTimePeriod()); },  // TODO load by join?
        $this->GetAccount()->GetGroups()));
    }
    
    protected static function LoadByAccount(ObjectDatabase $database, Account $account, int $period) : self
    {
        $obj = static::LoadByClientAndPeriod($database, $account, $period);        
        $obj ??= static::CreateTimed($database, $account, $period);        
        return $obj;
    }

    public static function LoadAllForAccountAll(ObjectDatabase $database, Account $account) : array
    {
        $grouplims = array(); $acctlims = array();

        foreach ($account->GetGroups() as $group)
        {
            // first, load the all of the account's group limits objects
            $grouplims = array_merge($grouplims, GroupTimed::LoadAllForGroup($database, $group));
        }        
        
        // for each group limit and time period, get an account object matching it
        foreach ($grouplims as $glim)
        {
            $alim = static::LoadByAccount($database, $account, $glim->GetTimePeriod());
            $acctlims[$alim->ID()] = $alim;
        }

        // load any other account limits not part of a group limit
        return array_merge($grouplims, $acctlims, static::LoadAllForClient($database, $account));
    }
}

