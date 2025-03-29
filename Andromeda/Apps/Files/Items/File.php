<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, QueryBuilder, TableTypes, FieldTypes};
use Andromeda\Core\IOFormat\InputPath;
use Andromeda\Core\Utilities;
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Policy;

/** 
 * Defines a user-stored file 
 * 
 * File metadata is stored in the database.
 * File content is stored by filesystems.
 * 
 * @phpstan-import-type ItemJ from Item
 * @phpstan-type FileJ \Union<ItemJ, array{size:int}>
 */
class File extends Item
{
    use TableTypes\TableNoChildren;

    /** The disk space size of the file (bytes) */
    protected FieldTypes\IntType $size;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->size = $fields[] = new FieldTypes\IntType('size');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

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

    public function PostConstruct() : void
    {
        if (!$this->isCreated())
            $this->GetFilesystem()->RefreshFile($this);
    }
    
    /** 
     * Returns the size of this file in bytes 
     * @return non-negative-int
     */
    public function GetSize() : int
    {
        $size = $this->size->GetValue();
        assert ($size >= 0); // DB CHECK CONSTRAINT
        return $size;
    }
    
    /**
     * Sets the size of the file and update stats (size must be initialized already!)
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
        if (!$this->size->isInitialized())
            $this->size->SetValue(0);
        
        //$this->MapToLimits(function(Policy\Base $lim)use($delta,$notify){
        //    if (!$this->onOwnerStorage()) $lim->CountSize($delta,$notify); });
        // TODO POLICY move onOwnerStorage checks inside limits - account should not care, filesystems should
        
        if (!$notify) 
        {
            $this->AssertSize($size);
            $this->GetFilesystem()->Truncate($this, $size);
        }

        $this->AddCountsToParent($this->GetParent(), add:false);
        $retval = $this->size->SetValue($size); // AFTER Truncate
        $this->AddCountsToParent($this->GetParent(), add:true);
        return $retval;
    }
    
    /**
     * Checks if the given total size would exceed the limit
     * @param int $size the new size of the file
     * @see Policy\Base::AssertSize()
     */
    public function AssertSize(int $size) : void
    {
        /*$delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        return $this->MapToLimits(function(Policy\Base $lim)use($delta){
            if (!$this->onOwnerStorage()) $lim->AssertSize($delta); });*/
    }
    
    /** 
     * Counts a download by updating limits, and notifying parents if $public 
     * @param bool $public if false, only updates the timestamp and not limit counters
     */
    public function CountDownload(bool $public) : void
    {
        /*if (Main::GetInstance()->GetConfig()->isReadOnly()) return $this;
        
        $this->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetDownloadDate(); });
        
        if ($public)
        {
            $this->MapToLimits(function(Policy\Base $lim){ $lim->CountPublicDownload(); });
            
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
        
        $this->MapToLimits(function(Policy\Base $lim)use($bytes){ $lim->CountBandwidth($bytes); });
        
        return parent::CountBandwidth($bytes);*/
    }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @param int $bytes the bandwidth delta
     * @see Policy\Base::AssertBandwidth()
     */
    public function AssertBandwidth(int $bytes) : void
    {
        /*$fs = $this->GetFilesystem(); // NOTE just get storage directly here, not get filesystem...
        if ($fs->isUserOwned() && $fs->GetStorage()->usesBandwidth()) $bytes *= 2;
        
        return $this->MapToLimits(function(Policy\Base $lim)use($bytes){ $lim->AssertBandwidth($bytes); });*/ // TODO POLICY
    }
    
    protected function AddCountsToPolicy(Policy\Base $limit, bool $add = true) : void { $limit->AddFileCounts($this, $add); }
    
    protected function AddCountsToParent(Folder $folder, bool $add = true) : void { $folder->AddFileCounts($this, $add); }
    
    /** Sends a RefreshFile() command to the filesystem to refresh metadata */
    protected function Refresh() : void
    {
        if (!$this->refreshed)
        {
            $this->refreshed = true;
            $this->GetFilesystem()->RefreshFile($this);
        }
    }

