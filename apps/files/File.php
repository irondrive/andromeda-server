<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\InputFile;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/Folder.php");

/** 
 * Defines a user-stored file 
 * 
 * File metadata is stored in the database.
 * File content is stored by filesystems.
 */
class File extends Item
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'size' => null,   
            'parent' => new FieldTypes\ObjectRef(Folder::class, 'files')
        ));
    }
    
    /** Returns the name of this file */
    public function GetName() : string { return $this->GetScalar('name'); }
    
    /** Returns the parent folder of this file */
    public function GetParent() : Folder { return $this->GetObject('parent'); }
    
    /** Returns the ID of the parent folder */
    public function GetParentID() : string { return $this->GetObjectID('parent'); }
    
    /** Returns the size of the file, in bytes */
    public function GetSize() : int { return $this->TryGetScalar('size') ?? 0; }
    
    /** Returns the number of share objects belonging to the file */
    public function GetNumShares() : int { return $this->CountObjectRefs('shares'); }
    
    /**
     * Sets the size of the file and update stats
     * 
     * if $notify is false, this is a user call to actually change the size of the file.
     * The call will be sent to the filesystem and modify the on-disk object.  If $notify
     * is true, then this is a notification coming up from the filesystem, alerting that the
     * on-disk object has changed in size and the database object needs to be updated
     * @param int $size the new size of the file
     * @param bool $notify if true, this is a notification coming up from the filesystem
     * @return $this
     */
    public function SetSize(int $size, bool $notify = false) : self 
    {
        $delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        $this->GetParent()->DeltaSize($delta);        
        
        $this->MapToLimits(function(Limits\Base $lim)use($delta,$notify){
            if (!$this->onOwnerFS()) $lim->CountSize($delta,$notify); });
        
        if (!$notify) $this->GetFSImpl()->Truncate($this, $size);
        
        return $this->SetScalar('size', $size); 
    }
    
    /**
     * Checks if the given total size would exceed the limit
     * @param int $size the new size of the file
     * @see Limits\Base::CheckSize()
     * @return $this
     */
    public function CheckSize(int $size) : self
    {
        $delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        return $this->MapToLimits(function(Limits\Base $lim)use($delta){
            if (!$this->onOwnerFS()) $lim->CheckSize($delta); });
    }
    
    /** 
     * Counts a download by updating limits, and notifying parents if $public 
     * @param bool public if false, only updates the timestamp and not limit counters
     */
    public function CountDownload(bool $public = true) : self
    {
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        $this->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetDownloadDate(); });
        
        if ($public)
        {
            $this->MapToLimits(function(Limits\Base $lim){ $lim->CountPublicDownload(); });
            
            return parent::CountPublicDownload();
        }
        else return $this;
    }
    
    /** Counts bandwidth by updating the count and notifying parents */
    public function CountBandwidth(int $bytes) : self
    {
        if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        $fs = $this->GetFilesystem(); if ($fs->isUserOwned() && $fs->GetStorage()->usesBandwidth()) $bytes *= 2;
        
        $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->CountBandwidth($bytes); });
        
        return parent::CountBandwidth($bytes);
    }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @param int $bytes the bandwidth delta
     * @see Limits\Base::CheckBandwidth()
     * @return $this
     */
    public function CheckBandwidth(int $bytes) : self
    {
        $fs = $this->GetFilesystem(); if ($fs->isUserOwned() && $fs->GetStorage()->usesBandwidth()) $bytes *= 2;
        
        return $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->CheckBandwidth($bytes); });
    }
    
    protected function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void { $limit->AddFileCounts($this, $add); }
        
    private bool $refreshed = false;
    
    /** Sends a RefreshFile() command to the filesystem to refresh metadata */
    public function Refresh() : self
    {
        if ($this->isCreated()) return $this;
        
        if ($this->deleted) return $this;
        else if (!$this->refreshed)
        {
            $this->refreshed = true;
            $this->GetFSImpl()->RefreshFile($this);
        }
        return $this;
    }

    public function SetName(string $name, bool $overwrite = false) : self
    {
        parent::CheckName($name, $overwrite, false);
        
        $this->GetFSImpl()->RenameFile($this, $name); 
        return $this->SetScalar('name', $name);
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : self
    {
        parent::CheckParent($parent, $overwrite, false);
        
        $this->GetFSImpl()->MoveFile($this, $parent);
        return $this->SetObject('parent', $parent);
    }

    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self
    {
        $file = parent::CheckName($name, $overwrite, true);

        $file ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl()->CopyFile($this, $file); return $file;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : self
    {
        $file = parent::CheckParent($parent, $overwrite, true);
        
        $file ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());        
        
        $this->GetFSImpl()->CopyFile($this, $file); return $file;
    }

    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {        
        return parent::BaseCreate($database)
            ->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('parent',$parent)
            ->SetObject('owner', $account)
            ->SetScalar('name',$name)->CountCreate();
    }
    
    /**
     * Creates a new file on disk and in the DB, copying its content from the given path
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the file's parent folder
     * @param Account $account the account owning this file
     * @param InputFile the input file name and content path
     * @param bool $overwrite if true (reuses the same object)
     * @return self newly created object
     */
    public static function Import(ObjectDatabase $database, Folder $parent, ?Account $account, InputFile $infile, bool $overwrite = false) : self
    {
        $file = static::TryLoadByParentAndName($database, $parent, $infile->GetName());
        if ($file !== null && !$overwrite) throw new DuplicateItemException();
        
        $file ??= static::NotifyCreate($database, $parent, $account, $infile->GetName());
        
        return $file->SetContents($infile->GetPath());     
    }
    
    /**
     * Sets the file's contents to the file of the given path
     * @param string $path file to load bytes from
     * @return $this
     */
    public function SetContents(string $path) : self
    {
        $this->SetSize(filesize($path),true);
        
        $this->GetFSImpl(false)->ImportFile($this, $path); return $this;  
    }
    
    /** Gets the preferred chunk size by the filesystem holding this file */
    public function GetChunkSize() : ?int { return $this->GetFSImpl()->GetChunkSize(); }
    
    /** Returns true if the file resides on a user-added storage */
    public function onOwnerFS() : bool { return $this->GetFilesystem()->isUserOwned(); }
    
    /**
     * Reads content from the file
     * @param int $start the starting byte to read from
     * @param int $length the number of bytes to read
     * @return string the returned data
     */
    public function ReadBytes(int $start, int $length) : string
    {
        $this->SetAccessed(); return $this->GetFSImpl()->ReadBytes($this, $start, $length);
    }
    
    /**
     * Writes content to the file
     * @param int $start the byte offset to write to
     * @param string $data the data to write
     * @return $this
     */
    public function WriteBytes(int $start, string $data) : self
    {
        $length = max($this->GetSize(), $start+strlen($data)); 
        
        $this->CheckSize($length); 
        
        $this->GetFSImpl()->WriteBytes($this, $start, $data); 
        
        $this->SetSize($length, true); 
        
        $this->SetModified(); return $this;
    }    
    
    /** Deletes the file from the DB only */
    public function NotifyDelete() : void 
    { 
        if (!$this->deleted)
            $this->MapToLimits(function(Limits\Base $lim){
                if (!$this->onOwnerFS()) $lim->CountSize($this->GetSize()*-1); });

        parent::Delete(); 
    }

    /** Deletes the file from both the DB and disk */
    public function Delete() : void
    {
        if (!$this->GetParent()->isNotifyDeleted()) 
            $this->GetFSImpl(false)->DeleteFile($this);
        
        $this->NotifyDelete();
    }    
    
    /**
     * Returns all items with a parent that is not owned by the item owner
     *
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, Item> items indexed by ID
     */
    public static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        
        $q->Join($database, Folder::class, 'id', static::class, 'parent')->Where($q->And(
            $q->Equals($database->GetClassTableName(File::class).'.owner', $account->ID()),
            $q->NotEquals($database->GetClassTableName(Folder::class).'.owner', $account->ID())));

        return array_filter(parent::LoadByQuery($database, $q), function(File $file){ return !$file->isWorldAccess(); });
    }
    
    /**
     * @see File::TryGetClientObjects()
     * @throws DeletedByStorageException if the item is deleted
     */
    public function GetClientObject(bool $details = false) : array
    {
        $retval = $this->TryGetClientObject($details);
        if ($retval === null) throw new DeletedByStorageException();
        else return $retval;
    }
    
    /**
     * Returns a printable client object of the file
     * @see Item::SubGetClientObject()
     * @return array|NULL null if deleted, else `{size:int, dates:{created:float,modified:?float,accessed:?float},
         counters:{pubdownloads:int, bandwidth:int, likes:int, dislikes:int}}`
     */
    public function TryGetClientObject(bool $details = false) : ?array
    {
        $this->Refresh(); if ($this->isDeleted()) return null;
        
        $data = array_merge(parent::SubGetClientObject($details),array(
            'size' => $this->GetSize(),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters()
        ));
        
        return $data;
    }
}
