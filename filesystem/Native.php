<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/filesystem/FSImpl.php");
require_once(ROOT."/apps/files/filesystem/FSManager.php");

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

abstract class BaseFileFS extends FSImpl
{
    protected abstract function GetFilePath(File $file) : string;
    
    public function ImportFile(File $file, string $path) : self
    {
        $this->GetStorage()->ImportFile($path, $this->GetFilePath($file)); return $this;
    }
    
    public function DeleteFile(File $file) : self
    {
        $this->GetStorage()->DeleteFile($this->GetFilePath($file)); return $this;
    }
    
    public function ReadBytes(File $file, int $start, int $length) : string
    {
        return $this->GetStorage()->ReadBytes($this->GetFilePath($file), $start, $length);
    }
    
    public function WriteBytes(File $file, int $start, string $data) : self
    {
        $this->GetStorage()->WriteBytes($this->GetFilePath($file), $start, $data); return $this;
    }
    
    public function Truncate(File $file, int $length) : self
    {
        $this->GetStorage()->Truncate($this->GetFilePath($file), $length); return $this;
    }
    
    public function CopyFile(File $file, File $dest) : self 
    {
        $this->GetStorage()->CopyFile($this->GetFilePath($file), $this->GetFilePath($dest)); return $this; 
    }
    
    protected function ManualCopyFolder(Folder $folder, Folder $dest) : self
    {
        $this->CreateFolder($dest);        
        foreach ($folder->GetFiles() as $item) $item->CopyToParent($dest);
        foreach ($folder->GetFolders() as $item) $item->CopyToParent($dest);        
        return $this;
    }
}

class Native extends BaseFileFS
{
    public function RefreshFile(File $file) : self { return $this; }
    public function RefreshFolder(Folder $folder) : self { return $this; }    
    public function CreateFolder(Folder $folder) : self { return $this; }
    public function DeleteFolder(Folder $folder) : self { return $this; }    
    public function RenameFile(File $file, string $name) : self { return $this; }
    public function RenameFolder(Folder $folder, string $name) : self { return $this; }    
    public function MoveFile(File $file, Folder $parent) : self { return $this; }
    public function MoveFolder(Folder $folder, Folder $parent) : self { return $this; }
    
    public function CopyFolder(Folder $folder, Folder $dest) : self
    {
        return $this->ManualCopyFolder($folder, $dest);
    }

    protected function GetFilePath(File $file) : string { return $file->ID(); }
}
