<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");

trait FilesystemCommon
{
    protected static function GetObjectClass() : string { return FSManager::class; }
    protected function GetFilesystem() : FSManager { return $this->GetObject('object'); }    
    
    public static function GetBaseUsage() : string { return "[--track_items bool] [--track_dlstats bool]"; }
    
    protected function SetBaseLimits(Input $input) : void
    {
        if ($input->HasParam('track_items') || $this->isCreated()) $this->SetFeature('track_items', $input->GetParam('track_items', SafeParam::TYPE_BOOL));
        if ($input->HasParam('track_dlstats') || $this->isCreated()) $this->SetFeature('track_dlstats', $input->GetParam('track_dlstats', SafeParam::TYPE_BOOL));
    }
        
    public static function ConfigLimits(ObjectDatabase $database, FSManager $filesystem, Input $input) : self
    {
        return static::BaseConfigLimits($database, $filesystem, $input);
    }
}


class FilesystemTotal extends Total           
{ 
    use FilesystemCommon; 

    public static function LoadByFilesystem(ObjectDatabase $database, FSManager $filesystem) : ?self
    {
        return static::LoadByClient($database, $filesystem);
    }
    
    protected function Initialize() : self
    {
        if (!$this->canTrackItems()) return $this;
        
        $roots = Folder::LoadRootsByFSManager($this->database, $this->GetFilesystem());
        $this->CountSize(array_sum(array_map(function(Folder $folder){ return $folder->GetSize(); }, $roots)),true);
        $this->CountItems(array_sum(array_map(function(Folder $folder){ return $folder->GetNumItems(); }, $roots)));
        $this->CountShares(array_sum(array_map(function(Folder $folder){ return $folder->GetTotalShares(); }, $roots)));
        
        return $this;
    }
}


class FilesystemTimed extends Timed 
{ 
    use FilesystemCommon; 

    public static function LoadAllForFilesystem(ObjectDatabase $database, FSManager $filesystem) : array
    {
        return static::LoadAllForClient($database, $filesystem);
    }    
    
    public static function GetTimedUsage() : string { return "[--max_stats_age -1|0|int]"; }
    
    protected function SetTimedLimits(Input $input) : void
    {
        if ($input->HasParam('max_stats_age') || $this->isCreated()) $this->SetScalar('max_stats_age', $input->GetParam('max_stats_age', SafeParam::TYPE_INT));
    }
}

