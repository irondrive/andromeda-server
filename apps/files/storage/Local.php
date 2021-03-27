<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

class LocalNonAdminException extends ActivateException { public $message = "LOCAL_STORAGE_ADMIN_ONLY"; }

FSManager::RegisterStorageType(Local::class);

abstract class LocalBase extends FWrapper { use BasePath; }

/** 
 * A storage on local-disk on the server
 * 
 * Only admin can add storages of this type!
 */
class Local extends LocalBase
{
    public function Activate() : self { return $this; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $account = $filesystem->GetOwner();
        if ($account && !$account->isAdmin()) 
            throw new LocalNonAdminException();
        
        else return parent::Create($database, $input, $filesystem);
    }

    protected function UseChunks() : bool { return false; }
    
    public function canGetFreeSpace() : bool { return true; }
    
    public function usesBandwidth() : bool { return false; }
    
    public function GetFreeSpace() : int
    {
        $space = disk_free_space($this->GetPath());
        if ($space === false) throw new FreeSpaceFailedException();
        else return $space;
    }

    protected function GetFullURL(string $path = "") : string
    {
        return $this->GetPath($path);
    }
    
    /**
     * Import the file quickly by just renaming it
     * @see FWrapper::ImportFile()
     */
    protected function SubImportFile(string $src, string $dest) : self
    {
        if (!rename($src, $this->GetFullURL($dest)))
            throw new FileCreateFailedException();
        return $this;
    }
}