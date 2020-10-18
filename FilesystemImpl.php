<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Transactions;

require_once(ROOT."/apps/files/Filesystem.php");
require_once(ROOT."/apps/files/File.php");
require_once(ROOT."/apps/files/Folder.php");

abstract class FilesystemImpl implements Transactions
{
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
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
    
    public abstract function RenameFile(File $file, string $name) : self;
    public abstract function RenameFolder(Folder $folder, string $name) : self;
    public abstract function MoveFile(File $file, Folder $parent) : self;
    public abstract function MoveFolder(Folder $folder, Folder $parent) : self;
    
    public function commit() { return $this->GetStorage()->commit(); }
    public function rollback() { return $this->GetStorage()->rollback(); }
}
