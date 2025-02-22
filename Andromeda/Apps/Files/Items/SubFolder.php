<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Apps\Accounts\Account;

/** A subfolder has a parent */
class SubFolder extends Folder
{
    use TableTypes\NoChildren;

    public function SetName(string $name, bool $overwrite = false) : bool
    {
        static::CheckName($name, $overwrite, false);
        
        $this->GetFilesystem()->RenameFolder($this, $name);
        return $this->name->SetValue($name);
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : bool
    {
        $this->CheckNotChildOrSelf($parent);
        static::CheckParent($parent, $overwrite, false); 
        
        $this->GetFilesystem()->MoveFolder($this, $parent);
        return $this->parent->SetObject($parent);
    }

    /**
     * Copy to a folder by copying our individual contents
     * @param Folder $dest new object for destination
     */
    protected function CopyToFolder(Folder $dest) : void
    {
        foreach ($this->GetFiles() as $item)
            $item->CopyToParent($dest->TryGetOwner(), $dest);
        
        foreach ($this->GetFolders() as $item)
            $item->CopyToParent($dest->TryGetOwner(), $dest);
    }
    
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self
    {
        $folder = static::CheckName($name, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren();

        $folder ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFilesystem(false)->CreateFolder($folder); 
        
        $this->CopyToFolder($folder);
        return $folder;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self
    {
        $this->CheckNotChildOrSelf($parent);
        
        $folder = static::CheckParent($parent, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren();
    
        $folder ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());
        
        $this->GetFilesystem(false)->CreateFolder($folder);
        
        $this->CopyToFolder($folder);
        return $folder;
    }
    
    /**
     * Creates a new non-root folder both in DB and on disk
     * @see Folder::NotifyCreate()
     */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        if (static::TryLoadByParentAndName($database, $parent, $name) !== null)
            throw new Exceptions\DuplicateItemException();

        $folder = static::NotifyCreate($database, $parent, $account, $name);
        
        $folder->GetFilesystem(false)->CreateFolder($folder);
        return $folder;
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void // TODO RAY re-do all deletes
    {
        if ($this->GetParent()->isFSDeleted())
            { $this->NotifyFSDeleted(); return; }
        
        if (!$this->isDeleted())
        {
            // calls refresh, might delete
            $this->DeleteChildren();
            
            if (!$this->isDeleted())
            {
                $this->GetFilesystem(false)->DeleteFolder($this);
            }
        }

        parent::Delete();
    }    
}
