<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/filesystem/FSImpl.php"); use Andromeda\Apps\Files\Filesystem\FSImpl;
require_once(ROOT."/apps/files/limits/Filesystem.php");
require_once(ROOT."/apps/files/limits/Account.php");

/** Exception indicating that files cannot be moved across filessytems */
class CrossFilesystemException extends Exceptions\ClientErrorException { public $message = "FILESYSTEM_MISMATCH"; }

/** Exception indicating that the item target name already exists */
class DuplicateItemException extends Exceptions\ClientErrorException   { public $message = "ITEM_ALREADY_EXISTS"; }

/**
 * An abstract class defining a user-created item in a filesystem.
 * 
 * Like other objects, items are generally referred to by ID, not name path.
 * It is therefore somewhat like an object storage, except that every item
 * must have exactly one parent (other than the root folder).
 */
abstract class Item extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'dates__modified' => null,
            'dates__accessed' => new FieldTypes\Scalar(null, true),         
            'counters__bandwidth' => new FieldTypes\Counter(true),  // total bandwidth used (recursive for folders)
            'counters__downloads' => new FieldTypes\Counter(),      // total download count (recursive for folders)
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'filesystem' => new FieldTypes\ObjectRef(FSManager::class),
            'likes' => new FieldTypes\ObjectRefs(Like::class, 'item', true), // links to like objects
            'counters__likes' => new FieldTypes\Counter(),      // actual total # of likes
            'counters__dislikes' => new FieldTypes\Counter(),   // actual total # of dislikes
            'tags' => new FieldTypes\ObjectRefs(Tag::class, 'item', true),
            'comments' => new FieldTypes\ObjectRefs(Comment::class, 'item', true),
            'shares' => new FieldTypes\ObjectRefs(Share::class, 'item', true)
        ));
    }
    
    /** Updates the metadata of this item by scanning the object in the filesystem */
    public abstract function Refresh() : self;
    
    /**
     * Refreshes the file from disk in case it should be removed from the DB
     * @see Item::Refresh()
     * @see BaseObject::isDeleted()
     */
    public function isDeleted() : bool { $this->Refresh(); return parent::isDeleted(); }
    
    /** Returns the owner of this item, or null if it's on a shared FS */
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    /** Returns the ID of the owner of this item (or null) */
    public function GetOwnerID() : ?string { return $this->TryGetObjectID('owner'); }
    
    /** Returns the name of this item */
    public abstract function GetName() : ?string;
    
    /** Returns the size of this item in bytes */
    public abstract function GetSize() : int;
    
    /** Returns the parent folder of this item */
    public abstract function GetParent() : ?Folder;
    
    /** Returns the parent ID of this item */
    public abstract function GetParentID() : ?string;

    /** Renames the item. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetName(string $name, bool $overwrite = false) : self;
    
    /** Moves the item to a new parent. If $overwrite, deletes an object if the target already exists. */
    public abstract function SetParent(Folder $folder, bool $overwrite = false) : self;
    
    /**
     * Copies the item to a new name.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param string $name the name of the new item
     * @param bool if true, overwrite a item that exists with the same name
     */
    public abstract function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self;
    
    /**
     * Copies the item to a new parent.  If $overwrite, deletes an object if the target already exists.
     * @param ?Account $owner the owner of the new item
     * @param Folder $folder the parent folder of the new item
     * @param bool if true, overwrite a item that exists with the same name
     */
    public abstract function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self;
    
    /**
     * Checks whether the item can be given the given name
     * @param string $name the new name to check
     * @param bool $overwrite if true and the name already exists, deletes it
     * @throws DuplicateItemException if $overwrite is false and the name already exists
     * @return $this
     */
    protected function CheckName(string $name, bool $overwrite = false) : self
    {
        if (($olditem = static::TryLoadByParentAndName($this->database, $this->GetParent(), $name)) !== null)
        { 
            if ($overwrite && $olditem !== $this) $olditem->Delete(); 
            else throw new DuplicateItemException(); 
        }
        return $this;
    }
    
    /**
     * Checks whether the item can be under the given parent
     * @param Folder $folder the new potential parent
     * @param bool $overwrite if true and the name already exists, deletes it
     * @throws CrossFilesystemException if the new parent is not on the same filesystem
     * @throws DuplicateItemException if $overwrite is false and the name already exists
     * @return $this
     */
    protected function CheckParent(Folder $folder, bool $overwrite = false) : self
    {
        if ($folder->GetFilesystemID() !== $this->GetFilesystemID())
            throw new CrossFilesystemException();

        if (($olditem = static::TryLoadByParentAndName($this->database, $folder, $this->GetName())) !== null) 
        { 
            if ($overwrite && $olditem !== $this) $olditem->Delete(); 
            else throw new DuplicateItemException(); 
        }
        return $this;
    }
    
    /**
     * Creates a new object in the database only (no filesystem call)
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the item's parent folder
     * @param Account $account the account owning this item
     * @param string $name the name of the item
     * @return self newly created object
     */
    public abstract static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self;
    
    /** Returns the filesystem manager that stores this object */
    protected function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    
    /** Returns the ID of the object's filesystem manager */
    protected function GetFilesystemID() : string { return $this->GetObjectID('filesystem'); }
    
    /** Returns the filesystem manager's implementor that stores this object */
    protected function GetFSImpl() : FSImpl { return $this->GetFilesystem()->GetFSImpl(); }
    
    /** Sets the item's access time to the given value or now if null */
    public function SetAccessed(?int $time = null) : self { return $this->SetDate('accessed', $time); }
    
    /** Sets the item's created time to the given value or now if null */
    public function SetCreated(?int $time = null) : self  { return $this->SetDate('created', $time); }
    
    /** Sets the item's modified time to the given value or now if null */
    public function SetModified(?int $time = null) : self { return $this->SetDate('modified', $time); }
    
    /** Returns the bandwidth used by the item in bytes */
    public function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    
    /** Returns the download count of the item */
    public function GetDownloads() : int { return $this->GetCounter('downloads'); }
    
    /**
     * Returns the like objects for this item
     * @return array<string, Like> likes indexed by ID
     */
    public function GetLikes() : array { return $this->GetObjectRefs('likes'); }
    
    /**
     * Returns the tag objects for this item
     * @return array<string, Tag> tags indexed by ID
     */
    public function GetTags() : array { return $this->GetObjectRefs('tags'); }
    
    /**
     * Returns the comment objects for this item
     * @return array<string, Comment> comments indexed by ID
     */
    public function GetComments() : array { return $this->GetObjectRefs('comments'); }
    
    /**
     * Returns the share objects for this item
     * @return array<string, Share> shares indexed by ID
     */
    public function GetShares() : array { return $this->GetObjectRefs('shares'); }
    
    /** Returns the number of shares on this object */
    public function GetNumShares() : int { return $this->CountObjectRefs('shares'); }
    
    /** Registers a new item with all limit objects */
    protected function CountCreate() : self 
    {
        return $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(); })
                    ->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetUploadDate(); }); 
    }
    
    /** Counts a download on the item and its parents */
    public function CountDownload() : self            
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountDownload();
        return $this->DeltaCounter('downloads'); 
    }
    
    /** Counts the given bandwidth on the item and its parents */
    public function CountBandwidth(int $bytes) : self 
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountBandwidth($bytes);
        return $this->DeltaCounter('bandwidth', $bytes); 
    }
    
    /**
     * Increments the item's like counter
     * @param bool $value if true, count a like, else count a dislike
     * @param bool $count if false, decrement, if true increment
     * @return $this
     */
    public function CountLike(bool $value, bool $count = true) : self
    { 
        if ($value) $this->DeltaCounter('likes', $count ? 1 : -1);
        else $this->DeltaCounter('dislikes', $count ? 1 : -1);
        return $this;
    }    
    
    /**
     * Increments the item's share counter
     * @param bool $count if true, increment, if false, decrement
     * @return $this
     */
    public function CountShare(bool $count = true) : self
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountSubShare($count);
        
        return $this->MapToLimits(function(Limits\Base $lim)use($count){ $lim->CountShare($count); });
    }

    /** Maps the given function to all applicable limit objects */
    protected function MapToLimits(callable $func) : self
    {
        return $this->MapToTotalLimits($func)->MapToTimedLimits($func);
    }
    
    /** Maps the given function to all applicable total limit objects */
    protected function MapToTotalLimits(callable $func) : self
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem()); if ($fslim !== null) $func($fslim);
        if ($this->GetOwnerID()) foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);
        return $this;
    }
    
    /** Maps the given function to all applicable timed limit objects */
    protected function MapToTimedLimits(callable $func) : self
    {
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
        foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $lim) $func($lim);        
        if ($this->GetOwnerID()) foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);        
        return $this;
    }
    
    /**
     * Returns a config bool for the item by checking applicable limits
     * @param callable $func the function returning the desired bool
     * @param Account $account the account to check the permission for, or null for defaults
     * @return bool true if (the FS value is null or true) and the account value is true
     */
    protected function GetLimitsBool(callable $func, ?Account $account) : bool
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        $aclim = Limits\AccountTotal::LoadByAccount($this->database, $account, true);
        return ($fslim === null || $func($fslim) !== false) && $func($aclim);
    }
    
    /** Returns true if the item should allow public modifications */
    public function GetAllowPublicModify() : bool {
       return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicModify(); }, null); }
    
    /** Returns true if the item should allow public uploading */
    public function GetAllowPublicUpload() : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicUplaod(); }, null); }    
    
    /** Returns true if the item should allow random/partial writes */
    public function GetAllowRandomWrite(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowRandomWrite(); }, $account); }
    
    /** Returns true if the item should allow sharing */
    public function GetAllowItemSharing(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowItemSharing(); }, $account); }
    
    /** Returns true if the item should allow public shares (shares with all users) */
    public function GetAllowShareEveryone(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareEveryone(); }, $account); }
        
    /** Deletes the item from the DB only */
    public abstract function NotifyDelete() : void;
    
    /** Deleting an item also deletes all of its component objects (likes, tags, comments, shares) */
    public function Delete() : void
    {
        $this->DeleteObjectRefs('likes');
        $this->DeleteObjectRefs('tags');
        $this->DeleteObjectRefs('comments');
        $this->DeleteObjectRefs('shares');
        
        $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(false); });
        
        parent::Delete();
    }    

    /**
     * Attemps to load this item by the given info 
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the parent folder of the item
     * @param string $name the name of the item to load
     * @return self|NULL loaded item or null if not found
     */
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, string $name) : ?self
    {
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name));        
        return parent::TryLoadUniqueByQuery($database, $q->Where($where));
    }
    
    /**
     * Returns an array of all items belonging to the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner to load objects for
     * @return array<string, Item> items indexed by ID
     */
    public static function LoadByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $where = $q->Equals('owner',$account->ID());
        return parent::LoadByQuery($database, $q->Where($where));
    }
    
    const DETAILS_NONE = 0; const DETAILS_PUBLIC = 1; const DETAILS_OWNER = 2; 
    
    /**
     * Returns a printable client object of this item
     * @param int $details level of details, see return
     * @return array|NULL `{id:string, name:?string, owner:?string, parent:?string}` \
         if details, add: `{comments:[id:Comment], likes:Like[], tags:[id:Tag], shares:[id:Share]}` \
         if details is public, comments will not show private owner comments
     * @see Comment::GetClientObject()
     * @see Tag::GetClientObject()
     * @see Like::GetClientObject()
     * @see Share::GetClientObject()
     */
    public function SubGetClientObject(int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'owner' => $this->GetOwnerID(),
            'parent' => $this->GetParentID()
        );
                
        $mapobj = function($e) { return $e->GetClientObject(); };

        if ($details)
        {
            $comments = $this->GetComments(); // TODO limit/offset for these!
            if ($details < self::DETAILS_OWNER)
                $comments = array_filter($comments, function($c){ return !$c->IsPrivate(); });
                
            $data['likes'] = array_map($mapobj, array_values($this->GetLikes())); // TODO limit/offset for these!
            $data['tags'] = array_map($mapobj, $this->GetTags());
            $data['comments'] = array_map($mapobj, $comments);
            $data['shares'] = array_map($mapobj, $this->GetShares());
        }
        
        return $data;
    }
    
    public abstract function GetClientObject() : ?array;
}
