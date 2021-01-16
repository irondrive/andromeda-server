<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");

class InapplicableFeatureException extends Exceptions\ServerException { public $message = "FEATURE_NOT_APPLICABLE"; }

trait FilesystemT
{
    protected function GetFilesystem() : FSManager { return $this->GetObject('object'); }

    public function GetAllowUserStorage() : bool { throw new InapplicableFeatureException(); }
    public function GetAllowEmailShare() : bool { throw new InapplicableFeatureException(); }
}

class FilesystemTotal extends Total           
{ 
    use FilesystemT; 

    public static function LoadByFilesystem(ObjectDatabase $database, FSManager $filesystem) : ?self
    {
        return static::LoadByClient($database, $filesystem);
    }
    
    protected function FinalizeCreate() : Total
    {
        if (!$this->canTrackItems()) return $this;
        
        $roots = Folder::LoadRootsByFSManager($this->database, $this->GetFilesystem());
        $this->CountSize(array_sum(array_map(function(Folder $folder){ return $folder->GetSize(); }, $roots)),true);
        $this->CountItems(array_sum(array_map(function(Folder $folder){ return $folder->GetNumItems(); }, $roots)));
        // TODO count shares (could do by a join)
        
        return $this;
    }
    
    public static function SetLimits(ObjectDatabase $database, FSManager $filesystem, Input $input) : self // TODO
    {
        
    }
}

class FilesystemTimed extends Timed 
{ 
    use FilesystemT; 

    public static function LoadAllForFilesystem(ObjectDatabase $database, FSManager $filesystem) : array
    {
        return static::LoadAllForClient($database, $filesystem);
    }
}

