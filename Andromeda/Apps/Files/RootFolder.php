<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Files/Storage/Exceptions.php"); use Andromeda\Apps\Files\Storage\StorageException;
require_once(ROOT."/Apps/Files/Folder.php");

use Andromeda\Apps\Files\Filesystem\FSManager;

/** Exception indicating that the operation is not valid on a root folder */
class InvalidRootOpException extends Exceptions\ClientErrorException { public $message = "ROOT_FOLDER_OP_INVALID"; }

/** A root folder has no parent or name */
class RootFolder extends Folder
{
    public static function GetObjClass(array $row) : string { return self::class; }
    
    public function GetName() : string 
    { 
        return $this->GetFilesystem()->GetName(); 
    }
    
    public function SetName(string $name, bool $overwrite = false) : self 
    { 
        $this->GetFilesystem()->SetName($name); return $this; 
    }
    
    /** Returned if this root's filesystem is owned by $account */
    public function isFSOwnedBy(Account $account) : bool
    {
        return $this->GetFilesystem()->GetOwnerID() === $account->ID();
    }
    
    public function GetParent() : ?Folder { return null; }
    public function GetParentID() : ?string { return null; }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self { throw new InvalidRootOpException(); }

    /**
     * Loads the root folder for given account and FS, creating it if it doesn't exist
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner of the root folder
     * @param FSManager $filesystem the filesystem of the root, or null to get the default
     * @return self|NULL loaded folder or null if a default FS does not exist and none is given
     */
    public static function GetRootByAccountAndFS(ObjectDatabase $database, Account $account, ?FSManager $filesystem = null) : ?self
    {
        $filesystem ??= FSManager::LoadDefaultByAccount($database, $account); if (!$filesystem) return null;
        
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'),
            $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = static::TryLoadUniqueByQuery($database, $q->Where($where));
        if ($loaded) return $loaded;
        else
        {
            $owner = $filesystem->isExternal() ? $filesystem->GetOwner() : $account;
            
            return parent::BaseCreate($database)
                ->SetObject('filesystem',$filesystem)
                ->SetObject('owner',$owner)->Refresh();
        }
    }
    
    /**
     * Loads all root folders on the given filesystem
     * @param ObjectDatabase $database database reference
     * @param FSManager $filesystem the filesystem
     * @return array<string, FSManager> folders indexed by ID
     */
    public static function LoadRootsByFSManager(ObjectDatabase $database, FSManager $filesystem) : array
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'));
        
        return static::LoadByQuery($database, $q->Where($where));
    }
    
    /**
     * Load all root folders for the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account folder owner
     * @return array<string, FSManager> folders indexed by ID
     */
    public static function LoadRootsByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('owner',$account->ID()), $q->IsNull('parent'));
        
        return static::LoadByQuery($database, $q->Where($where));
    }
    
    /** Deletes all root folders on the given filesystem - if the FS is external or $unlink, only remove DB objects */
    public static function DeleteRootsByFSManager(ObjectDatabase $database, FSManager $filesystem, bool $unlink = false) : void
    {
        $unlink = $filesystem->isExternal() || $unlink;
        if ($unlink) static::$skiprefresh = true;
        
        $roots = static::LoadRootsByFSManager($database, $filesystem);
        
        static::$skiprefresh = false;
        
        foreach ($roots as $folder)
        {
            if ($unlink) $folder->NotifyFSDeleted(); else $folder->Delete();
        }
    }
    
    /** Deletes all root folders for the given owner - if the FS is external, only remove DB objects */
    public static function DeleteRootsByAccount(ObjectDatabase $database, Account $account) : void
    {
        foreach (static::LoadRootsByAccount($database, $account) as $folder)
        {
            if ($folder->GetFilesystem()->isExternal()) $folder->NotifyFSDeleted(); else $folder->Delete();
        }
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {
        $this->DeleteChildren();

        parent::Delete();
    }
}
