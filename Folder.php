<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Item.php");

class Folder extends Item
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'counters__visits' => new FieldTypes\Counter(),
            'parent'    => new FieldTypes\ObjectRef(Folder::class, 'folders'),
            'files'     => new FieldTypes\ObjectRefs(File::class, 'parent'),
            'folders'   => new FieldTypes\ObjectRefs(Folder::class, 'parent')
        ));
    }
    
    // TODO FUTURE folders will need to track number of subfiles/folders separately since the ObjectRefs is not recursive...
    
    public function GetName() : ?string   { return $this->TryGetScalar('name'); }
    public function GetParent() : ?Folder { return $this->TryGetObject('parent'); }
    public function GetFiles() : array    { $this->Refresh(true); return $this->GetObjectRefs('files'); }
    public function GetFolders() : array  { $this->Refresh(true); return $this->GetObjectRefs('folders'); }   
    
    private bool $refreshed = false;
    private bool $subrefreshed = false;
    protected function Refresh(bool $doContents) : void
    {
        if ($this->isDeleted()) return;
        else if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true; $this->subrefreshed = $doContents;
            $this->GetFilesystemImpl()->RefreshFolder($this, $doContents);   
        }
    }

    private static function CreateRoot(ObjectDatabase $database, Filesystem $filesystem, ?Account $account) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$filesystem)->SetObject('owner',$account);
    }
    
    public static function Create(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, bool $isNotify = false) : self
    {
        $folder = self::CreateRoot($database, $parent->GetFilesystem(), $account)
            ->SetObject('parent',$parent)->SetScalar('name',$name);

        if (!$isNotify) $folder->GetFilesystemImpl()->CreateFolder($folder);
            
        return $folder;
    }

    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $loaded = self::TryLoadByID($database, $id);        
        if (!$loaded) return null;
        
        $owner = $loaded->GetObjectID('owner');
        return ($owner === null || $owner === $account->ID()) ? $loaded : null;
    }
    
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, Account $account, string $name) : ?self
    {
        $criteria = array('parent'=>$parent->ID(), 'name'=>$name);
        $loaded = self::LoadManyMatchingAll($database, $criteria);
        
        if (!count($loaded)) return null; else $loaded = array_values($loaded)[0];
        
        $owner = $loaded->GetObjectID('owner');
        return ($owner === null || $owner === $account->ID()) ? $loaded : null;
    }
    
    public static function LoadRootByAccount(ObjectDatabase $database, Account $account, ?Filesystem $filesystem = null) : self
    {
        $filesystem ??= Filesystem::LoadDefaultByAccount($database, $account);
        $criteria = array('owner'=>$account->ID(), 'filesystem'=>$filesystem->ID(), 'parent'=>null);
        $loaded = self::LoadManyMatchingAll($database, $criteria);
        
        if (!count($loaded))
        {
            $criteria['owner'] = null;
            $loaded = self::LoadManyMatchingAll($database, $criteria);
        }

        if (count($loaded)) return array_values($loaded)[0];
        else
        {
            $owner = $filesystem->isShared() ? $filesystem->GetOwner() : $account;
            return self::CreateRoot($database, $filesystem, $owner);
        }
    }
    
    public static function DeleteByParent(ObjectDatabase $database, Parent $parent) : void
    {
        parent::DeleteManyMatchingAll($database, array('parent'=>$parent->ID()));
    }
    
    private bool $notifyDeleted = false; public function isNotifyDeleted() : bool { return $this->notifyDeleted; }
    
    public function DeleteChildren(bool $isNotify = true) : void
    {
        $this->Refresh(true);
        $this->notifyDeleted = $isNotify;
        File::DeleteByParent($this->database, $this);
        Folder::DeleteByParent($this->database, $this);
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
            $this->GetFilesystemImpl()->DeleteFolder($this);
        
        parent::Delete();
    }
    
    const ONLYSELF = 1; const WITHCONTENT = 2; const RECURSIVE = 3;
    
    public function GetClientObject(int $level = self::ONLYSELF) : ?array
    {
        $this->Refresh(false); 
        if ($this->isDeleted()) return null;

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
            $sublevel = ($level === self::RECURSIVE) ? self::RECURSIVE : self::ONLYSELF;
            $data['folders'] = array_map(function($folder)use($sublevel){ return $folder->GetClientObject($sublevel); }, $this->GetFolders());
            $data['files'] = array_map(function($file){ return $file->GetClientObject(); }, $this->GetFiles());            
        }        

        return $data;
    }
}
