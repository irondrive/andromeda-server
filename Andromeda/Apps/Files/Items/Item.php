<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Config;
//use Andromeda\Apps\Files\Limits;
use Andromeda\Apps\Files\Social;
use Andromeda\Apps\Files\Filesystem\Filesystem;
use Andromeda\Apps\Files\Storage\Storage;

/**
 * An abstract class defining a user-created item in a filesystem.
 * 
 * Like other objects, items are generally referred to by ID, not name path.
 * It is therefore somewhat like an object storage, except that every item
 * must have exactly one parent (other than the root folder).
 */
abstract class Item extends BaseObject
{
    protected const IDLength = 16;

    use TableTypes\TableLinkedChildren;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(File::class, Folder::class); }

    /** The disk space size of the item (bytes) */
    protected FieldTypes\Counter $size;
    /** 
     * The account that owns the item (null if on public external storage)
     * @var FieldTypes\NullObjectRefT<Account>
     */
    protected FieldTypes\NullObjectRefT $owner;
    /** 
     * The storage that holds this item
     * @var FieldTypes\ObjectRefT<Storage>
     */
    protected FieldTypes\ObjectRefT $storage;
    /**
     * The parent item of this item (null if isroot)
     * @var FieldTypes\NullObjectRefT<Folder>
     */
    protected FieldTypes\NullObjectRefT $parent;
    /** The name of the item (null if isroot) */
    protected FieldTypes\NullStringType $name;
    /** True if this item is the filesystem root */
    protected FieldTypes\BoolType $isroot;
    /** True if this item has no owner */
    protected FieldTypes\BoolType $ispublic;
    /** Timestamp when the item was created */
    protected FieldTypes\Timestamp $date_created;
    /** Timestamp when the item was last written */
    protected FieldTypes\NullTimestamp $date_modified;
    /** Timestamp when the item was last read */
    protected FieldTypes\NullTimestamp $date_accessed; 
    /** Optional user-supplied description of the item */
    protected FieldTypes\NullStringType $description;
    
    protected function CreateFields(): void
    {
        $fields = array();
        $this->size = $fields[] = new FieldTypes\Counter('size');
        $this->owner = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'owner');
        $this->storage = $fields[] = new FieldTypes\ObjectRefT(Storage::class, 'storage');
        $this->parent = $fields[] = new FieldTypes\NullObjectRefT(Folder::class, 'parent');
        $this->name = $fields[] = new FieldTypes\NullStringType('name');
        $this->isroot = $fields[] = new FieldTypes\BoolType('isroot');
        $this->ispublic = $fields[] = new FieldTypes\BoolType('ispublic');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_modified = $fields[] = new FieldTypes\NullTimestamp('date_modified',saveOnRollback:true);
        $this->date_accessed = $fields[] = new FieldTypes\NullTimestamp('date_accessed',saveOnRollback:true);
        $this->description = $fields[] = new FieldTypes\NullStringType('description');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /**
     * Attemps to load this item by the given parent and name
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param string $name the name of the item to load
     * @return ?static loaded item or null if not found
     */
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, string $name) : ?self
    {
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name));        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($where));
    }

    /** 
     * Loads objects with the given parent
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param non-negative-int $limit the max number of files to load 
     * @param non-negative-int $offset the offset to start loading from
     * @return array<string, static> items indexed by ID
     */
    public static function LoadByParent(ObjectDatabase $database, Folder $parent, ?int $limit = null, ?int $offset = null) : array
    {
        return $database->LoadObjectsByKey(static::class, 'parent', $parent->ID(), $limit, $offset);
    }
    
    /**
     * Returns an array of all items belonging to the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner to load objects for
     * @param non-negative-int $limit the max number of files to load 
     * @param non-negative-int $offset the offset to start loading from
     * @return array<string, static> items indexed by ID
     */
    public static function LoadByOwner(ObjectDatabase $database, Account $account, ?int $limit = null, ?int $offset = null) : array
    {
        return $database->LoadObjectsByKey(static::class, 'owner', $account->ID(), $limit, $offset);
    }
    
    /**
     * Returns all items with a parent that is not owned by the item owner
     * 
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, static> items indexed by ID
     */
    public abstract static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array;
    // TODO RAY base impl can be here...?

    /** 
     * Deletes objects with the given parent
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param non-negative-int $limit the max number of files to load 
     * @param non-negative-int $offset the offset to start loading from
     */
    public static function DeleteByParent(ObjectDatabase $database, Folder $parent, ?int $limit = null, ?int $offset = null) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'parent', $parent->ID(), $limit, $offset);
    }
    
    /**
     * Creates a new object in the database only (no filesystem call)
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the item's parent folder
     * @param Account $account the account owning this item
     * @param string $name the name of the item
     * @return static newly created object
     */
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        $obj = $database->CreateObject(static::class);

        $obj->storage->SetObject($parent->storage->GetObject());
        $obj->parent->SetObject($parent);
        $obj->owner->SetObject($account);
        $obj->name->SetValue($name);

        //$obj->CountCreate(); // TODO limits
        return $obj;
    }

    /** True if the item has been refreshed */
    protected bool $refreshed = false;

    /** Updates the metadata of this item by scanning the object in the filesystem */
    public abstract function Refresh() : void;
    // TODO seems like Refresh semantics could use some work, maybe just make it immediate
    // upon loading the object from the DB? would be ideal if this wasn't public?

    /** Returns the owner of this item, or null if it's on an external FS */
    public function TryGetOwner() : ?Account { return $this->owner->TryGetObject(); }
    
    /** Returns the ID of the owner of this item (or null) */
    public function TryGetOwnerID() : ?string { return $this->owner->TryGetObjectID(); }
    
    /** Returns the name of this item (null if a root folder) */
    public function TryGetName() : ?string { return $this->name->TryGetValue(); }

    /**
     * Returns the name of this item
     * @throws Exceptions\InvalidRootOpException if null
     */
    public function GetName() : string
    {
        $name = $this->name->TryGetValue();
        if ($name !== null) return $name;
        else throw new Exceptions\InvalidRootOpException();
    }
    
    /** 
     * Returns the size of this item in bytes 
     * @return non-negative-int
     */
    public function GetSize() : int
    {
        $size = $this->size->GetValue();
        assert ($size >= 0); // DB CHECK CONSTRAINT
        return $size;
    }
    
    /** Returns the parent folder of this item */
    public function TryGetParent() : ?Folder { return $this->parent->TryGetObject(); }
    
    /** Returns the parent ID of this item */
    public function TryGetParentID() : ?string { return $this->parent->TryGetObjectID(); }

    /**
     * Returns the name of this item
     * @throws Exceptions\InvalidRootOpException if null
     */
    public function GetParent() : Folder
    {
        $parent = $this->parent->TryGetObject();
        if ($parent !== null) return $parent;
        else throw new Exceptions\InvalidRootOpException();
    }
    
    /** Renames the item. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetName(string $name, bool $overwrite = false) : bool;
    
    /** Moves the item to a new parent. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetParent(Folder $parent, bool $overwrite = false) : bool;
    
    /** Returns this item's description */
    public function GetDescription() : ?string { return $this->description->TryGetValue(); }
    
    /** Sets this item's description to the given value */
    public function SetDescription(?string $val) : bool { return $this->description->SetValue($val); }

    /** 
     * Returns all comment objects for this item 
     * @return array<string, Social\Comment>
     */
    //public function GetComments() : array { return Social\Comment::LoadByItem($this->database, $this); }

    /** 
     * Returns all like objects for this item 
     * @return array<string, Social\Like>
     */
    //public function GetLikes() : array { return Social\Like::LoadByItem($this->database, $this); }

    /** 
     * Returns all tag objects for this item 
     * @return array<string, Social\Tag>
     */
    //public function GetTags() : array { return Social\Tag::LoadByItem($this->database, $this); }

    /** 
     * Returns all share objects for this item 
     * @return array<string, Social\Share>
     */
    //public function GetShares() : array { return Social\Share::LoadByItem($this->database, $this); } // TODO RAY !! fix these
    
    /**
     * Copies the item to a new name.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param string $name the name of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self;
    
    /**
     * Copies the item to a new parent.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param Folder $parent the parent folder of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self;
    
    /**
     * Asserts that this item can be moved to the given name
     * @param string $name the item name to check for
     * @param bool $overwrite if true, delete the duplicate item
     * @param bool $reuse if true, return the duplicate item for reuse instead of deleting
     * @throws Exceptions\DuplicateItemException if a duplicate item exists and not $overwrite
     * @throws Exceptions\InvalidRootOpException if used on a root folder
     * @return ?static existing item to be re-used
     */
    protected function CheckName(string $name, bool $overwrite, bool $reuse) : ?self
    {
        $parent = $this->GetParent();
        $parent->Refresh(true);
        $item = static::TryLoadByParentAndName($this->database, $parent, $name);
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this) 
            {
                if (!$reuse) $item->Delete();
                else { $item->SetCreated(); return $item; }
            }
            else throw new Exceptions\DuplicateItemException();
        }
        return null;
    }
    
    /**
     * Asserts that this item can be moved to the given parent
     * @param Folder $parent parent folder to check
     * @param bool $overwrite if true, delete the duplicate item
     * @param bool $reuse if true, return the duplicate item for reuse instead of deleting
     * @throws Exceptions\CrossFilesystemException if the parent is on a different filesystem
     * @throws Exceptions\DuplicateItemException if a duplicate item exists and not $overwrite
     * @throws Exceptions\InvalidRootOpException if used on a root folder
     * @return ?static existing item to be re-used
     */
    protected function CheckParent(Folder $parent, bool $overwrite, bool $reuse) : ?self
    {
        if ($parent->storage->GetObjectID() !== $this->storage->GetObjectID())
            throw new Exceptions\CrossFilesystemException();

        $parent->Refresh(true);
        $item = static::TryLoadByParentAndName($this->database, $parent, $this->GetName());
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this)
            {
                if (!$reuse) $item->Delete();
                else { $item->SetCreated(); return $item; }
            }
            else throw new Exceptions\DuplicateItemException();
        }
        return null;
    }    
    
    /** Sets this item's owner to the given account */
    public function SetOwner(Account $account) : bool
    {
        //$this->AddStatsToOwner(false); // subtract stats from old owner
        $retval = $this->owner->SetObject($account);
        //$this->AddStatsToOwner(); // add stats to new owner
        return $retval;
    }
    
    /** 
     * Returns the filesystem manager's implementor that stores this object 
     * @param bool $requireExist if true the item must not be deleted
     * 
     * Refreshes the item from storage first to make sure it's ready to use.
     * @throws Exceptions\DeletedByStorageException if requreExist and the item has been deleted
     */
    protected function GetFilesystem(bool $requireExist = true) : Filesystem 
    {
        if ($requireExist)
        {
            $this->Refresh(); 
            if ($this->isDeleted())
                throw new Exceptions\DeletedByStorageException();
        }
        
        return $this->storage->GetObject()->GetFilesystem(); 
    }
    
    /** Returns true if this file should be accessible by all accounts */
    public function isWorldAccess() : bool 
    { 
        $st = $this->storage->GetObject();
        return $st->isExternal() && !$st->isUserOwned();
    }
    
    /** Sets the item's access time to the given value or now if null */
    public function SetAccessed(?float $time = null) : bool
    { 
        //if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        //return $this->SetDate('accessed', max($this->TryGetDate('accessed'), $time)); 
        return false;        // TODO RAY !! fix me
    }
    
    /** Sets the item's created time to the given value or now if null */
    public function SetCreated(?float $time = null) : bool { return $this->date_created->SetTimeNow(); } // TODO RAY !! what is $time used for?
    
    /** Sets the item's modified time to the given value or now if null */
    public function SetModified(?float $time = null) : bool { return $this->date_modified->SetTimeNow(); } // TODO RAY !! what is $time used for?
    // TODO maybe modified and accessed should be set within the filesystem, not files app

    // TODO limits CountCreate() used to MapToLimits(CountItem) and MapToTotalLimits(SetUploadDate)
    // TODO limits CountShare() used to forward to MapToLimits also

    /** 
     * Maps the given function to all applicable limit objects 
     * @return $this
     */
    /*protected function MapToLimits(callable $func) : self
    {        
        return $this->MapToTotalLimits($func)->MapToTimedLimits($func);
    }*/
    
    /** 
     * Maps the given function to all applicable total limit objects 
     * @return $this
     */
    /*protected function MapToTotalLimits(callable $func) : self
    {        
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem()); if ($fslim !== null) $func($fslim);
        
        if ($this->GetOwnerID()) foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);
        
        return $this;
    }*/
    
    /** 
     * Maps the given function to all applicable timed limit objects 
     * @return $this
     */
    /*protected function MapToTimedLimits(callable $func) : self
    {        
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
        
        foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $lim) $func($lim);    
        
        if ($this->GetOwnerID()) foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);   
        
        return $this;
    }*/
    
    /**
     * Adds this item's stats to all owner limits
     * @param bool $add if true add, else subtract
     * @return $this
     */
    /*protected function AddStatsToOwner(bool $add = true) : void
    {
        if (!$this->GetOwnerID()) return;
        
        foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $limit)
            $this->AddStatsToLimit($limit, $add);
 
        if (Config::GetInstance($this->database)->GetAllowTimedStats())
            foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $limit)
                $this->AddStatsToLimit($limit, $add);
    }*/ 

    /**
     * Adds this item's stats to all filesystem limits
     * @param bool $add if true add, else subtract
     * @return $this
     */
    /*protected function AddStatsToFilesystem(bool $add = true) : bool
    {        
        $total = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        
        if ($total !== null) $this->AddStatsToLimit($total, $add);
        
        if (Config::GetInstance($this->database)->GetAllowTimedStats())
            foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $limit)
                $this->AddStatsToLimit($limit, $add);
    }*/
    
    /** Adds this item's stats to the given limit, substracting if not $add */
    //protected abstract function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void;
    
    /**
     * Returns a config bool for the item by checking applicable limits
     * @param callable $func the function returning the desired bool
     * @param Account $account the account to check the permission for, or null for defaults
     * @return bool true if (the FS value is null or true) and the account value is true
     */
    /*protected function GetLimitsBool(callable $func, ?Account $account) : bool
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        $aclim = Limits\AccountTotal::LoadByAccount($this->database, $account, true);
        return ($fslim === null || $func($fslim) !== false) && $func($aclim);
    }*/
    
    /** Returns true if the item should allow public modifications */
    /*public function GetAllowPublicModify() : bool {
       return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicModify(); }, null); }*/
    
    /** Returns true if the item should allow public uploading */
    /*public function GetAllowPublicUpload() : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicUpload(); }, null); }*/
    
    /** Returns true if the item should allow random/partial writes */
    /*public function GetAllowRandomWrite(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowRandomWrite(); }, $account); }*/
    
    /** Returns true if the item should allow sharing */
    /*public function GetAllowItemSharing(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowItemSharing(); }, $account); }*/

    /** Returns true if the item should allow group shares (shares with a group) */
    /*public function GetAllowShareToGroups(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareToGroups(); }, $account); }*/
        
    /** Returns true if the item should allow public shares (shares with all users) */
    /*public function GetAllowShareToEveryone(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareToEveryone(); }, $account); }*/ // TODO LIMITS
    
    /** Deletes the item from the DB only */
    public abstract function NotifyFSDeleted() : void;

    public function NotifyPreDeleted() : void
    {
        //if (!$this->isDeleted())
        //    $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(false); }); // TODO LIMITS
        
        // TODO RAY !! need to delete likes, tags, comments, shares when deleting
    }
    
    /**
     * Returns a printable client object of this item
     * @param bool $owner if true, show owner-level details
     * @param bool $details if true, show tag and share objects
     * @return ?array{} `{id:id, name:?string, owner:?string, parent:?string, filesystem:string, \
         dates:{created:float, modified:?float}, counters:{pubdownloads:int, bandwidth:int, likes:int, dislikes:int, tags:int, comments:int}}` \
         if $owner, add: `{counters:{shares:int}, dates:{accessed:?float}}`, \
         if $details, add: `{tags:[id:Tag]}`, if $details && $owner, add `{shares:[id:Share]}`
     * @see Tag::GetClientObject()
     * @see Share::GetClientObject()
     */
    public function SubGetClientObject(bool $owner = false, bool $details = false) : ?array
    {
        /*$data = array(
            'id' => $this->ID(),
            'name' => $this->GetName(),
            'owner' => $this->GetOwnerID(),
            'parent' => $this->GetParentID(),
            'filesystem' => $this->GetFilesystemID(),
            'description' => $this->GetDescription(),            
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'modified' => $this->TryGetDate('modified')
            ),
            'counters' => array(
                'pubdownloads' => $this->GetPublicDownloads(),
                'bandwidth' => $this->GetBandwidth(),
                'likes' => $this->GetCounter('likes'),
                'dislikes' => $this->GetCounter('dislikes'),
                'tags' => $this->CountObjectRefs('tags'),
                'comments' => $this->CountObjectRefs('comments')
            )
        );
        
        if ($owner) 
        {
            $data['dates']['accessed'] = $this->TryGetDate('accessed');
            $data['counters']['shares'] = $this->GetNumShares();
        }
        
        if ($details) $data['tags'] = array_map(function(Tag $e) {
            return $e->GetClientObject(); }, $this->GetTags());
        
        if ($owner && $details) $data['shares'] = array_map(function(Share $e) {
            return $e->GetClientObject(); }, $this->GetShares());
        
        return $data;*/ return []; // TODO RAY !! get Client object
    }
    
    /** @return array{} */
    public abstract function GetClientObject(bool $owner = false, bool $full = false) : array;
    /** @return array{} */
    public abstract function TryGetClientObject(bool $owner = false, bool $full = false) : ?array;
}
