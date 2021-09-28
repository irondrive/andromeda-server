<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/Folder.php");

/** A subfolder has a parent */
class SubFolder extends Folder
{
    public static function GetObjClass(array $row) : string { return self::class; }
    
    public function CanRefreshDelete() : bool { return true; }
    
    public function GetName() : string { return $this->GetScalar('name'); }
    public function GetParent() : Folder { return $this->GetObject('parent'); }
    public function GetParentID() : string { return $this->GetObjectID('parent'); }

    public function SetName(string $name, bool $overwrite = false) : self
    {
        parent::CheckName($name, $overwrite, false);
        
        $this->GetFSImpl()->RenameFolder($this, $name);
        return $this->SetScalar('name', $name);
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($parent);
        parent::CheckParent($parent, $overwrite, false); 
        
        $this->GetFSImpl()->MoveFolder($this, $parent);
        return $this->SetObject('parent', $parent);
    }
    
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self
    {
        $folder = parent::CheckName($name, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren(false);

        $folder ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl()->CopyFolder($this, $folder); return $folder;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($parent);
        
        $folder = parent::CheckParent($parent, $overwrite, true);
        if ($folder !== null) $folder->DeleteChildren(false);
    
        $folder ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());
        
        $this->GetFSImpl()->CopyFolder($this, $folder); return $folder;
    } 
    
    /**
     * Creates a new non-root folder in DB only
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of this folder
     * @param Account $account the owner of this folder (or null)
     * @param string $name the name of this folder
     * @return $this
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
        $isNotify = $this->GetParent()->isNotifyDeleted();
        
        $this->DeleteChildren($isNotify);
        
        if (!$isNotify) $this->GetFSImpl(false)->DeleteFolder($this);
            
        parent::Delete();
    }    
}
