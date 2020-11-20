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
class DuplicateItemException extends Exceptions\ClientErrorException        { public $message = "ITEM_ALREADY_EXISTS"; }

abstract class Item extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'dates__modified' => null,
            'dates__accessed' => new FieldTypes\Scalar(null, true),         
            'counters__bandwidth' => new FieldTypes\Counter(null, true),
            'counters__downloads' => new FieldTypes\Counter(null, true),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'filesystem' => new FieldTypes\ObjectRef(FSManager::class),
            'likes' => new FieldTypes\ObjectRefs(Like::class, 'item', true),
            'counters__likes' => new FieldTypes\Counter(),
            'counters__dislikes' => new FieldTypes\Counter(),
            'tags' => new FieldTypes\ObjectRefs(Tag::class, 'item', true),
            'comments' => new FieldTypes\ObjectRefs(Comment::class, 'item', true),
            'shares' => new FieldTypes\ObjectRefs(Share::class, 'item', true)
        ));
    }
    
    public abstract function Refresh() : self;
    
    public function isDeleted() : bool { $this->Refresh(); return parent::isDeleted(); }
    
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    public abstract function GetName() : ?string;
    public abstract function GetSize() : int;
    public abstract function GetParent() : ?Folder;
    
    public function SetName(string $name, bool $overwrite = false) : self 
    {
        $olditem = static::TryLoadByParentAndName($this->database, $this->GetParent(), $this->GetOwner(), $name);
        if ($olditem !== null) { if ($overwrite) $olditem->Delete(); else throw new DuplicateItemException(); }
        
        return $this->SetScalar('name', $name); 
    }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : self
    {
        if ($folder->GetFilesystemID() !== $this->GetFilesystemID())
            throw new CrossFilesystemException();
        
        $olditem = static::TryLoadByParentAndName($this->database, $folder, $this->GetOwner(), $this->GetName());
        if ($olditem !== null) { if ($overwrite) $olditem->Delete(); else throw new DuplicateItemException(); }
        
        return $this->SetObject('parent', $folder);
    }
    
    public abstract static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self;
    
    protected function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    protected function GetFilesystemID() : string { return $this->GetObjectID('filesystem'); }
    protected function GetFSImpl() : FSImpl { return $this->GetFilesystem()->GetFSImpl(); }
    
    public function SetAccessed(?int $time = null) : self { $time ??= microtime(true); return $this->SetDate('accessed', $time); }
    public function SetCreated(?int $time = null) : self  { $time ??= microtime(true); return $this->SetDate('created', $time); }
    public function SetModified(?int $time = null) : self { $time ??= microtime(true); return $this->SetDate('modified', $time); }
    
    public function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    public function GetDownloads() : int { return $this->GetCounter('downloads'); }
    
    public function GetLikes() : array { return $this->GetObjectRefs('likes'); }
    public function GetTags() : array { return $this->GetObjectRefs('tags'); }
    public function GetComments() : array { return $this->GetObjectRefs('comments'); }
    public function GetShares() : array { return $this->GetObjectRefs('shares'); }
    
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
    
    public function CountLike(int $value) : self 
    { 
        if ($value > 0) $this->DeltaCounter('likes', 1);
        else if ($value < 0) $this->DeltaCounter('dislikes', 1);
        return $this;
    }
    
    public function DiscountLike(int $value) : self
    {
        if ($value > 0) $this->DeltaCounter('likes', -1);
        else if ($value < 0) $this->DeltaCounter('dislikes', -1);
        return $this;
    }
    
    public function Delete() : void
    {
        $this->DeleteObjectRefs('likes');
        $this->DeleteObjectRefs('tags');
        $this->DeleteObjectRefs('comments');
        $this->DeleteObjectRefs('shares');
        
        parent::Delete();
    }    
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $loaded = static::TryLoadByID($database, $id);
        if (!$loaded) return null;
        
        $owner = $loaded->GetObjectID('owner');
        return ($owner === null || $owner === $account->ID()) ? $loaded : null;
    }
    
    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, Account $account, string $name) : ?self
    {
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name),
                         $q->Or($q->Equals('owner',$account->ID()),$q->IsNull('owner')));
        
        return parent::LoadOneByQuery($database, $q->Where($where));
    }
    
    public function GetItemClientObject(bool $details = false) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'owner' => $this->GetObjectID('owner'),
            'parent' => $this->GetObjectID('parent'),
        );
                
        $mapobj = function($e) { return $e->GetClientObject(); };

        if ($details)
        {
            $data['likes'] = array_map($mapobj, array_values($this->GetLikes())); // TODO limit/offset for these!
            $data['tags'] = array_map($mapobj, $this->GetTags());
            $data['comments'] = array_map($mapobj, $this->GetComments()); // TODO limit/offset for these!
            $data['shares'] = array_map($mapobj, $this->GetShares());
        }
        
        return $data;
    }
}
