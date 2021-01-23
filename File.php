<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/exceptions/Exceptions.php");

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/Folder.php");

class File extends Item
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'size' => null,   
            'parent' => new FieldTypes\ObjectRef(Folder::class, 'files')
        ));
    }
    
    public function GetName() : string   { return $this->GetScalar('name'); }
    public function GetParent() : Folder { return $this->GetObject('parent'); }
    public function GetParentID() : string { return $this->GetObjectID('parent'); }
    
    public function GetSize() : int { return $this->TryGetScalar('size') ?? 0; }
    
    public function GetNumShares() : int { return $this->CountObjectRefs('shares'); }
    
    public function SetSize(int $size, bool $notify = false) : self 
    {
        if (!$notify)
            $this->GetFSImpl()->Truncate($this, $size);
        
        $delta = $size - ($this->TryGetScalar('size') ?? 0);
        
        $this->GetParent()->DeltaSize($delta);
        
        // TODO this assumes the FS never changes - will not work for cross-FS move!
        $this->MapToLimits(function(Limits\Base $lim)use($delta){ 
            if (!$this->onOwnerFS()) $lim->CountSize($delta); });
        
        return $this->SetScalar('size', $size); 
    }
    
    public function CountDownload(bool $public = true) : self
    {
        $this->MapToLimits(function(Limits\Base $lim){ $lim->CountDownload(); })
             ->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetDownloadDate(); });
        
        return parent::CountDownload($public);
    }
    
    public function CountBandwidth(int $bytes) : self
    {
        $this->MapToLimits(function(Limits\Base $lim)use($bytes){ $lim->CountBandwidth($bytes); });
        
        return parent::CountBandwidth($bytes);
    }
        
    private bool $refreshed = false;
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
    
    public static function Import(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, string $path, bool $overwrite = false) : self
    {
        $file = static::NotifyCreate($database, $parent, $account, $name)
            ->CheckName($name,$overwrite)->SetSize(filesize($path),true); // TODO maybe reuse the same object for overwrite? may not want to clear all comments/shares, etc.
        
        $file->GetFSImpl()->ImportFile($file, $path); return $file;       
    }
    
    public function GetChunkSize() : ?int { return $this->GetFSImpl()->GetChunkSize(); }
    public function onOwnerFS() : bool { return $this->GetFilesystem()->isUserOwned(); }
    
    public function ReadBytes(int $start, int $length) : string
    {
        $this->SetAccessed(); return $this->GetFSImpl()->ReadBytes($this, $start, $length);
    }
    
    public function WriteBytes(int $start, string $data) : self
    {
        $this->SetModified(); $this->GetFSImpl()->WriteBytes($this, $start, $data); return $this;
    }    
    
    public function NotifyDelete() : void 
    { 
        $this->MapToLimits(function(Limits\Base $lim){
            if (!$this->onOwnerFS()) $lim->CountSize($this->GetSize()*-1); });
        
        parent::Delete(); 
    }

    public function Delete() : void
    {        
        $parent = $this->GetParent();
        $isReal = ($parent === null || !$parent->isNotifyDeleted());
        if ($isReal) $this->GetFSImpl()->DeleteFile($this);
        
        $this->NotifyDelete();
    }
    
    public function GetClientObject(int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array_merge(parent::GetItemClientObject($details),array(
            'size' => $this->TryGetScalar('size'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters()
        ));
        
        return $data;
    }
}
