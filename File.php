<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

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
    
    public function GetSize() : int { return $this->TryGetScalar('size') ?? 0; }
    
    public function SetSize(int $size) : self 
    {
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
            $this->GetFilesystemImpl()->RefreshFile($this);
        }
        return $this;
    }
    
    public function SetName(string $name) : self 
    { 
        $this->GetFilesystemImpl()->RenameFile($this, $name); 
        return parent::SetName($name); 
    }
    
    public function SetParent(Folder $folder) : self
    {
        $this->GetFilesystemImpl()->MoveFile($this, $folder);
        return parent::SetParent($folder);
    }

    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('owner', $account)->SetObject('parent',$parent)->SetScalar('name',$name);
    }
    
    public static function Import(ObjectDatabase $database, Folder $parent, Account $account, string $name, string $path) : self
    {
        $file = self::NotifyCreate($database, $parent, $account, $name)->SetSize(filesize($path));        
        $file->GetFilesystemImpl()->ImportFile($file, $path); return $file;       
    }
    
    public function ReadBytes(int $start, int $length) : string
    {
        $this->SetAccessed(); return $this->GetFilesystemImpl()->ReadBytes($this, $start, $length);
    }    
    
    public function NotifyDelete() : void { parent::Delete(); }

    public function Delete() : void
    {
        $parent = $this->GetParent();
        $isReal = ($parent === null || !$parent->isNotifyDeleted());
        if ($isReal) $this->GetFilesystemImpl()->DeleteFile($this);
        
        $this->NotifyDelete();
    }
    
    public function GetClientObject() : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'size' => $this->TryGetScalar('size'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'owner' => $this->GetObjectID('owner'),
            'parent' => $this->GetObjectID('parent')
        );
        
        return $data;
    }
}
