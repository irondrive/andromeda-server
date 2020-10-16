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
            'parent' => new FieldTypes\ObjectRef(Folder::class, 'files')
        ));
    }
    
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, bool $isNotify = false) : self
    {
        $file = parent::BaseCreate($database)->SetObject('filesystem',$parent->GetFilesystem())
            ->SetObject('owner', $account)->SetObject('parent',$parent)->SetScalar('name',$name);
        
        // if (!$isNotify) $file->GetFilesystemImpl()->CreateFile($file);
        // TODO FILE CRAETE send to underlying storage, and notify parameter
        
        return $file;
    }
    
    public function GetName() : string   { return $this->GetScalar('name'); }
    public function GetParent() : Folder { return $this->GetObject('parent'); }
    
    public function SetSize(int $size) : self { return $this->DeltaCounter('size', $size - ($this->TryGetCounter('size') ?? 0)); }
    
    private bool $refreshed = false;
    protected function Refresh() : void
    {
        if ($this->isDeleted()) return;
        else if (!$this->refreshed)
        {
            $this->refreshed = true;
            $this->GetFilesystemImpl()->RefreshFile($this);
        }
    }
    
    public static function DeleteByParent(ObjectDatabase $database, Parent $parent) : void
    {
        parent::DeleteManyMatchingAll($database, array('parent'=>$parent->ID()));
    }
    
    public function NotifyDelete() : void { parent::Delete(); }

    public function Delete() : void
    {
        $parent = $this->GetParent();
        $isReal = ($parent === null || !$parent->isNotifyDeleted());
        if ($isReal) $this->GetFilesystemImpl()->DeleteFile($this);
        
        $this->NotifyDelete();
    }
    
    public function GetClientObject() : array
    {
        $this->Refresh();
        if ($this->isDeleted()) return null;
        
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'owner' => $this->GetObjectID('owner'),
            'parent' => $this->GetObjectID('parent')
        );
        
        return $data;
    }
}
