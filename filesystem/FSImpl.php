<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Transactions;

require_once(ROOT."/apps/files/filesystem/FSManager.php");

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

abstract class FSImpl implements Transactions
{
    public function __construct(FSManager $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    public function GetChunkSize() : ?int { return null; }
    
    protected function GetStorage(){ return $this->filesystem->GetStorage(); }
    protected function GetAccount(){ return $this->filesystem->GetAccount(); }
    protected function GetDatabase(){ return $this->filesystem->GetDatabase(); }
    
    public abstract function RefreshFile(File $file) : self;
    public abstract function RefreshFolder(Folder $folder) : self;
    
    public abstract function CreateFolder(Folder $folder) : self;
    public abstract function DeleteFolder(Folder $folder) : self;

    public abstract function ImportFile(File $file, string $path) : self;
    public abstract function DeleteFile(File $file) : self;
    
    public abstract function ReadBytes(File $file, int $start, int $length) : string;
    public abstract function WriteBytes(File $file, int $start, string $data) : self;
    public abstract function Truncate(File $file, int $length) : self;
    
    public abstract function RenameFile(File $file, string $name) : self;
    public abstract function RenameFolder(Folder $folder, string $name) : self;
    public abstract function MoveFile(File $file, Folder $parent) : self;
    public abstract function MoveFolder(Folder $folder, Folder $parent) : self;
    
    public function commit() { return $this->GetStorage()->commit(); }
    public function rollback() { return $this->GetStorage()->rollback(); }
}
