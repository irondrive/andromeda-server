<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\Database\Exceptions\BadPolyClassException;
use Andromeda\Apps\Accounts\Account;

/** 
 * Defines a user-stored folder which groups other items 
 *
 * Folders keep recursive running counts of most statistics like number 
 * of subfiles, total folder size, etc., so retrieving these values is fast.
 * 
 * Every folder must have a name and exactly one parent, other than roots which have neither.
 */
abstract class Folder extends Item
{
    use TableTypes\HasTable;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(RootFolder::class, SubFolder::class); }

    public static function HasTypedRows() : bool { return true; }

    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string
    {
        $table = $database->GetClassTableName(self::class);
        return match($class)
        {
            RootFolder::class => $q->IsNull("$table.parent",false),
            SubFolder::class => $q->Not($q->IsNull("$table.parent",false)),
            default => throw new BadPolyClassException($class)
        };
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string
    {
        return ($row['parent'] === null) ? RootFolder::class : SubFolder::class;
    }

    /** Running count of subfiles in this folder */
    protected FieldTypes\Counter $count_subfiles;
    /** Running count of subfolders in this folder */
    protected FieldTypes\Counter $count_subfolders;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->count_subfiles = $fields[] = new FieldTypes\Counter('count_subfolders');
        $this->count_subfolders = $fields[] = new FieldTypes\Counter('count_subfolders');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /**
     * Returns all items with a parent that is not owned by the item owner
     *
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, static> items indexed by ID
     */
    public static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array
    {
        /*$q = new QueryBuilder();
        
        $q->SelfJoinWhere($database, Folder::class, 'obj_parent', 'id');
        
        $w = $q->And($q->GetWhere(), $q->NotEquals('_parent.obj_owner', $account->ID()),
            $q->Equals($database->GetClassTableName(Folder::class).'.obj_owner', $account->ID()));

        return array_filter(static::LoadByQuery($database, $q->Where($w)), 
            function(Folder $folder){ return !$folder->isWorldAccess(); });*/
        return []; // TODO RAY work out the self join issue
    }
    
    /**
     * Returns an array of the files in this folder (not recursive)
     * @param non-negative-int $limit the max number of files to load
     * @param non-negative-int $offset the offset to start loading from
     * @return array<string, File> files indexed by ID
     */
    public function GetFiles(?int $limit = null, ?int $offset = null) : array
    { 
        $this->Refresh(true); 
        return File::LoadByParent($this->database, $this, $limit, $offset);
    }
        
    /**
     * Returns an array of the folders in this folder (not recursive)
     * @param non-negative-int $limit the max number of folders to load
     * @param non-negative-int $offset the offset to start loading from
     * @return array<string, SubFolder> folders indexed by ID
     */
    public function GetFolders(?int $limit = null, ?int $offset = null) : array
    { 
        $this->Refresh(true); 
        return SubFolder::LoadByParent($this->database, $this, $limit, $offset);
    }
    
    /** Returns the number of files in this folder (not recursive) (fast) */
    public function GetNumFiles() : int { return $this->count_subfiles->GetValue(); }
    
    /** Returns the number of folders in this folder (not recursive) (fast) */
    public function GetNumFolders() : int { return $this->count_subfolders->GetValue(); }
    
    /** Returns the number of items in this folder (not recursive) (fast) */
    public function GetNumItems() : int { return $this->GetNumFiles() + $this->GetNumFolders(); }
    
    /** Increments the size of this folder and parents by the given #bytes */
    public function DeltaSize(int $size) : void
    { 
        if (($parent = $this->TryGetParent()) !== null)
            $parent->DeltaSize($size);
        $this->size->DeltaValue($size); 
    }
    
    /** Sets this folder and its contents' owners to the given account */
    public function SetOwnerRecursive(Account $account) : self
    {
        $this->SetOwner($account);
        
        //foreach (array_merge($this->GetFiles(), $this->GetFolders()) as $item) $item->SetOwner($account); // TODO no array merge
       
        return $this;
    }
    
    /** 
     * Asserts that $this is not the given folder, or any of its parents 
     * i.e. that $folder is not equal to $this, or any of its children
     * @throws Exceptions\InvalidFolderParentException if the check fails
     */
    protected function CheckNotChildOrSelf(Folder $folder) : void
    {
        do { if ($folder === $this)
                throw new Exceptions\InvalidFolderParentException(); }
        while (($folder = $folder->TryGetParent()) !== null);
    }
    
    /**
     * Adds the statistics from the given item to this folder
     * @param Item $item the item to add stats from
     * @param bool $add if true add, else subtract
     */
    private function AddItemCounts(Item $item, bool $add = true) : void // @phpstan-ignore-line TODO RAY remove ignore later
    {
        $this->SetModified(); // TODO RAY seems like a dumb place to do this?
        
        $val = $add ? 1 : -1;
        $this->size->DeltaValue($item->GetSize() * $val);        

        if ($item instanceof File) 
        {
            $this->count_subfiles->DeltaValue($val);
        }
        else if ($item instanceof Folder) 
        {
            $this->count_subfiles->DeltaValue($item->GetNumFiles()*$val);
            $this->count_subfolders->DeltaValue($item->GetNumFolders()*$val + $val); // +self
        }
        
        if (($parent = $this->TryGetParent()) !== null) 
            $parent->AddItemCounts($item, $add);
    }
    
    // TODO need to notify folder directly, can't override AddObjectRef/RemoveObjectRef
    /*protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::AddObjectRef($field, $object, $notification);
        
        if ($modified && ($field === 'files' || $field === 'folders')) $this->AddItemCounts($object, true);
        
        return $modified;
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : bool
    {
        $modified = parent::RemoveObjectRef($field, $object, $notification);
        
        if ($modified && ($field === 'files' || $field === 'folders')) $this->AddItemCounts($object, false);
        
        return $modified;
    }*/
    
    //protected function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void { $limit->AddFolderCounts($this, $add); }

    /** True if the folder's contents have been refreshed */
    protected bool $subrefreshed = false;
    
    /**
     * Refreshes the folder's metadata from disk
     * @param bool $doContents if true, refresh all contents of the folder
     */
    public function Refresh(bool $doContents = false) : void
    {
        if ($this->isCreated() || $this->isDeleted()) return;
        
        if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true;
            $this->subrefreshed = $doContents;
            $this->GetFilesystem(false)->RefreshFolder($this, $doContents);
        }
    }
    
    protected bool $fsDeleted = false;
    // TODO RAY add comment
    public function isFSDeleted() : bool { return $this->fsDeleted; }
    
    /** Deletes all subfiles and subfolders, refresh if not isNotify */
    public function DeleteChildren(bool $isNotify = false) : void
    {
        $this->fsDeleted = $isNotify;
        if (!$isNotify) $this->Refresh(true);

        // TODO RAY don't we need to delete each subitem with NOTIFY also? maybe that is the point of fsDeleted?
        File::DeleteByParent($this->database, $this);
        SubFolder::DeleteByParent($this->database, $this);
    }
    
    /** Deletes this folder and its contents from the DB only */
    public function NotifyFSDeleted() : void
    {
        $this->DeleteChildren(true);
        
        parent::Delete();
    } 
    
    /**
     * Recursively lists subitems in this folder
     * @param bool $files if true, load files
     * @param bool $folders if true, load folders
     * @param non-negative-int $limit max number of items to load
     * @param non-negative-int $offset offset of items to load
     * @return array<string, Item> items indexed by ID
     */
    private function RecursiveItems(?bool $files = true, ?bool $folders = true, ?int $limit = null, ?int $offset = null) : array // @phpstan-ignore-line TODO should have a client function for this?
    {
        $items = array();
        if ($limit !== null && $offset !== null)
            $limit += $offset;
        
        foreach ($this->GetFolders($limit) as $subfolder)
        {
            $subitems = $subfolder->RecursiveItems($files,$folders,$limit);
            $items = array_merge($items, $subitems);            
            if ($limit !== null)
                $limit -= count($subitems);
            assert($limit >= 0); // RecursiveItems only returns up to limit
        }
        
        $items = array_merge($items, $this->GetFiles($limit));
        if ($offset !== null) $items = array_slice($items, $offset);
        return $items;
    }

    /** 
     * @see Folder::TryGetClientObjects()
     * @throws Exceptions\DeletedByStorageException if the item is deleted 
     * @return array{}
     */
    public function GetClientObject(bool $owner = false, bool $details = false,
        bool $files = false, bool $folders = false, bool $recursive = false,
        ?int $limit = null, ?int $offset = null) : array
    {
        return [];
        /*$retval = $this->TryGetClientObject($owner,$details,$files,$folders,$recursive,$limit,$offset);
        if ($retval === null) throw new Exceptions\DeletedByStorageException(); else return $retval;*/
    }
    
    /**
     * Returns a printable client object of this folder
     * @param bool $files if true, show subfiles
     * @param bool $folders if true, show subfolders
     * @param bool $recursive if true, show recursive contents
     * @param int $limit max number of items to show
     * @param int $offset offset of items to show
     * @return ?array{} null if deleted, else `{files:[id:File], folders:[id:Folder], \
         counters:{size:int, pubvisits:int, subfiles:int, subfolders:int}}` \
         if $owner, add: `{counters:{subshares:int}}`
     * @see Item::SubGetClientObject()
     */
    public function TryGetClientObject(bool $owner = false, bool $details = false,
        bool $files = false, bool $folders = false, bool $recursive = false, 
        ?int $limit = null, ?int $offset = null) : ?array
    {
        return [];
        /*$this->Refresh($files || $folders); 
        
        if ($this->isDeleted()) return null;
        
        $this->SetAccessed();
        
        if ($recursive && ($files || $folders))
        {
            $items = $this->RecursiveItems($files,$folders,$limit,$offset);
            
            if ($folders) $subfolders = array_filter($items, function($item){ return ($item instanceof Folder); });            
            if ($files) $subfiles = array_filter($items, function($item){ return ($item instanceof File); });
        }
        else
        {
            if ($folders)
            {
                $subfolders = array_filter($this->GetFolders($limit,$offset), 
                    function(Folder $folder){ return !$folder->Refresh()->isDeleted(); });
                    // check isDeleted early so we have an accurate count for files
            
                $numitems = count($subfolders);
                if ($offset !== null) $offset = max(0, $offset-$numitems);
                if ($limit !== null) $limit = max(0, $limit-$numitems);
            }
            
            if ($files) $subfiles = $this->GetFiles($limit,$offset);
        }
        
        $data = parent::SubGetClientObject($owner,$details);
        
        if ($folders) $data['folders'] = array_filter(array_map(function(Folder $folder)use($owner){ 
            return $folder->TryGetClientObject($owner); }, $subfolders));
        
        if ($files) $data['files'] = array_filter(array_map(function(File $file)use($owner){ 
            return $file->TryGetClientObject($owner); }, $subfiles));
        
        $data['counters'] = Utilities::array_map_keys(function($p){ return $this->GetCounter($p); },
            array('size','pubvisits','subfiles','subfolders'));
        
        if ($owner) $data['counters']['subshares'] = $this->GetCounter('subshares');
        
        return $data;*/
    }
}
