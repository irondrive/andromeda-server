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

        $this->AddCountsToParent($this->GetParent(),add:false);
        $retval = $this->parent->SetObject($parent);
        $this->AddCountsToParent($this->GetParent(),add:true);
        
        $this->GetFilesystem()->MoveFolder($this, $parent);
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

        $folder ??= static::CreateItem($this->database, $this->GetParent(), $owner, $name);
        
        $this->AddCountsToParent($this->GetParent());
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
        // TODO RAY !! if reusing, also need to subtract counts? though not recursively as children will get deleted? or just remove concept here and don't solve

        $folder ??= static::CreateItem($this->database, $parent, $owner, $this->GetName());
        
        $this->AddCountsToParent($parent);
        $this->GetFilesystem()->CreateFolder($folder); 
        
        $this->CopyToFolder($folder);
        return $folder;
    }
    
    /** Creates a new non-root folder both in DB and on disk */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : static
    {
        if (static::TryLoadByParentAndName($database, $parent, $name) !== null)
            throw new Exceptions\DuplicateItemException();

        $folder = static::CreateItem($database, $parent, $account, $name);
        
        $folder->AddCountsToParent($parent);
        $folder->GetFilesystem()->CreateFolder($folder);
        return $folder;
    }

    public function NotifyPreDeleted() : void
    {
        parent::NotifyPreDeleted();

        if (!$this->isFSDeleted()) // TODO RAY !! probably can just do recursive content counts? at this point, subcontent is deleted
            $this->GetParent()->AddFolderCounts($this, add:false); // NOT content counts

        /*$this->MapToLimits(function(Policy\Base $lim){
            if (!$this->onOwnerStorage()) $lim->CountSize($this->GetSize()*-1); });*/ // TODO POLICY
    }

    public function NotifyPostDeleted() : void
    {
        parent::NotifyPostDeleted();

        // NOTE we don't do this for RootFolder
        if (!$this->isFSDeleted())
            $this->GetFilesystem()->DeleteFolder($this);
    }
}
