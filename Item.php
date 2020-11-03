<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/filesystem/FSImpl.php"); use Andromeda\Apps\Files\Filesystem\FSImpl;

class CrossFilesystemException extends Exceptions\ClientErrorException      { public $message = "FILESYSTEM_MISMATCH"; }

abstract class Item extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'dates__created' => null,
            'dates__modified' => null,
            'dates__accessed' => new FieldTypes\Scalar(null, true),         
            'counters__bandwidth' => new FieldTypes\Counter(null, true),
            'counters__downloads' => new FieldTypes\Counter(null, true),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'filesystem' => new FieldTypes\ObjectRef(FSManager::class)
        ));
    }
    
    public abstract function Refresh() : self;
    
    public function isDeleted() : bool { $this->Refresh(); return parent::isDeleted(); }
    
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    public abstract function GetName() : ?string;
    public abstract function GetSize() : int;
    public abstract function GetParent() : ?Folder;
    
    public function SetName(string $name) : self { return $this->SetScalar('name', $name); }
    
    public function SetParent(Folder $folder) : self
    {
        if ($folder->GetFilesystemID() !== $this->GetFilesystemID())
            throw new CrossFilesystemException();
        return $this->SetObject('parent', $folder);
    }
    
    protected function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    protected function GetFilesystemID() : string { return $this->GetObjectID('filesystem'); }
    protected function GetFSImpl() : FSImpl { return $this->GetFilesystem()->GetFSImpl(); }
    
    public function SetAccessed(?int $time = null) : self { $time ??= microtime(true); return $this->SetDate('accessed', $time); }
    public function SetCreated(?int $time = null) : self  { $time ??= microtime(true); return $this->SetDate('created', $time); }
    public function SetModified(?int $time = null) : self { $time ??= microtime(true); return $this->SetDate('modified', $time); }
    
    public function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    public function GetDownloads() : int { return $this->GetCounter('downloads'); }
    
    public function CountDownload() : self            
    { 
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountDownload();
        return $this->DeltaCounter('downloads'); 
    }
    
    public function CountBandwidth(int $bytes) : self 
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountBandwidth($bytes);
        return $this->DeltaCounter('bandwidth', $bytes); 
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
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name),
                         $q->Or($q->Equals('owner',$account->ID()),$q->IsNull('owner')));
        $loaded = parent::LoadByQuery($database, $q->Where($where));
        return count($loaded) ? array_values($loaded)[0] : null;
    }
    
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        parent::DeleteByObject($database, 'owner', $account);
    }
    
    public static function DeleteByFSManager(ObjectDatabase $database, FSManager $filesystem) : void
    {
        parent::DeleteByObject($database, 'filesystem', $filesystem);
    }
}
