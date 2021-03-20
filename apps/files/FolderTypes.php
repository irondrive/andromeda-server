<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\StorageException;
require_once(ROOT."/apps/files/Folder.php");

use Andromeda\Apps\Files\Filesystem\FSManager;

/** Exception indicating that the operation is not valid on a root folder */
class InvalidRootOpException extends Exceptions\ClientErrorException { public $message = "ROOT_FOLDER_OP_INVALID"; }

/** A root folder has no parent or name */
class RootFolder extends Folder
{
    public static function GetObjClass(array $row) : string { return self::class; }
    
    public function GetName() : string { return $this->GetFilesystem()->GetName(); }
    
    public function SetName(string $name, bool $overwrite = false) : self { $this->GetFilesystem()->SetName($name); }
    
    public function GetParent() : ?Folder { return null; }
    public function GetParentID() : ?string { return null; }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self { throw new InvalidRootOpException(); }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self { throw new InvalidRootOpException(); }
   
    /** 
     * Don't let a storage exception fail refreshing a root folder 
     * @see Folder::Refresh()
     */
    public function Refresh(bool $doContents = false) : self 
    {
        try { parent::Refresh($doContents); } catch (StorageException $e) { }
        
        return $this;
    }
    
    /** Don't let a root folder be deleted if it's missing in storage */
    public function CanRefreshDelete() : bool { return false; }
    
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
            $owner = $filesystem->isShared() ? $filesystem->GetOwner() : $account;
            
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
    
    /** Deletes all root folders on the given filesystem - if the FS is shared or $unlink, only remove DB objects */
    public static function DeleteRootsByFSManager(ObjectDatabase $database, FSManager $filesystem, bool $unlink = false) : void
    {
        $unlink = $filesystem->isShared() || $unlink;
        if ($unlink) static::$skiprefresh = true;
        
        $roots = static::LoadRootsByFSManager($database, $filesystem);
        
        static::$skiprefresh = false;
        
        foreach ($roots as $folder)
        {
            if ($unlink) $folder->NotifyDelete(); else $folder->Delete();
        }
    }
    
    /** Deletes all root folders for the given owner - if the FS is shared, only remove DB objects */
    public static function DeleteRootsByAccount(ObjectDatabase $database, Account $account) : void
    {
        foreach (static::LoadRootsByAccount($database, $account) as $folder)
        {
            if ($folder->GetFilesystem()->isShared()) $folder->NotifyDelete(); else $folder->Delete();
        }
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {
        $this->DeleteChildren(false);

        parent::Delete();
    }
    
    /**
     * Special function that returns a client object listing all root folders
     * @param ObjectDatabase $database database reference
     * @return array `{files:[], folders:[id:RootFolder],
         dates:{created:float}, counters:{size:int, pubvisits:int, pubdownloads:int, bandwidth:int,
            subfiles:int, subfolders:int, subshares:int, likes:int, dislikes:int}}`
     * @see Folder::GetClientObject()
     */
    public static function GetSuperRootClientObject(ObjectDatabase $database, Account $account) : array
    {
        $filesystems = FSManager::LoadByAccount($database, $account);
        
        $roots = array(); foreach ($filesystems as $fs)
        {
            $root = RootFolder::GetRootByAccountAndFS($database, $account, $fs);
            
            $roots[$root->ID()] = $root;
        }

        $counters = array(); foreach (array_keys(Folder::GetFieldTemplate()) as $prop)
        {
            $prop = explode('__',$prop); if ($prop[0] !== 'counters') continue; else $prop = $prop[1];

            $counters[$prop] = array_sum(array_map(function (RootFolder $folder)use ($prop){ return $folder->GetCounter($prop); }, $roots));
        }
        
        $dates = array('created' => $account->GetDateCreated());
        
        $roots = array_map(function(RootFolder $fs){ return $fs->GetClientObject(); }, $roots);
        
        return array(
            'id' => null,
            'files' => array(),
            'folders' => $roots,
            'counters' => $counters,
            'dates' => $dates
        );
    }
}

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
        
        $folder->GetFSImpl()->CreateFolder($folder); return $folder;
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {
        $isNotify = $this->GetParent()->isNotifyDeleted();
        
        $this->DeleteChildren($isNotify);
        
        if (!$isNotify) $this->GetFSImpl()->DeleteFolder($this);
            
        parent::Delete();
    }    
}