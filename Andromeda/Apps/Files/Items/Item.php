<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\{Config, Policy, Social};
use Andromeda\Apps\Files\Filesystem\Filesystem;
use Andromeda\Apps\Files\Storage\Storage;

/**
 * An abstract class defining a user-created item in a filesystem.
 * 
 * Like other objects, items are generally referred to by ID, not name path.
 * It is therefore somewhat like an object storage, except that every item
 * must have exactly one parent (other than the root folder).
 * 
 * @phpstan-import-type ShareJ from Social\Share
 * @phpstan-import-type TagJ from Social\Tag
 * @phpstan-type ItemJ array{id:string, name:string, owner:?string, parent:?string, storage:string, description:?string, date_created:float, date_modified:?float, date_accessed?:?float, tags?:array<string, TagJ>, shares?:array<string, ShareJ>, count_likes?:int, count_dislikes?:int}
 */
abstract class Item extends BaseObject
{
    protected const IDLength = 16;

    use TableTypes\TableLinkedChildren;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(File::class, Folder::class); }

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
    protected FieldTypes\NullBoolType $isroot;
    /** True if this item has no owner */
    protected FieldTypes\NullBoolType $ispublic;
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
        $this->owner = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'owner');
        $this->storage = $fields[] = new FieldTypes\ObjectRefT(Storage::class, 'storage');
        $this->parent = $fields[] = new FieldTypes\NullObjectRefT(Folder::class, 'parent');
        $this->name = $fields[] = new FieldTypes\NullStringType('name');
        $this->isroot = $fields[] = new FieldTypes\NullBoolType('isroot');
        $this->ispublic = $fields[] = new FieldTypes\NullBoolType('ispublic');
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
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, string $name) : ?static
    {
        $parent->Refresh(doContents:true);

        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name));        
        return $database->TryLoadUniqueByQuery(static::class, $q->Where($where));
    }

    /** 
     * Loads objects with the given parent
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, static> items indexed by ID
     */
    public static function LoadByParent(ObjectDatabase $database, Folder $parent, ?int $limit = null, ?int $offset = null) : array
    {
        $parent->Refresh(doContents:true);
        return $database->LoadObjectsByKey(static::class, 'parent', $parent->ID(), $limit, $offset);
    }
    
    /**
     * Returns an array of all items belonging to the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner to load objects for
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
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
    public static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        $objs = $database->LoadObjectsByQuery(static::class, $q);
        
        $q->SelfJoinWhere($database, Item::class, 'parent', 'id', '_parent');

        // TODO DBREVAMP this doesn't seem to work. seems to return everything. also needs testing across postgres and sqlite
        
        // load where the item's owner is us, but the item's parent's owner is not us
        $q->Where($q->And($q->NotEquals('_parent.owner', $account->ID(), quotes:false),
            $q->Equals($database->GetClassTableName(Item::class).'.owner', $account->ID(), quotes:false)));

        $objs = $database->LoadObjectsByQuery(static::class, $q);
        // items on shared external storage don't count as we don't need a share to put them there
        return array_filter($objs, function(Item $item){ return !$item->isWorldAccess(); });
    }
    
    /** 
     * Deletes objects with the given parent
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     */
    public static function DeleteByParent(ObjectDatabase $database, Folder $parent) : int
    {
        $parent->Refresh(doContents:true);
        return $database->DeleteObjectsByKey(static::class, 'parent', $parent->ID());
    }
    
    /**
     * Creates a new object in the database only (no filesystem call)
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the item's parent folder
     * @param Account $account the account owning this item
     * @param string $name the name of the item
     * @return static newly created object
     */
    protected static function CreateItem(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();

        $obj->storage->SetObject($parent->storage->GetObject());
        $obj->parent->SetObject($parent);
        $obj->owner->SetObject($account);
        $obj->name->SetValue($name);

        if ($account === null)
            $obj->ispublic->SetValue(true);

        //$obj->CountCreate(); // TODO POLICY
        return $obj;
    }

    /**
     * Creates a new object in the database only (no filesystem call)
     * Also refreshes it and adds counts to the parent
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the item's parent folder
     * @param Account $account the account owning this item
     * @param string $name the name of the item
     * @return static newly created object
     */
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : static
    {
        $obj = static::CreateItem($database, $parent, $account, $name);
        $obj->Refresh();
        $obj->AddCountsToParent($parent);
        return $obj;
    }

    /** True if the item has been refreshed */
    protected bool $refreshed = false;

    /** Updates the metadata of this item by scanning the object in the filesystem */
    protected abstract function Refresh() : void;

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
    public function TryGetDescription() : ?string { return $this->description->TryGetValue(); }
    
    /** Sets this item's description to the given value */
    public function SetDescription(?string $val) : bool { return $this->description->SetValue($val); }

    /** 
     * Returns all comment objects for this item 
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, Social\Comment>
     */
    public function GetComments(?int $limit = null, ?int $offset = null) : array { 
        return Social\Comment::LoadByItem($this->database, $this, $limit, $offset); }

    /** 
     * Returns all like objects for this item 
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, Social\Like>
     */
    public function GetLikes(?int $limit = null, ?int $offset = null) : array { 
        return Social\Like::LoadByItem($this->database, $this, $limit, $offset); }

    /** 
     * Returns all tag objects for this item 
     * @return array<string, Social\Tag>
     */
    public function GetTags() : array { 
        return Social\Tag::LoadByItem($this->database, $this); }

    /** 
     * Returns all share objects for this item 
     * @return array<string, Social\Share>
     */
    public function GetShares() : array {
        return Social\Share::LoadByItem($this->database, $this); }
    
    /**
     * Copies the item to a new name.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param string $name the name of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static;
    
    /**
     * Copies the item to a new parent.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param Folder $parent the parent folder of the new item
     * @param bool $overwrite if true, reuse the duplicate object
     * @return static the newly created object
     */
    public abstract function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : static;
    
    /**
     * Asserts that this item can be moved to the given name
     * @param string $name the item name to check for
     * @param bool $overwrite if true, delete the duplicate item
     * @param bool $reuse if true, return the duplicate item for reuse instead of deleting
     * @throws Exceptions\DuplicateItemException if a duplicate item exists and not $overwrite
     * @throws Exceptions\InvalidRootOpException if used on a root folder
     * @return ?static existing item to be re-used
     */
    protected function CheckName(string $name, bool $overwrite, bool $reuse) : ?static
    {
        $item = Item::TryLoadByParentAndName($this->database, $this->GetParent(), $name); // not static
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this && $item instanceof static) 
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
    protected function CheckParent(Folder $parent, bool $overwrite, bool $reuse) : ?static
    {
        if ($parent->storage->GetObjectID() !== $this->storage->GetObjectID())
            throw new Exceptions\CrossFilesystemException();

        $item = Item::TryLoadByParentAndName($this->database, $parent, $this->GetName()); // not static
        
        if ($item !== null)
        {
            if ($overwrite && $item !== $this && $item instanceof static)
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
    
    /** Returns the filesystem manager's implementor that stores this object */
    protected function GetFilesystem() : Filesystem 
    {
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
        if ($this->database->isReadOnly()) return false;
        return ($time !== null)
            ? $this->date_accessed->SetValue($time)
            : $this->date_accessed->SetTimeNow();
    }
    
    /** Sets the item's created time to the given value or now if null */
    public function SetCreated(?float $time = null) : bool 
    { 
        return ($time !== null)
            ? $this->date_created->SetValue($time)
            : $this->date_created->SetTimeNow();
    }
    
    /** Sets the item's modified time to the given value or now if null */
    public function SetModified(?float $time = null) : bool
    { 
        return ($time !== null)
            ? $this->date_modified->SetValue($time)
            : $this->date_modified->SetTimeNow();
    }

    /** 
     * Maps the given function to all applicable limit objects 
     * @return $this
     */
    /*protected function MapToLimits(callable $func) : void
    {        
        return $this->MapToTotalLimits($func)->MapToTimedLimits($func);
    }*/
    
    /** 
     * Maps the given function to all applicable total limit objects 
     * @return $this
     */
    /*protected function MapToTotalLimits(callable $func) : void
    {        
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem()); if ($fslim !== null) $func($fslim);
        
        if ($this->GetOwnerID()) foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);
        
        return $this;
    }*/
    
    /** 
     * Maps the given function to all applicable timed limit objects 
     * @return $this
     */
    /*protected function MapToTimedLimits(callable $func) : void
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
            $this->AddCountsToPolicy($limit, $add);
 
        if (Config::GetInstance($this->database)->GetAllowTimedStats())
            foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $limit)
                $this->AddCountsToPolicy($limit, $add);
    }*/ 

    /**
     * Adds this item's stats to all filesystem limits
     * @param bool $add if true add, else subtract
     * @return $this
     */
    /*protected function AddStatsToFilesystem(bool $add = true) : bool
    {        
        $total = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        
        if ($total !== null) $this->AddCountsToPolicy($total, $add);
        
        if (Config::GetInstance($this->database)->GetAllowTimedStats())
            foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $limit)
                $this->AddCountsToPolicy($limit, $add);
    }*/
    
    /** Adds this item's stats to the given limit, substracting if not $add */
    protected abstract function AddCountsToPolicy(Policy\Base $limit, bool $add = true) : void;
    
    /** Adds this item's stats to the given folder, substracting if not $add */
    protected abstract function AddCountsToParent(Folder $folder, bool $add = true) : void;
    
    /**
     * Returns a config bool for the item by checking applicable limits
     * @param callable $func the function returning the desired bool
     * @param Account $account the account to check the permission for, or null for defaults
     * @return bool true if (the FS value is null or true) and the account value is true
     */
    protected function GetPolicyBool(callable $func, ?Account $account) : bool
    {
        $fslim = Policy\StandardStorage::TryLoadByStorage($this->database, $this->storage->GetObject());
        $aclim = Policy\StandardAccount::ForceLoadByAccount($this->database, $account);

        return ($fslim === null || $func($fslim) !== false) && $func($aclim);
    }
    
    /** Returns true if the item should allow public modifications */
    public function GetAllowPublicModify() : bool {
       return $this->GetPolicyBool(function(Policy\Standard $p){ return $p->GetAllowPublicModify(); }, null); }
    
    /** Returns true if the item should allow public uploading */
    public function GetAllowPublicUpload() : bool {
        return $this->GetPolicyBool(function(Policy\Standard $p){ return $p->GetAllowPublicUpload(); }, null); }
    
    /** Returns true if the item should allow random/partial writes */
    public function GetAllowRandomWrite(?Account $account) : bool {
        return $this->GetPolicyBool(function(Policy\Standard $p){ return $p->GetAllowRandomWrite(); }, $account); }
    
    /** Returns true if the item should allow sharing */
    public function GetAllowItemSharing(?Account $account) : bool {
        return $this->GetPolicyBool(function(Policy\Standard $p){ return $p->GetAllowItemSharing(); }, $account); }

    /** Returns true if the item should allow group shares (shares with a group) */
    public function GetAllowShareToGroups(?Account $account) : bool {
        return $this->GetPolicyBool(function(Policy\Standard $p){ return $p->GetAllowShareToGroups(); }, $account); }
    
    protected bool $fsDeleted = false;
    public function isFSDeleted() : bool { return $this->fsDeleted; }
    
    /** Deletes the item from the DB only */
    public function NotifyFSDeleted() : void
    {
        $this->fsDeleted = true;
        $this->Delete();
    }

    public function NotifyPreDeleted() : void
    {
        if (($parent = $this->parent->TryGetObject()) !== null)
        {
            if ($parent->isDeleted())
                $this->fsDeleted = true;
        }

        Social\Comment::DeleteByItem($this->database, $this);
        Social\Like::DeleteByItem($this->database, $this);
        Social\Share::DeleteByItem($this->database, $this);
        Social\Tag::DeleteByItem($this->database, $this);

        //$this->MapToLimits(function(Policy\Base $lim){ $lim->CountItem(false); }); // TODO POLICY
    }

    /**
     * Returns a printable client object of this item
     * @param bool $owner if true, show owner-level details
     * @param bool $details if true, show tag and share objects
     * @return ItemJ
     */
    public function GetClientObject(bool $owner, bool $details = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->GetName(),
            'owner' => $this->TryGetOwnerID(),
            'parent' => $this->TryGetParentID(),
            'storage' => $this->storage->GetObjectID(),
            'description' => $this->TryGetDescription(),            
            'date_created' => $this->date_created->GetValue(),
            'date_modified' => $this->date_modified->TryGetValue()
        );
        
        if ($owner)
            $data['date_accessed'] = $this->date_accessed->TryGetValue();
        
        if ($details) 
        {
            $data['tags'] = array_map(function(Social\Tag $e) {
                return $e->GetClientObject(); }, $this->GetTags());
            $data['count_likes'] = Social\Like::CountByItem($this->database, $this, likeval:true);
            $data['count_dislikes'] = Social\Like::CountByItem($this->database, $this, likeval:false);
        }
        
        if ($owner && $details) 
            $data['shares'] = array_map(function(Social\Share $e) {
                return $e->GetClientObject(fullitem:false, owner:true, secret:false); }, $this->GetShares());
        
        return $data;
    }
}
