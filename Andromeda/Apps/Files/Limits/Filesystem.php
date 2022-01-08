<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/RootFolder.php"); use Andromeda\Apps\Files\RootFolder;

require_once(ROOT."/Apps/Files/Limits/Total.php");
require_once(ROOT."/Apps/Files/Limits/Timed.php");

/** Filesystem limits common between total and timed */
trait FilesystemCommon
{
    protected static function GetObjectClass() : string { return FSManager::class; }
    
    /** Returns the limited filesystem */
    protected function GetFilesystem() : FSManager { return $this->GetObject('object'); }    
    
    public static function GetBaseUsage() : string { return "[--track_items bool] [--track_dlstats bool]"; }
    
    protected function SetBaseLimits(Input $input) : void
    {
        if ($input->HasParam('track_items') || $this->isCreated())
        {
            $this->SetFeatureBool('track_items', $input->GetParam('track_items', SafeParam::TYPE_BOOL));
            
            if ($this->isFeatureModified('track_items')) $init = true;
        }
        
        if ($input->HasParam('track_dlstats') || $this->isCreated())
        {
            $this->SetFeatureBool('track_dlstats', $input->GetParam('track_dlstats', SafeParam::TYPE_BOOL));
            
            if ($this->isFeatureModified('track_dlstats')) $init = true;
        }
        
        if ($init ?? false) $this->Initialize();
    }
        
    public static function ConfigLimits(ObjectDatabase $database, FSManager $filesystem, Input $input) : self
    {
        return static::BaseConfigLimits($database, $filesystem, $input);
    }
    
    /**
     * @param bool $full if false, don't show track_items/track_dlstats
     * @return array `config:{track_items:bool,track_dlstats:bool}`
     * @see Total::GetClientObject()
     * @see Timed::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $data = parent::GetClientObject($full);
        
        if ($full)
        {
            $data['config']['track_items'] = $this->GetFeatureBool('track_items');
            $data['config']['track_dlstats'] = $this->GetFeatureBool('track_dlstats');
        }
        
        return $data;
    }
}

/** Concrete class providing filesystem config and total stats */
class FilesystemTotal extends Total
{ 
    use FilesystemCommon; 

    /** Loads the total limit object for the given filesystem (or null if none exists) */
    public static function LoadByFilesystem(ObjectDatabase $database, FSManager $filesystem) : ?self
    {
        return static::LoadByClient($database, $filesystem);
    }
    
    /** Initializes the FS total stats by adding stats from all root folders */
    protected function Initialize() : self
    {
        parent::Initialize();
        
        if (!$this->canTrackItems()) return $this;
        
        $roots = RootFolder::LoadRootsByFSManager($this->database, $this->GetFilesystem());
        
        foreach ($roots as $root) $this->AddCumulativeFolderCounts($root,true);
        
        return $this;
    }
}

/** Concrete class providing timed filesystem limits */
class FilesystemTimed extends Timed 
{ 
    use FilesystemCommon; 

    /**
     * Loads all timed limits for the given filesystem
     * @param ObjectDatabase $database database reference
     * @param FSManager $filesystem filesystem of interest
     * @return array<string, FilesystemTimed> timed limits
     */
    public static function LoadAllForFilesystem(ObjectDatabase $database, FSManager $filesystem) : array
    {
        return static::LoadAllForClient($database, $filesystem);
    }    
    
    public static function GetTimedUsage() : string { return "[--max_stats_age ".static::MAX_AGE_FOREVER." (forever)|0 (none)|int]"; }
    
    protected function SetTimedLimits(Input $input) : void
    {
        if ($input->HasParam('max_stats_age')) 
        {
            $this->SetScalar('max_stats_age', $input->GetParam('max_stats_age', SafeParam::TYPE_INT));
        }
    }
}