    public function SetName(string $name, bool $overwrite = false) : bool
    {
        static::CheckName($name, $overwrite, reuse:false);
        
        $retval = $this->name->SetValue($name);

        $this->GetFilesystem()->RenameFile($this, $name); 
        $this->Save(); // FS is changed, save now for accurate loads
        return $retval;
    }
    
    public function SetParent(Folder $parent, bool $overwrite = false) : bool
    {
        static::CheckParent($parent, $overwrite, reuse:false);

        $this->AddCountsToParent($this->GetParent(),add:false);
        $retval = $this->parent->SetObject($parent);
        $this->AddCountsToParent($this->GetParent(),add:true);
        
        $this->GetFilesystem()->MoveFile($this, $parent);
        $this->Save(); // FS is changed, save now for accurate loads
        return $retval;
    }

    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static
    {
        $file = static::CheckName($name, $overwrite, reuse:true);
        if ($file !== null) $file->AddCountsToParent($this->GetParent(),add:false);
        $file ??= static::CreateItem($this->database, $this->GetParent(), $owner, $name);
        
        $file->SetSize($this->GetSize(),notify:true);
        $file->AddCountsToParent($this->GetParent());
        $this->GetFilesystem()->CopyFile($this, $file);
        return $file;
    }
    
    public function CopyToParent(?Account $owner, Folder $parent, bool $overwrite = false) : static
    {
        $file = static::CheckParent($parent, $overwrite, reuse:true);
        if ($file !== null) $file->AddCountsToParent($parent,add:false);
        $file ??= static::CreateItem($this->database, $parent, $owner, $this->GetName());
        
        $file->SetSize($this->GetSize(),notify:true);
        $file->AddCountsToParent($parent);
        $this->GetFilesystem()->CopyFile($this, $file);
        return $file;
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
        $file = static::TryLoadByParentAndName($database, $parent, $name);
        if ($file !== null)
        {
            if (!$overwrite) throw new Exceptions\DuplicateItemException();
            else $file->AddCountsToParent($parent,add:false);
        }
        else $file = static::CreateItem($database, $parent, $account, $name);

        $file->SetSize(0, notify:true);
        $file->AddCountsToParent($parent);
        $file->GetFilesystem()->CreateFile($file);
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
        $file = static::TryLoadByParentAndName($database, $parent, $infile->GetName());
        if ($file !== null)
        {
            if (!$overwrite) throw new Exceptions\DuplicateItemException();
            else $file->AddCountsToParent($parent,add:false);
        }
        else $file = static::CreateItem($database, $parent, $account, $infile->GetName());

        $file->SetContents($infile);
        $file->AddCountsToParent($parent);
        return $file;
    }
    
    /**
     * Sets the file's contents to the file of the given path
     * @param InputPath $infile file to load content from
     */
    public function SetContents(InputPath $infile) : void
    {
        $this->SetSize($infile->GetSize(), notify:true);
        $this->GetFilesystem()->ImportFile($this, $infile);
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
        // TODO RAY !! should the Filesystem call SetSize notify?
        $this->SetSize($length, notify:true); // AFTER WriteBytes
    }    

    public function NotifyPreDeleted() : void
    {
        parent::NotifyPreDeleted();

        if (!$this->isFSDeleted())
            $this->GetParent()->AddFileCounts($this, add:false);

        /*$this->MapToLimits(function(Policy\Base $lim){
            if (!$this->onOwnerStorage()) $lim->CountSize($this->GetSize()*-1); });*/ // TODO POLICY
    }

    public function NotifyPostDeleted(): void
    {
        parent::NotifyPostDeleted();

        if (!$this->isFSDeleted())
            $this->GetFilesystem()->DeleteFile($this);
    }
    
    /**
     * Returns a printable client object of the file
     * @return FileJ
     */
    public function GetClientObject(bool $owner, bool $details = false) : array
    {
        $data = parent::GetClientObject($owner,$details);
        
        $data['size'] = $this->GetSize();
    
        return $data;
    }
}
