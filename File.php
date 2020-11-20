<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/Folder.php");

use Andromeda\Core\Exceptions\ServerException;

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
    
    public function GetSize() : int { return $this->TryGetScalar('size') ?? 0; }
    
    public function SetSize(int $size, bool $notify = false) : self 
    {
        if (!$notify)
            $this->GetFSImpl()->Truncate($this, $size);
        
        $oldsize = $this->TryGetScalar('size') ?? 0;
        $this->GetParent()->DeltaSize($size-$oldsize);
        return $this->SetScalar('size', $size); 
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
    
    public function SetName(string $name, bool $overwrite = false, bool $notify = false) : self
    {
        parent::SetName($name, $overwrite);
        if (!$notify) $this->GetFSImpl()->RenameFile($this, $name); 
        return $this;
    }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : self
    {
        parent::SetParent($folder, $overwrite);
        $this->GetFSImpl()->MoveFile($this, $folder);
        return $this;
    }

    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('owner', $account)->SetObject('parent',$parent)->SetScalar('name',$name);
    }
    
    public static function Import(ObjectDatabase $database, Folder $parent, Account $account, string $name, string $path, bool $overwrite = false) : self
    {
        $file = static::NotifyCreate($database, $parent, $account, $name)
            ->SetName($name,$overwrite,true)->SetSize(filesize($path),true);
        
        $file->GetFSImpl()->ImportFile($file, $path); return $file;       
    }
    
    public function GetChunkSize() : ?int { return $this->GetFSImpl()->GetChunkSize(); }
    
    public function ReadBytes(int $start, int $length) : string
    {
        $this->SetAccessed(); return $this->GetFSImpl()->ReadBytes($this, $start, $length);
    }
    
    public function WriteBytes(int $start, string $data) : self
    {
        $this->SetModified(); $this->GetFSImpl()->WriteBytes($this, $start, $data); return $this;
    }    
    
    public function NotifyDelete() : void { parent::Delete(); }

    public function Delete() : void
    {        
        $parent = $this->GetParent();
        $isReal = ($parent === null || !$parent->isNotifyDeleted());
        if ($isReal) $this->GetFSImpl()->DeleteFile($this);
        
        $this->NotifyDelete();
    }
    
    public function GetClientObject(bool $details = false) : ?array
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
