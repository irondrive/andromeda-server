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
    
    public function RefreshFile(File $file, ?string $path = null) : self
    {
        $storage = $this->GetStorage();        
        if ($path === null)
        {
            $path = $this->GetItemPath($file);
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
            $path = $this->GetItemPath($folder).'/';
            if (!$storage->isFolder($path)) { $folder->NotifyDelete(); return $this; }
        }
 
        $folder->SetAccessed($storage->GetATime($path))
               ->SetCreated($storage->GetCTime($path))
               ->SetModified($storage->GetMTime($path))->Save();

        if ($doContents) $this->RefreshFolderContents($folder, $path);
        
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
                $dbitem = $itemclass::Create($this->GetDatabase(), $folder, $folder->GetOwner(), $fsitem, true);       
            }
            
            if ($isfile) $this->RefreshFile($dbitem, $fspath);
                  else $this->RefreshFolder($dbitem, false, $fspath);
        }
        
        foreach ($dbitems as $dbitem) $dbitem->NotifyDelete();
    }
    
    public function CreateFolder(Folder $folder) : self
    {
        $path = $this->GetItemPath($folder).'/';
        $this->GetStorage()->CreateFolder($path);
        return $this;
    }

    public function DeleteFolder(Folder $folder) : self
    {
        $path = $this->GetItemPath($folder).'/';
        $this->GetStorage()->DeleteFolder($path);
        return $this;
    }
    
    public function DeleteFile(File $file) : self
    {
        $path = $this->GetItemPath($file);
        $this->GetStorage()->DeleteFile($path);
        return $this;
    }
    
    public function commit() { }
    public function rollback() { }
}
