<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/files/FilesystemImpl.php");
require_once(ROOT."/apps/files/Filesystem.php");

class InvalidScannedItemException extends Exceptions\ServerException { public $message = "SCANNED_ITEM_INVALID"; }

class Shared extends FilesystemImpl
{
    private function GetItemPath(?Item $item) : string
    {
        $parent = $item->GetParent();
        if ($parent === null) return "";
        else return $this->GetItemPath($parent).'/'.$item->GetName();
    }
    
    private function GetFilePath(File $file) : string { return $this->GetItemPath($file); }
    private function GetFolderPath(Folder $folder) : string { return $this->GetItemPath($folder).'/'; }
    
    public function RefreshFile(File $file, ?string $path = null) : self
    {
        $storage = $this->GetStorage();        
        if ($path === null)
        {
            $path = $this->GetFilePath($file);
            if (!$storage->isFile($path)) { $file->NotifyDelete(); return $this; }
        }

        $file->SetAccessed($storage->GetATime($path))
             ->SetCreated($storage->GetCTime($path))
             ->SetModified($storage->GetMTime($path))
             ->SetSize($storage->GetSize($path))->Save();        
        return $this;
    }
    
    public function RefreshFolder(Folder $folder, bool $doContents = true, ?string $path = null) : self
    {
        $storage = $this->GetStorage();
        if ($path === null)
        {
            $path = $this->GetFolderPath($folder);
            if (!$storage->isFolder($path)) { $folder->NotifyDelete(); return $this; }
        }
 
        $folder->SetAccessed($storage->GetATime($path))
               ->SetCreated($storage->GetCTime($path))
               ->SetModified($storage->GetMTime($path))->Save();

        if ($doContents) 
            $this->RefreshFolderContents($folder, $path);         
        return $this;        
    }
    
    private function RefreshFolderContents(Folder $folder, string $path) : void
    {
        $storage = $this->GetStorage();
        $fsitems = $storage->ReadFolder($path);
        if ($fsitems === null) { $folder->NotifyDelete(); return; }

        $dbitems = array_merge($folder->GetFiles(), $folder->GetFolders());
        
        foreach ($fsitems as $fsitem)
        {
            // TODO FUTURE could use SQL order by to make this a lot faster (scandir is already sorted)
            // just add ORDER BY to the API like you have $limit

            $fspath = $path.$fsitem;
            $isfile = $storage->isFile($fspath);
            if (!$isfile && !$storage->isFolder($fspath)) 
                throw new InvalidScannedItemException();
            
            $dbitem = null;
            foreach (array_keys($dbitems) as $id)
            {
                $dbitemtmp = $dbitems[$id];                
                if ($dbitemtmp->GetName() === $fsitem)
                {
                    $dbitem = $dbitemtmp; unset($dbitems[$id]); break;
                }
            }
            
            if ($dbitem === null)
            {
                $itemclass = $isfile ? File::class : Folder::class;
                $dbitem = $itemclass::NotifyCreate($this->GetDatabase(), $folder, $folder->GetOwner(), $fsitem);       
            }
            
            $dbitem->Refresh();
        }
        
        foreach ($dbitems as $dbitem) $dbitem->NotifyDelete();
    }
    
    public function CreateFolder(Folder $folder) : self
    {
        $path = $this->GetFolderPath($folder);
        $this->GetStorage()->CreateFolder($path);
        return $this;
    }

    public function DeleteFolder(Folder $folder) : self
    {
        $path = $this->GetFolderPath($folder);
        $this->GetStorage()->DeleteFolder($path);
        return $this;
    }
    
    public function ImportFile(File $file, string $path) : self
    {
        $dest = $this->GetFilePath($file);
        $this->GetStorage()->ImportFile($path, $dest);
        return $this;
    }
    
    public function DeleteFile(File $file) : self
    {
        $path = $this->GetFilePath($file);
        $this->GetStorage()->DeleteFile($path);
        return $this;
    }
    
    public function ReadBytes(File $file, int $start, int $length) : string
    {
        $path = $this->GetFilePath($file);
        return $this->GetStorage()->ReadBytes($path, $start, $length);
    }
    
    public function RenameFile(File $file, string $name) : self
    { 
        $oldpath = $this->GetFilePath($file);
        $newpath = $this->GetFolderPath($file->GetParent()).$name;
        $this->GetStorage()->RenameFolder($oldpath, $newpath);
        return $this;
    }
    
    public function RenameFolder(Folder $folder, string $name) : self
    { 
        $oldpath = $this->GetFolderPath($folder);
        $newpath = $this->GetFolderPath($folder->GetParent()).$name;
        $this->GetStorage()->RenameFolder($oldpath, $newpath);
        return $this;
    }
    
    public function MoveFile(File $file, Folder $parent) : self
    { 
        $path = $this->GetFilePath($file);
        $dest = $this->GetFolderPath($parent).$file->GetName();
        $this->GetStorage()->MoveFile($path, $dest);
        return $this;
    }
    
    public function MoveFolder(Folder $folder, Folder $parent) : self
    { 
        $path = $this->GetFolderPath($folder);
        $dest = $this->GetFolderPath($parent).$folder->GetName();
        $this->GetStorage()->MoveFolder($path, $dest);
        return $this;
    }
    
    public function commit() { }
    public function rollback() { }
}
