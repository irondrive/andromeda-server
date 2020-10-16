<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/FilesystemImpl.php");
require_once(ROOT."/apps/files/Filesystem.php");

class Native extends FilesystemImpl
{
    public function RefreshFile(File $file) : self { return $this; }
    public function RefreshFolder(Folder $folder) : self { return $this; }
    
    public function CreateFolder(Folder $folder) : self { return $this; }   // TODO update modified date, item count of parents?
    public function DeleteFolder(Folder $folder) : self { return $this; }   // TODO update modified date, item count of parents?
    
    public function DeleteFile(File $file) : self { return $this; } // TODO FILE
}
