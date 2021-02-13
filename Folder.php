<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

/** Exception indicating that the folder destination is invalid */
class InvalidDestinationException extends Exceptions\ClientErrorException { public $message = "INVALID_FOLDER_DESTINATION"; }

/** 
 * Defines a user-stored folder which groups other items 
 *
 * Folders keep recursive running counts of most statistics like number 
 * of subfiles, total folder size, etc., so retrieving these values is fast.
 * 
 * Every folder must have a name and exactly one parent, other than roots which have neither.
 */
class Folder extends Item
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'counters__visits' => new FieldTypes\Counter(), // number of public visits to this folder
            'counters__size' => new FieldTypes\Counter(),   // total size of the folder and all contents
            'parent'    => new FieldTypes\ObjectRef(Folder::class, 'folders'),
            'files'     => new FieldTypes\ObjectRefs(File::class, 'parent'),
            'folders'   => new FieldTypes\ObjectRefs(Folder::class, 'parent'),
            'counters__subfiles' => new FieldTypes\Counter(),   // total number of subfiles (recursive)
            'counters__subfolders' => new FieldTypes\Counter(), // total number of subfolders (recursive)
            'counters__subshares' => new FieldTypes\Counter()   // total number of shares (recursive)
        ));
    }

    /** Returns the name of the folder, or null iff it is a root folder */
    public function GetName() : ?string { return $this->TryGetScalar('name'); }
    
    /** Returns the total size of the folder and its content in bytes */
    public function GetSize() : int { return $this->TryGetCounter('size') ?? 0; }
    
    /** Returns the parent of the folder, or null iff it is a root folder */
    public function GetParent() : ?Folder { return $this->TryGetObject('parent'); }
    
    /** Returns the ID of the parent of this folder */
    public function GetParentID() : ?string { return $this->TryGetObjectID('parent'); }
    
    /**
     * Returns an array of the files in this folder (not recursive)
     * @param int $limit the max number of files to load`
     * @param int $offset the offset to start loading from
     * @return array<string, File> files indexed by ID
     */
    public function GetFiles(?int $limit = null, ?int $offset = null) : array { 
        $this->Refresh(true); return $this->GetObjectRefs('files',$limit,$offset); }
        
    /**
     * Returns an array of the folders in this folder (not recursive)
     * @param int $limit the max number of folders to load
     * @param int $offset the offset to start loading from
     * @return array<string, Folder> folders indexed by ID
     */
    public function GetFolders(?int $limit = null, ?int $offset = null) : array { $this->Refresh(true); return $this->GetObjectRefs('folders',$limit,$offset); }
    
    /** Returns the number of files in this folder (not recursive) (fast) */
    public function GetNumFiles() : int { return $this->GetCounter('subfiles'); }
    
    /** Returns the number of folders in this folder (not recursive) (fast) */
    public function GetNumFolders() : int { return $this->GetCounter('subfolders'); }
    
    /** Returns the number of items in this folder (not recursive) (fast) */
    public function GetNumItems() : int { return $this->GetNumFiles() + $this->GetNumFolders(); }
    
    /** Returns the total number of shares on this folder or its contents (recursive) */
    public function GetTotalShares() : int { return $this->GetNumShares() + $this->GetCounter('subshares'); }
    
    /** Increments the folder's visit counter */
    public function CountVisit() : self { return $this->DeltaCounter('visits'); }
    
    /** Increments the size of this folder and parents by the given #bytes */
    public function DeltaSize(int $size) : self 
    { 
        if (($parent = $this->GetParent()) !== null)
            $parent->DeltaSize($size);
        return $this->DeltaCounter('size',$size); 
    }   

    public function SetName(string $name, bool $overwrite = false) : self
    {
        parent::CheckName($name, $overwrite);
        $this->GetFSImpl()->RenameFolder($this, $name);
        return $this->SetScalar('name', $name);
    }

    public function SetParent(Folder $folder, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($folder);        
        parent::CheckParent($folder, $overwrite);
        
        $this->GetFSImpl()->MoveFolder($this, $folder);
        return $this->SetObject('parent', $folder);
    }

    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self 
    {
        parent::CheckName($name, $overwrite);
        
        if (!$this->GetParentID()) throw new InvalidDestinationException();
        
        $newfolder = static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl()->CopyFolder($this, $newfolder); return $newfolder;
    }
    
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self
    {
        parent::CheckParent($folder, $overwrite);
        $this->CheckIsNotChildOrSelf($folder);
        
        $newfolder = static::NotifyCreate($this->database, $folder, $owner, $this->GetName());
        
        $this->GetFSImpl()->CopyFolder($this, $newfolder); return $newfolder;
    }    
    
    /** Asserts that this folder is not the given folder, or any of its parents */
    private function CheckIsNotChildOrSelf(Folder $folder) : void
    {
        do { if ($folder === $this)
                throw new InvalidDestinationException(); }
        while (($folder = $folder->GetParent()) !== null);
    }
    
    /**
     * Adds the statistics from the given item to this folder
     * @param Item $item the item to add stats from
     * @param bool $sub if true, subtract instead of add
     */
    private function AddItemCounts(Item $item, bool $sub = false) : void
    {
        $this->SetModified(); $val = $sub ? -1 : 1;
        $this->DeltaCounter('size', $item->GetSize() * $val);
        $this->DeltaCounter('bandwidth', $item->GetBandwidth() * $val);
        $this->DeltaCounter('downloads', $item->GetDownloads() * $val);        
        
        if ($item instanceof File) 
        {
            $this->DeltaCounter('subfiles', $val);
            
            $this->DeltaCounter('subshares', $item->GetNumShares() * $val);
        }
        
        if ($item instanceof Folder) 
        {
            $this->DeltaCounter('subfolders', $val);
            
            $this->DeltaCounter('subfiles', $item->GetNumFiles() * $val);
            $this->DeltaCounter('subfolders', $item->GetNumFolders() * $val);
            $this->DeltaCounter('subshares', $item->GetTotalShares() * $val);
        }
        
        $parent = $this->GetParent(); if ($parent !== null) $parent->AddItemCounts($item, $sub);
    }
    
    /** Counts a share on a subitem of this folder */
    protected function CountSubShare(bool $count = true) : self
    {
        $this->DeltaCounter('subshares', $count ? 1 : -1);
    }

    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if ($field === 'files' || $field === 'folders') $this->AddItemCounts($object, false);
        
        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {        
        if ($field === 'files' || $field === 'folders') $this->AddItemCounts($object, true);
        
        return parent::RemoveObjectRef($field, $object, $notification);
    }
    
    private bool $refreshed = false;
    private bool $subrefreshed = false;
    
    private static bool $skiprefresh = false;
    
    /**
     * Refreshes the folder's metadata from disk
     * @param bool $doContents if true, refresh all contents of the folder
     */
    public function Refresh(bool $doContents = false) : self
    {
        if ($this->deleted || static::$skiprefresh) return $this;
        else if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true; $this->subrefreshed = $doContents;
            $this->GetFSImpl()->RefreshFolder($this, $doContents);   
        }
        return $this;
    }

    /** Creates a folder with the given FS and owner but no name or parent */
    private static function CreateRoot(ObjectDatabase $database, FSManager $filesystem, ?Account $account) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$filesystem)->SetObject('owner',$account);
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
        return static::CreateRoot($database, $parent->GetFilesystem(), $account)
            ->SetObject('parent',$parent)->SetScalar('name',$name)->CountCreate();
    }
    
    /**
     * Creates a new non-root folder both in DB and on disk
     * @see Folder::NotifyCreate()
     */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        $folder = static::NotifyCreate($database, $parent, $account, $name)->CheckName($name);

        $folder->GetFSImpl()->CreateFolder($folder); return $folder;
    }
    
    /**
     * Loads the root folder for given account and FS, creating it if it doesn't exist
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner of the root folder
     * @param FSManager $filesystem the filesystem of the root, or null to get the default
     * @return self|NULL loaded folder or null if creating it fails
     */
    public static function LoadRootByAccountAndFS(ObjectDatabase $database, Account $account, ?FSManager $filesystem = null) : ?self
    {
        $filesystem ??= FSManager::LoadDefaultByAccount($database, $account); if (!$filesystem) return null;
        
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'),
                                                  $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = static::TryLoadUniqueByQuery($database, $q->Where($where));
        if ($loaded) return $loaded;
        else 
        {
            $owner = $filesystem->isShared() ? $filesystem->GetOwner() : $account;
            return self::CreateRoot($database, $filesystem, $owner)->Refresh();
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
    
    private bool $notifyDeleted = false; public function isNotifyDeleted() : bool { return $this->notifyDeleted; }
    
    /** Deletes all subfiles and subfolders, refresh if not isNotify */
    public function DeleteChildren(bool $isNotify = true) : void
    {
        if (!$isNotify) $this->Refresh(true);
        
        $this->notifyDeleted = $isNotify;
        $this->DeleteObjectRefs('files');
        $this->DeleteObjectRefs('folders');
    }
    
    /** Deletes this folder and its contents from the DB only */
    public function NotifyDelete() : void
    {
        $this->DeleteChildren(true);
        
        parent::Delete();
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {        
        $parent = $this->GetParent();
        $isNotify = ($parent !== null && $parent->isNotifyDeleted());
        
        $this->DeleteChildren($isNotify);
        
        if (!$isNotify && $parent !== null) 
            $this->GetFSImpl()->DeleteFolder($this);
        
        parent::Delete();
    }    
    
    /**
     * Recursively lists subitems in this folder
     * @param bool $files if true, load files
     * @param bool $folders if true, load folders
     * @param int $limit max number of items to load
     * @param int $offset offset of items to load
     * @return array<string, Item> items indexed by ID
     */
    private function RecursiveItems(?bool $files = true, ?bool $folders = true, ?int $limit = null, ?int $offset = null) : array
    {
        $items = array();
        
        if ($limit && $offset) $limit += $offset;
        
        foreach ($this->GetFolders($limit) as $subfolder)
        {
            $newitems = $subfolder->RecursiveItems($files,$folders,$limit);
            
            $items = array_merge($items, $newitems);            
            if ($limit !== null) $limit -= count($newitems);
        }
        
        $items = array_merge($items, $this->GetFiles($limit));
        
        if ($offset) $items = array_slice($items, $offset);
        
        return $items;
    }

    /**
     * Returns a printable client object of this folder
     * @param bool $files if true, show subfiles
     * @param bool $folders if true, show subfolders
     * @param bool $recursive if true, show recursive contents
     * @param int $limit max number of items to show
     * @param int $offset offset of items to show
     * @param int $details detail level of output
     * @return array|NULL null if deleted, else `{filesystem:id, files:[id:File], folders:[id:Folder],
         dates:{created:int, modified:?int, accessed:?int}, counters:{size:int, visits:int, downloads:int, bandwidth:int,
            subfiles:int, subfolders:int, subshares:int, likes:int, dislikes:int}}`
     * @see Item::SubGetClientObject()
     */
    public function GetClientObject(bool $files = false, bool $folders = false, bool $recursive = false,
        ?int $limit = null, ?int $offset = null, int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $this->SetAccessed();

        $data = array_merge(parent::SubGetClientObject($details),array(
            'filesystem' => $this->GetObjectID('filesystem')
        ));
        
        
        if ($recursive && ($files || $folders))
        {
            $items = $this->RecursiveItems($files,$folders,$limit,$offset);
            
            if ($folders)
            {
                $subfolders = array_filter($items, function($item){ return ($item instanceof Folder); });
                $data['folders'] = array_map(function($folder){ return $folder->GetClientObject(); },$subfolders);
            }
            
            if ($files)
            {
                $subfiles = array_filter($items, function($item){ return ($item instanceof File); });
                $data['files'] = array_map(function($file){ return $file->GetClientObject(); },$subfiles);
            }            
        }
        else
        {
            if ($folders)
            {
                $data['folders'] = array_map(function($folder){
                    return $folder->GetClientObject(); }, $this->GetFolders($limit,$offset));
            
                $numitems = count($data['folders']);
                if ($offset !== null) $offset = max(0, $offset-$numitems);
                if ($limit !== null) $limit = max(0, $limit-$numitems);
            }
            
            if ($files)
            {
                $data['files'] = array_map(function($file){
                    return $file->GetClientObject(); }, $this->GetFiles($limit,$offset));
            }
        }        
        
        $data['dates'] = $this->GetAllDates();
        $data['counters'] = $this->GetAllCounters();

        return $data;
    }
}
