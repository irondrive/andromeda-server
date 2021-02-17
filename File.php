<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\CounterOverLimitException;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

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
        
        if (!$notify)
        {
            $this->MapToLimits(function(Limits\Base $lim)use($delta){ 
                if (!$this->onOwnerFS()) $lim->CountSize($delta); });
            
            $this->GetFSImpl()->Truncate($this, $size);                
        }
        
        return $this->SetScalar('size', $size); 
    }
    
    /**
     * Checks if the given total size would exceed the limit
     * @param int $size the new size of the file
     * @see Limits\Base::CheckBandwidth()
     * @return $this
     */
    public function CheckSize(int $size) : self
    {
        $delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        $this->MapToLimits(function(Limits\Base $lim)use($delta){
            if (!$this->onOwnerFS()) $lim->CheckSize($delta); });
    }
    
    /** 
     * Counts a download by updating limits, and notifying parents if $public 
     * @param bool public if false, only updates limits and does not update item counters
     */
    public function CountDownload(bool $public = true) : self
    {
        $this->MapToLimits(function(Limits\Base $lim){ $lim->CountDownload(); })
             ->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetDownloadDate(); });
        
        if ($public) return parent::CountDownload(); else return $this;
    }
    
    /** Counts bandwidth by updating the count and notifying parents */
    public function CountBandwidth(int $bytes) : self
    {
        $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->CountBandwidth($bytes); });
        
        return parent::CountBandwidth($bytes);
    }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @param int $size the bandwidth delta
     * @see Limits\Base::CheckBandwidth()
     * @return $this
     */
    public function CheckBandwidth(int $delta) : self
    {
        $this->MapToLimits(function(Limits\Base $lim)use($delta){
            if (!$this->onOwnerFS()) $lim->CheckBandwidth($delta); });
    }
        
    private bool $refreshed = false;
    
    /** Sends a RefreshFile() command to the filesystem to refresh metadata */
    public function Refresh() : self
    {
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
        parent::CheckName($name, $overwrite);
        $this->GetFSImpl()->RenameFile($this, $name); 
        return $this->SetScalar('name', $name);
    }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : self
    {        
        parent::CheckParent($folder, $overwrite);
        $this->GetFSImpl()->MoveFile($this, $folder);
        return $this->SetObject('parent', $folder);
    }

    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self
    {
        parent::CheckName($name, $overwrite);
        $newfile = static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl()->CopyFile($this, $newfile); return $newfile;
    }
    
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self
    {        
        parent::CheckParent($folder, $overwrite);
        $newfile = static::NotifyCreate($this->database, $folder, $owner, $this->GetName());
        
        $this->GetFSImpl()->CopyFile($this, $newfile); return $newfile;
    }

    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('owner', $account)->SetObject('parent',$parent)->SetScalar('name',$name)->CountCreate();
    }
    
    /**
     * Creates a new file on disk and in the DB, copying its content from the given path
     * @param ObjectDatabase $database database reference
     * @param Folder $parent the file's parent folder
     * @param Account $account the account owning this file
     * @param string $name the name of the file
     * @param string $path the path of the file content
     * @param bool $overwrite if true
     * @return self newly created object
     */
    public static function Import(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, string $path, bool $overwrite = false) : self
    {
        $file = static::NotifyCreate($database, $parent, $account, $name)
            ->CheckName($name,$overwrite)->SetSize(filesize($path),true); // TODO maybe reuse the same object for overwrite? may not want to clear all comments/shares, etc.
        
        $file->GetFSImpl()->ImportFile($file, $path); return $file;       
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
            $this->GetFSImpl()->DeleteFile($this);
        
        $this->NotifyDelete();
    }    
    
    /**
     * Returns a printable client object of the file
     * @see Item::SubGetClientObject()
     * @return array|NULL null if deleted, else `{size:int, dates:{created:float,modified:?float,accessed:?float},
         counters:{downloads:int, bandwidth:int, likes:int, dislikes:int}}`
     */
    public function GetClientObject(int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array_merge(parent::SubGetClientObject($details),array(
            'size' => $this->TryGetScalar('size'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters()
        ));
        
        return $data;
    }
}
