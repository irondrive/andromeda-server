<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Files/Folder.php");

/** A subfolder has a parent */
class SubFolder extends Folder
{
    /** @return class-string<self> */
    public static function GetObjClass(array $row) : string { return self::class; }
    
    public function GetName() : string { return $this->GetScalar('name'); }
    public function GetParent() : Folder { return $this->GetObject('parent'); }
    public function GetParentID() : string { return $this->GetObjectID('parent'); }

    public function SetName(string $name, bool $overwrite = false) : self
    {
        static::CheckName($name, $overwrite, false);
        
        $this->GetFSImpl()->RenameFolder($this, $name);
        return $this->SetScalar('name', $name);
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($parent);
        static::CheckParent($parent, $overwrite, false); 
        
        $this->GetFSImpl()->MoveFolder($this, $parent);
        return $this->SetObject('parent', $parent);
    }

    /**
     * Copy to a folder by copying our individual contents
     * @param Folder $dest new object for destination
     * @return $this
     */
    protected function CopyToFolder(Folder $dest) : self
    {
        foreach ($this->GetFiles() as $item)
            $item->CopyToParent($dest->GetOwner(), $dest);
        
        foreach ($this->GetFolders() as $item)
            $item->CopyToParent($dest->GetOwner(), $dest);
        
        return $this;
    }
    
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self
    {
        $folder = static::CheckName($name, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren();

        $folder ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl(false)->CreateFolder($folder); 
        
        $this->CopyToFolder($folder); return $folder;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($parent);
        
        $folder = static::CheckParent($parent, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren();
    
        $folder ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());
        
        $this->GetFSImpl(false)->CreateFolder($folder);
        
        $this->CopyToFolder($folder); return $folder;
    }
    
    /**
     * Creates a new non-root folder in DB only
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of this folder
     * @param Account $account the owner of this folder (or null)
     * @param string $name the name of this folder
     * @return static
     */
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        return parent::BaseCreate($database)
            ->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('parent',$parent)            
            ->SetObject('owner',$account)
            ->SetScalar('name',$name)->CountCreate();
    }
    
    /**
     * Creates a new non-root folder both in DB and on disk
     * @see Folder::NotifyCreate()
     */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        $folder = static::TryLoadByParentAndName($database, $parent, $name);
        if ($folder !== null) throw new DuplicateItemException();

        $folder = static::NotifyCreate($database, $parent, $account, $name);
        
        $folder->GetFSImpl(false)->CreateFolder($folder); return $folder;
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {
        if ($this->GetParent()->isFSDeleted())
            { $this->NotifyFSDeleted(); return; }
        
        if (!$this->isDeleted())
        {
            // calls refresh, might delete
            $this->DeleteChildren();
            
            if (!$this->isDeleted())
            {
                $this->GetFSImpl(false)->DeleteFolder($this);
            }
        }

        parent::Delete();
    }    
}
