<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes};
use Andromeda\Apps\Accounts\Account;

/** A subfolder has a parent */
class SubFolder extends Folder
{
    use TableTypes\NoChildren;

    public function SetName(string $name, bool $overwrite = false) : bool
    {
        static::CheckName($name, $overwrite, reuse:false);
        
        $this->GetFilesystem()->RenameFolder($this, $name);
        $retval = $this->name->SetValue($name);
        $this->Save(); // FS is changed, save now for accurate loads
        return $retval;
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : bool
    {
        $this->AssertNotChildOrSelf($parent);
        static::CheckParent($parent, $overwrite, reuse:false); 
        
        $this->GetFilesystem()->MoveFolder($this, $parent);
        $retval = $this->parent->SetObject($parent);
        $this->Save(); // FS is changed, save now for accurate loads
        return $retval;
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
    
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static
    {
        $folder = static::CheckName($name, $overwrite, reuse:true);
        //if ($folder !== null) $folder->DeleteChildren();// TODO RAY !! see below

        $folder ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFilesystem()->CreateFolder($folder); 
        
        $this->CopyToFolder($folder);
        return $folder;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : static
    {
        $this->AssertNotChildOrSelf($parent);
        
        $folder = static::CheckParent($parent, $overwrite, reuse:true);
        //if ($folder !== null) $folder->DeleteChildren();
        // TODO RAY !! the whole reuse thing seems stupid, why would you ever want to do that?
        // in fact the only case I can see is when uploading a new file over an old one, and we currently don't reuse for that, only in Copy
    
        $folder ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());
        
        $this->GetFilesystem()->CreateFolder($folder);
        
        $this->CopyToFolder($folder);
        return $folder;
    }
    
    /** Creates a new non-root folder both in DB and on disk */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : static
    {
        if (static::TryLoadByParentAndName($database, $parent, $name) !== null)
            throw new Exceptions\DuplicateItemException();

        $folder = static::NotifyCreate($database, $parent, $account, $name);
        
        $folder->GetFilesystem()->CreateFolder($folder);
        return $folder;
    }

    public function NotifyPostDeleted() : void
    {
        parent::NotifyPostDeleted();

        // NOTE we don't do this for RootFolder
        if (!$this->isFSDeleted())
            $this->GetFilesystem()->DeleteFolder($this);
    }
}
