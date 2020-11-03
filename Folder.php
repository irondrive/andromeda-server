<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

class MoveLoopException extends Exceptions\ClientErrorException { public $message = "CANNOT_MOVE_FOLDER_INTO_ITSELF"; }

class Folder extends Item
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'counters__visits' => new FieldTypes\Counter(),
            'counters__size' => new FieldTypes\Counter(),   
            'parent'    => new FieldTypes\ObjectRef(Folder::class, 'folders'),
            'files'     => new FieldTypes\ObjectRefs(File::class, 'parent'),
            'folders'   => new FieldTypes\ObjectRefs(Folder::class, 'parent'),
            'counters__subfiles' => new FieldTypes\Counter(),
            'counters__subfolders' => new FieldTypes\Counter()
        ));
    }

    public function GetName() : ?string   { return $this->TryGetScalar('name'); }
    public function GetSize() : int       { return $this->TryGetCounter('size') ?? 0; }
    public function GetParent() : ?Folder { return $this->TryGetObject('parent'); }
    
    public function GetFiles(?int $limit = null, ?int $offset = null) : array    { $this->Refresh(true); return $this->GetObjectRefs('files',$limit,$offset); }
    public function GetFolders(?int $limit = null, ?int $offset = null) : array  { $this->Refresh(true); return $this->GetObjectRefs('folders',$limit,$offset); }
    
    public function CountVisit() : self   { return $this->DeltaCounter('visits'); }
    
    public function DeltaSize(int $size) : self 
    { 
        if (($parent = $this->GetParent()) !== null)
            $parent->DeltaSize($size);
        return $this->DeltaCounter('size',$size); 
    }   
    
    public function SetName(string $name) : self
    {
        $this->GetFSImpl()->RenameFolder($this, $name);
        return parent::SetName($name);
    }
    
    public function SetParent(Folder $folder) : self
    {
        if ($folder->ID() === $this->ID())
            throw new MoveLoopException();
        
        $tmpparent = $folder;
        while (($tmpparent = $tmpparent->GetParent()) !== null)
        {
            if ($tmpparent->ID() === $this->ID())
                throw new MoveLoopException();
        }
        
        $this->GetFSImpl()->MoveFolder($this, $folder);
        
        return parent::SetParent($folder);
    }
    
    private function AddItemCounts(BaseObject $object) : void
    {
        $this->SetModified();
        $this->DeltaCounter('size', $object->GetSize());
        $this->DeltaCounter('bandwidth', $object->GetBandwidth());
        $this->DeltaCounter('downloads', $object->GetDownloads());
        if (is_a($object, File::class)) $this->DeltaCounter('subfiles');
        if (is_a($object, Folder::class)) $this->DeltaCounter('subfolders');
        
        $parent = $this->GetParent(); if ($parent !== null) $parent->AddItemCounts($object);
    }
    
    private function SubItemCounts(BaseObject $object) : void
    {
        $this->SetModified();
        $this->DeltaCounter('size', $object->GetSize() * -1);
        $this->DeltaCounter('bandwidth', $object->GetBandwidth() * -1);
        $this->DeltaCounter('downloads', $object->GetDownloads() * -1);
        if (is_a($object, File::class)) $this->DeltaCounter('subfiles', -1);
        if (is_a($object, Folder::class)) $this->DeltaCounter('subfolders', -1);
        
        $parent = $this->GetParent(); if ($parent !== null) $parent->SubItemCounts($object);
    }

    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if ($field === 'files' || $field === 'folders') $this->AddItemCounts($object);
        
        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {        
        if ($field === 'files' || $field === 'folders') $this->SubItemCounts($object);
        
        return parent::RemoveObjectRef($field, $object, $notification);
    }
    
    private bool $refreshed = false;
    private bool $subrefreshed = false;
    public function Refresh(bool $doContents = false) : self
    {
        if ($this->deleted) return $this;
        else if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true; $this->subrefreshed = $doContents;
            $this->GetFSImpl()->RefreshFolder($this, $doContents);   
        }
        return $this;
    }

    private static function CreateRoot(ObjectDatabase $database, FSManager $filesystem, ?Account $account) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$filesystem)->SetObject('owner',$account);
    }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        return self::CreateRoot($database, $parent->GetFilesystem(), $account)
        ->SetObject('parent',$parent)->SetScalar('name',$name);
    }
    
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        $folder = self::NotifyCreate($database, $parent, $account, $name);
        $folder->GetFSImpl()->CreateFolder($folder);
            
        return $folder;
    }
    
    public static function LoadRootByAccount(ObjectDatabase $database, Account $account, ?FSManager $filesystem = null) : ?self
    {
        $filesystem ??= FSManager::LoadDefaultByAccount($database, $account); if (!$filesystem) return null;
        
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'),
                                                  $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = self::LoadByQuery($database, $q->Where($where));
        
        if (count($loaded)) return array_values($loaded)[0];
        else {
            $owner = $filesystem->isShared() ? $filesystem->GetOwner() : $account;
            return self::CreateRoot($database, $filesystem, $owner)->Refresh();
        }
    }
    
    private bool $notifyDeleted = false; public function isNotifyDeleted() : bool { return $this->notifyDeleted; }
    
    public function DeleteChildren(bool $isNotify = true) : void
    {
        if (!$isNotify) $this->Refresh(true);
        $this->notifyDeleted = $isNotify;
        $this->DeleteObjects('files');
        $this->DeleteObjects('folders');
    }
    
    public function NotifyDelete(bool $isNotify = true) : void
    {        
        $this->DeleteChildren($isNotify);
        
        parent::Delete();
    }
    
    public function Delete() : void
    {
        $parent = $this->GetParent();
        $isNotify = ($parent !== null && $parent->isNotifyDeleted());
        
        $this->DeleteChildren($isNotify);
        
        if (!$isNotify && $parent !== null) 
            $this->GetFSImpl()->DeleteFolder($this);
        
        parent::Delete();
    }
    
    const ONLYSELF = 1; const WITHCONTENT = 2; const RECURSIVE = 3;
    
    public function GetClientObject(int $level = self::ONLYSELF, ?int $limit = null, ?int $offset = null) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $this->SetAccessed();

        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'owner' => $this->GetObjectID('owner'),
            'parent' => $this->GetObjectID('parent'),
            'filesystem' => $this->GetObjectID('filesystem')
        );
        
        if ($level != self::ONLYSELF)
        {
            $subv = ($level === self::RECURSIVE) ? self::RECURSIVE : self::ONLYSELF;
            $data['folders'] = array_map(function($folder)use($subv){ return $folder->GetClientObject($subv); }, $this->GetFolders($limit,$offset));
            
            if ($limit !== null) $limit -= count($data['folders']);           
            if ($offset !== null) $offset -= count($data['folders']);
            $data['files'] = array_map(function($file){ return $file->GetClientObject(); }, $this->GetFiles($limit,$offset));            
        }        

        return $data;
    }
}
