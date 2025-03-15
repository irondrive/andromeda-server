<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\InputPath;
use Andromeda\Apps\Accounts\Account;

/** 
 * Defines a user-stored file 
 * 
 * File metadata is stored in the database.
 * File content is stored by filesystems.
 */
class File extends Item
{
    use TableTypes\TableNoChildren;

    /**
     * Returns all items that account owns but that reside in a parent that they don't own
     * Does not return items that are world accessible
     * @param ObjectDatabase $database database reference
     * @param Account $account the account that owns the items
     * @return array<string, static> items indexed by ID
     */
    public static function LoadAdoptedByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        
        $q->Join($database, Folder::class, 'id', static::class, 'parent')->Where($q->And(
            $q->Equals($database->GetClassTableName(File::class).'.owner', $account->ID()),
            $q->NotEquals($database->GetClassTableName(Folder::class).'.owner', $account->ID())));

        $objs = $database->LoadObjectsByQuery(static::class, $q);
        return array_filter($objs, function(File $file){ return !$file->isWorldAccess(); });
    }
    
    /**
     * Sets the size of the file and update stats
     * 
     * if $notify is false, this is a user call to actually change the size of the file.
     * The call will be sent to the filesystem and modify the on-disk object.  If $notify
     * is true, then this is a notification coming up from the filesystem, alerting that the
     * on-disk object has changed in size and the database object needs to be updated
     * @param non-negative-int $size the new size of the file
     * @param bool $notify if true, this is a notification coming up from the filesystem
     */
    public function SetSize(int $size, bool $notify = false) : bool
    {
        $delta = $size - $this->size->GetValue();
        $this->GetParent()->DeltaSize($delta);        
        
        //$this->MapToLimits(function(Limits\Base $lim)use($delta,$notify){
        //    if (!$this->onOwnerStorage()) $lim->CountSize($delta,$notify); });
        // TODO LIMITS move onOwnerStorage checks inside limits - account should not care, filesystems should
        
        if (!$notify) 
            $this->GetFilesystem()->Truncate($this, $size);
        return false; // TODO $this->size->SetValue($size); 
    }
    
    /**
     * Checks if the given total size would exceed the limit
     * @param int $size the new size of the file
     * @see Limits\Base::AssertSize()
     */
    public function AssertSize(int $size) : void
    {
        /*$delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        return $this->MapToLimits(function(Limits\Base $lim)use($delta){
            if (!$this->onOwnerStorage()) $lim->AssertSize($delta); });*/
    }
    
    /** 
     * Counts a download by updating limits, and notifying parents if $public 
     * @param bool $public if false, only updates the timestamp and not limit counters
     */
    public function CountDownload(bool $public = true) : void
    {
        /*if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        $this->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetDownloadDate(); });
        
        if ($public)
        {
            $this->MapToLimits(function(Limits\Base $lim){ $lim->CountPublicDownload(); });
            
            return parent::CountPublicDownload();
        }
        else return $this;*/
    }
    
    /** Counts bandwidth by updating the count and notifying parents */
    public function CountBandwidth(int $bytes) : void
    {
        /*if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        $fs = $this->GetFilesystem();
        if ($fs->isUserOwned() && $fs->GetStorage()->usesBandwidth()) $bytes *= 2;
        
        $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->CountBandwidth($bytes); });
        
        return parent::CountBandwidth($bytes);*/
    }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @param int $bytes the bandwidth delta
     * @see Limits\Base::AssertBandwidth()
     */
    public function AssertBandwidth(int $bytes) : void
    {
        /*$fs = $this->GetFilesystem();
        if ($fs->isUserOwned() && $fs->GetStorage()->usesBandwidth()) $bytes *= 2;
        
        return $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->AssertBandwidth($bytes); });*/ // TODO LIMITS
    }
    
    //protected function AddStatsToLimit(Limits\Base $limit, bool $add = true) : void { $limit->AddFileCounts($this, $add); } // TODO STATS
    
    /** Sends a RefreshFile() command to the filesystem to refresh metadata */
    public function Refresh() : void
    {
        if ($this->refreshed || $this->isCreated() || $this->isDeleted()) return;

        $this->refreshed = true;
        // TODO RAY why is requireExist for filesystem here true? this whole chain makes no sense
        // what to do if it doesn't exist anymore? throw? need to rethink all refreshing
        $this->GetFilesystem()->RefreshFile($this);
    }

    public function SetName(string $name, bool $overwrite = false) : bool
    {
        static::CheckName($name, $overwrite, false);
        
        $this->GetFilesystem()->RenameFile($this, $name); 
        return $this->name->SetValue($name);
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : bool
    {
        static::CheckParent($parent, $overwrite, false);
        
        $this->GetFilesystem()->MoveFile($this, $parent);
        return $this->parent->SetObject($parent);
    }

    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static
    {
        $file = static::CheckName($name, $overwrite, true);

        $file ??= static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFilesystem()->CopyFile($this, $file);
        $file->SetSize($this->GetSize(),notify:true);
        return $file;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : static
    {
        $file = static::CheckParent($parent, $overwrite, true);
        
        $file ??= static::NotifyCreate($this->database, $parent, $owner, $this->GetName());
        
        $this->GetFilesystem()->CopyFile($this, $file);
        $file->SetSize($this->GetSize(),notify:true);
        return $file;
    }

    /**
     * Creates a new empty file in the DB and checks for duplicates
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the file's parent folder
     * @param Account $account the account owning this file
     * @param string $name the name for the file
     * @param bool $overwrite if true (reuses the same object)
     * @return static newly created object
     */
    protected static function BasicCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, bool $overwrite = false) : static
    {
        $parent->Refresh(true);
        // TODO RAY !! there will be complication with needing Save()...
        // probably need a global look at saving. maybe make the DB throw an exception
        // if there are things to be saved at the end of the request.  could be temporary debug only?
        // files in for example SetParent should save right away since the FS is now changed
        
        $file = static::TryLoadByParentAndName($database, $parent, $name);
        if ($file !== null && !$overwrite)
            throw new Exceptions\DuplicateItemException();
        
        return $file ?? static::NotifyCreate($database, $parent, $account, $name);
    }
    
    /**
     * Creates a new empty file on disk and in the DB
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the file's parent folder
     * @param Account $account the account owning this file
     * @param string $name the name for the file
     * @param bool $overwrite if true (reuses the same object)
     * @return static newly created object
     */
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, bool $overwrite = false) : static
    {
        $file = static::BasicCreate($database, $parent, $account, $name, $overwrite);
        $file->GetFilesystem()->CreateFile($file);
        $file->SetSize(0,notify:true);
        return $file;
    }
    
    /**
     * Creates a new file on disk and in the DB, importing content from the given path
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the file's parent folder
     * @param Account $account the account owning this file
     * @param InputPath $infile the input file name and content path
     * @param bool $overwrite if true (reuses the same object)
     * @return static newly created object
     */
    public static function Import(ObjectDatabase $database, Folder $parent, ?Account $account, InputPath $infile, bool $overwrite = false) : static
    {
        $obj = static::BasicCreate($database, $parent, $account, $infile->GetName(), $overwrite);
        $obj->SetContents($infile);
        return $obj;
    }
    
    /**
     * Sets the file's contents to the file of the given path
     * @param InputPath $infile file to load content from
     */
    public function SetContents(InputPath $infile) : void
    {
        $this->GetFilesystem(false)->ImportFile($this, $infile);
        $this->SetSize($infile->GetSize(), notify:true);
    }
    
    /** Gets the preferred chunk size by the filesystem holding this file */
    public function GetChunkSize() : ?int { return $this->GetFilesystem()->GetChunkSize(); }
    
    /** Returns true if the file resides on a user-added storage */
    public function onOwnerStorage() : bool { return $this->storage->GetObject()->isUserOwned(); }
    
    /**
     * Reads content from the file
     * @param non-negative-int $start the starting byte to read from
     * @param non-negative-int $length the number of bytes to read
     * @return string the returned data
     */
    public function ReadBytes(int $start, int $length) : string
    {
        $this->SetAccessed();
        return $this->GetFilesystem()->ReadBytes($this, $start, $length);
    }
    
    /**
     * Writes content to the file
     * @param non-negative-int $start the byte offset to write to
     * @param string $data the data to write
     */
    public function WriteBytes(int $start, string $data) : void
    {
        $this->SetModified();
        $length = max($this->GetSize(), $start+strlen($data)); 
        
        $this->AssertSize($length); 
        $this->GetFilesystem()->WriteBytes($this, $start, $data); 
        // TODO RAY should the Filesystem call SetSize notify?
        $this->SetSize($length, notify:true);         
    }    
    
    /** Deletes the file from the DB only */
    public function NotifyFSDeleted() : void 
    {
        /*if (!$this->isDeleted())
            $this->MapToLimits(function(Limits\Base $lim){
                if (!$this->onOwnerStorage()) $lim->CountSize($this->GetSize()*-1); });

        parent::Delete(); */
    }

    /** Deletes the file from both the DB and disk */
    public function Delete() : void
    {
        /*if (!$this->isDeleted() && !$this->GetParent()->isFSDeleted()) 
        {
            $this->GetFilesystem(false)->DeleteFile($this);
        }
        
        $this->NotifyFSDeleted();*/ // TODO delete semantics needs investigation/fixing
    }    
    
    /**
     * @see File::TryGetClientObject()
     * @throws Exceptions\DeletedByStorageException if the item is deleted
     * @return array{}
     */
    public function GetClientObject(bool $owner = false, bool $details = false) : array
    {
        /*$retval = $this->TryGetClientObject($owner,$details);
        if ($retval === null) throw new Exceptions\DeletedByStorageException();
        else return $retval;*/ return [];
    }
    
    /**
     * Returns a printable client object of the file
     * @see Item::SubGetClientObject()
     * @return ?array{} null if deleted, else `{size:int}`
     */
    public function TryGetClientObject(bool $owner = false, bool $details = false) : ?array
    {
        /*$this->Refresh(); if ($this->isDeleted()) return null; // TODO would make more sense if the caller had to do refresh instead
        
        $data = parent::SubGetClientObject($owner,$details);
        
        $data['size'] = $this->GetSize();
        
        return $data;*/ return [];
    }
}
