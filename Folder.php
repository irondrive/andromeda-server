<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

class InvalidDestinationException extends Exceptions\ClientErrorException { public $message = "INVALID_FOLDER_DESTINATION"; }

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
            'counters__subfolders' => new FieldTypes\Counter(),
            'counters__subshares' => new FieldTypes\Counter()
        ));
    }

    public function GetName() : ?string   { return $this->TryGetScalar('name'); }
    public function GetSize() : int       { return $this->TryGetCounter('size') ?? 0; }
    public function GetParent() : ?Folder { return $this->TryGetObject('parent'); }
    public function GetParentID() : ?string { return $this->TryGetObjectID('parent'); }
    
    public function GetFiles(?int $limit = null, ?int $offset = null) : array    { $this->Refresh(true); return $this->GetObjectRefs('files',$limit,$offset); }
    public function GetFolders(?int $limit = null, ?int $offset = null) : array  { $this->Refresh(true); return $this->GetObjectRefs('folders',$limit,$offset); }
    
    public function GetNumFiles() : int { return $this->GetCounter('subfiles'); }
    public function GetNumFolders() : int { return $this->GetCounter('subfolders'); }
    
    public function GetNumItems() : int { return $this->GetNumFiles() + $this->GetNumFolders(); }
    public function GetTotalShares() : int { return $this->GetNumShares() + $this->GetCounter('subshares'); }
    
    public function CountVisit() : self   { return $this->DeltaCounter('visits'); }
    
    public function DeltaSize(int $size) : self 
    { 
        if (($parent = $this->GetParent()) !== null)
            $parent->DeltaSize($size);
        return $this->DeltaCounter('size',$size); 
    }   
    
    public function SetName(string $name, bool $overwrite = false) : self
    {
        parent::CheckName($name, $overwrite);
        $this->GetFSImpl()->RenameFolder($this, $name);
        return $this->SetScalar('name', $name);
    }

    public function SetParent(Folder $folder, bool $overwrite = false) : self
    {
        $this->CheckIsNotChildOrSelf($folder);        
        parent::CheckParent($folder, $overwrite);
        
        $this->GetFSImpl()->MoveFolder($this, $folder);
        return $this->SetObject('parent', $folder);
    }
    
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self 
    {
        parent::CheckName($name, $overwrite);
        
        if (!$this->GetParentID()) throw new InvalidDestinationException();
        
        $newfolder = static::NotifyCreate($this->database, $this->GetParent(), $owner, $name);
        
        $this->GetFSImpl()->CopyFolder($this, $newfolder); return $newfolder;
    }
    
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self
    {
        parent::CheckParent($folder, $overwrite);
        $this->CheckIsNotChildOrSelf($folder);
        
        $newfolder = static::NotifyCreate($this->database, $folder, $owner, $this->GetName());
        
        $this->GetFSImpl()->CopyFolder($this, $newfolder); return $newfolder;
    }    
    
    private function CheckIsNotChildOrSelf(Folder $folder) : void
    {
        if ($folder->ID() === $this->ID())
            throw new InvalidDestinationException();
        
        while (($folder = $folder->GetParent()) !== null)
        {
            if ($folder->ID() === $this->ID())
                throw new InvalidDestinationException();
        }
    }
    
    private function AddItemCounts(Item $item, bool $sub = false) : void
    {
        $this->SetModified(); $val = $sub ? -1 : 1;
        $this->DeltaCounter('size', $item->GetSize() * $val);
        $this->DeltaCounter('bandwidth', $item->GetBandwidth() * $val);
        $this->DeltaCounter('downloads', $item->GetDownloads() * $val);        
        
        if ($item instanceof File) 
        {
            $this->DeltaCounter('subfiles', $val);
            
            $this->DeltaCounter('subshares', $item->GetNumShares() * $val);
        }
        
        if ($item instanceof Folder) 
        {
            $this->DeltaCounter('subfolders', $val);
            
            $this->DeltaCounter('subfiles', $item->GetNumFiles() * $val);
            $this->DeltaCounter('subfolders', $item->GetNumFolders() * $val);
            $this->DeltaCounter('subshares', $item->GetTotalShares() * $val);
        }
        
        $parent = $this->GetParent(); if ($parent !== null) $parent->AddItemCounts($item, $sub);
    }
    
    protected function CountSubShare(bool $count = true) : self
    {
        $this->DeltaCounter('subshares', $count ? 1 : -1);
    }

    protected function AddObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {
        if ($field === 'files' || $field === 'folders') $this->AddItemCounts($object, false);
        
        return parent::AddObjectRef($field, $object, $notification);
    }
    
    protected function RemoveObjectRef(string $field, BaseObject $object, bool $notification = false) : self
    {        
        if ($field === 'files' || $field === 'folders') $this->AddItemCounts($object, true);
        
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
        return static::CreateRoot($database, $parent->GetFilesystem(), $account)
            ->SetObject('parent',$parent)->SetScalar('name',$name)->CountCreate();
    }
    
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self
    {
        $folder = static::NotifyCreate($database, $parent, $account, $name)->CheckName($name);

        $folder->GetFSImpl()->CreateFolder($folder); return $folder;
    }
    
    public static function LoadRootByAccountAndFS(ObjectDatabase $database, Account $account, ?FSManager $filesystem = null) : ?self
    {
        $filesystem ??= FSManager::LoadDefaultByAccount($database, $account); if (!$filesystem) return null;
        
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'),
                                                  $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = static::TryLoadUniqueByQuery($database, $q->Where($where));
        if ($loaded) return $loaded;
        else {
            $owner = $filesystem->isShared() ? $filesystem->GetOwner() : $account;
            return self::CreateRoot($database, $filesystem, $owner)->Refresh();
        }
    }

    public static function LoadRootsByFSManager(ObjectDatabase $database, FSManager $filesystem) : array
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('filesystem',$filesystem->ID()), $q->IsNull('parent'));
        
        return static::LoadByQuery($database, $q->Where($where));
    }
    
    public static function LoadRootsByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('owner',$account->ID()), $q->IsNull('parent'));
        
        return static::LoadByQuery($database, $q->Where($where));
    }
    
    public static function DeleteRootsByFSManager(ObjectDatabase $database, FSManager $filesystem) : void
    {
        foreach (static::LoadRootsByFSManager($database, $filesystem) as $folder)
        {
            if ($filesystem->isShared()) $folder->NotifyDelete(); else $folder->Delete();
        }
    }
    
    public static function DeleteRootsByAccount(ObjectDatabase $database, Account $account) : void
    {
        foreach (static::LoadRootsByAccount($database, $account) as $folder)
        {
            if ($folder->GetFilesystem()->isShared()) $folder->NotifyDelete(); else $folder->Delete();
        }
    }
    
    private bool $notifyDeleted = false; public function isNotifyDeleted() : bool { return $this->notifyDeleted; }
    
    public function DeleteChildren(bool $isNotify = true) : void
    {
        if (!$isNotify) $this->Refresh(true);
        $this->notifyDeleted = $isNotify;
        $this->DeleteObjectRefs('files');
        $this->DeleteObjectRefs('folders');
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
    
    private function RecursiveItems(?bool $files = true, ?bool $folders = true, ?int $limit = null, ?int $offset = null) : array
    {
        $items = array();
        
        if ($limit && $offset) $limit += $offset;
        
        foreach ($this->GetFolders($limit) as $subfolder)
        {
            $newitems = $subfolder->RecursiveItems($files,$folders,$limit);
            
            $items = array_merge($items, $newitems);            
            if ($limit !== null) $limit -= count($newitems);
        }
        
        $items = array_merge($items, $this->GetFiles($limit));
        
        if ($offset) $items = array_slice($items, $offset);
        
        return $items;
    }

    public function GetClientObject(bool $files = false, bool $folders = false, bool $recursive = false,
        ?int $limit = null, ?int $offset = null, int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $this->SetAccessed();

        $data = array_merge(parent::GetItemClientObject($details),array(
            'filesystem' => $this->GetObjectID('filesystem')
        ));
        
        
        if ($recursive && ($files || $folders))
        {
            $items = $this->RecursiveItems($files,$folders,$limit,$offset);
            
            if ($folders)
            {
                $subfolders = array_filter($items, function($item){ return ($item instanceof Folder); });
                $data['folders'] = array_map(function($folder){ return $folder->GetClientObject(); },$subfolders);
            }
            
            if ($files)
            {
                $subfiles = array_filter($items, function($item){ return ($item instanceof File); });
                $data['files'] = array_map(function($file){ return $file->GetClientObject(); },$subfiles);
            }            
        }
        else
        {
            if ($folders)
            {
                $data['folders'] = array_map(function($folder){
                    return $folder->GetClientObject(); }, $this->GetFolders($limit,$offset));
            
                $numitems = count($data['folders']);
                if ($offset !== null) $offset = max(0, $offset-$numitems);
                if ($limit !== null) $limit = max(0, $limit-$numitems);
            }
            
            if ($files)
            {
                $data['files'] = array_map(function($file){
                    return $file->GetClientObject(); }, $this->GetFiles($limit,$offset));
            }
        }        
        
        $data['dates'] = $this->GetAllDates();
        $data['counters'] = $this->GetAllCounters();

        return $data;
    }
}
