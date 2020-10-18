<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/FilesystemImpl.php");
require_once(ROOT."/apps/files/Filesystem.php");

class Native extends FilesystemImpl
{
    public function RefreshFile(File $file) : self { return $this; }
    public function RefreshFolder(Folder $folder) : self { return $this; }    
    public function CreateFolder(Folder $folder) : self { return $this; }
    public function DeleteFolder(Folder $folder) : self { return $this; }    
    public function RenameFile(File $file, string $name) : self { return $this; }
    public function RenameFolder(Folder $folder, string $name) : self { return $this; }
    public function MoveFile(File $file, Folder $parent) : self { return $this; }
    public function MoveFolder(Folder $folder, Folder $parent) : self { return $this; }
 
    // TODO FUTURE hierarchy of files by prefix folders? like git
    private function GetFilePath(File $file) : string { return $file->ID(); }
    
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
}
