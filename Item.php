<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Config.php"); use Andromeda\Apps\Files\Config;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/filesystem/FSImpl.php"); use Andromeda\Apps\Files\Filesystem\FSImpl;
require_once(ROOT."/apps/files/limits/Filesystem.php");
require_once(ROOT."/apps/files/limits/Account.php");

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
            'counters__bandwidth' => new FieldTypes\Counter(true),
            'counters__downloads' => new FieldTypes\Counter(),
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
    public function GetOwnerID() : ?string { return $this->TryGetObjectID('owner'); }
    
    public abstract function GetName() : ?string;
    public abstract function GetSize() : int;
    
    public abstract function GetParent() : ?Folder;
    public abstract function GetParentID() : ?string;

    public abstract function SetName(string $name, bool $overwrite = false) : self;
    public abstract function SetParent(Folder $folder, bool $overwrite = false) : self;
    public abstract function CopyToName(?Account $owner, string $name, bool $overwrite = false) : self;
    public abstract function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : self;
    
    protected function CheckName(string $name, bool $overwrite = false) : self
    {
        $olditem = static::TryLoadByParentAndName($this->database, $this->GetParent(), $name);
        if ($olditem !== null) { if ($overwrite && $olditem !== $this) $olditem->Delete(); else throw new DuplicateItemException(); }
        return $this;
    }
    
    protected function CheckParent(Folder $folder, bool $overwrite = false) : self
    {
        if ($folder->GetFilesystemID() !== $this->GetFilesystemID())
            throw new CrossFilesystemException();
        
        $olditem = static::TryLoadByParentAndName($this->database, $folder, $this->GetName());
        if ($olditem !== null) { if ($overwrite && $olditem !== $this) $olditem->Delete(); else throw new DuplicateItemException(); }
        return $this;
    }
    
    public abstract static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : self;
    
    protected function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    protected function GetFilesystemID() : string { return $this->GetObjectID('filesystem'); }
    protected function GetFSImpl() : FSImpl { return $this->GetFilesystem()->GetFSImpl(); }
    
    public function SetAccessed(?int $time = null) : self { return $this->SetDate('accessed', $time); }
    public function SetCreated(?int $time = null) : self  { return $this->SetDate('created', $time); }
    public function SetModified(?int $time = null) : self { return $this->SetDate('modified', $time); }
    
    public function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    public function GetDownloads() : int { return $this->GetCounter('downloads'); }
    
    public function GetLikes() : array { return $this->GetObjectRefs('likes'); }
    public function GetTags() : array { return $this->GetObjectRefs('tags'); }
    public function GetComments() : array { return $this->GetObjectRefs('comments'); }
    public function GetShares() : array { return $this->GetObjectRefs('shares'); }
    
    public function GetNumShares() : int { return $this->CountObjectRefs('shares'); }
    
    protected function CountCreate() : self 
    {
        return $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(); })
                    ->MapToTotalLimits(function(Limits\Total $lim){ $lim->SetUploadDate(); }); 
    }
    
    public function CountDownload(bool $public = true) : self            
    {
        if (!$public) return $this;
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
    
    public function CountLike(int $value, bool $count = true) : self
    { 
        if ($value > 0) $this->DeltaCounter('likes', $count ? 1 : -1);
        else if ($value < 0) $this->DeltaCounter('dislikes', $count ? 1 : -1);
        return $this;
    }    
    
    public function CountShare(bool $count = true) : self
    {
        $parent = $this->GetParent();
        if ($parent !== null) $parent->CountSubShare($count);
        
        return $this->MapToLimits(function(Limits\Base $lim)use($count){ $lim->CountShare($count); });
    }

    protected function MapToLimits(callable $func) : self
    {
        return $this->MapToTotalLimits($func)->MapToTimedLimits($func);
    }
    
    protected function MapToTotalLimits(callable $func) : self
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem()); if ($fslim !== null) $func($fslim);
        if ($this->GetOwnerID()) foreach (Limits\AccountTotal::LoadByAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);
        return $this;
    }
    
    protected function MapToTimedLimits(callable $func) : self
    {
        if (!Config::GetInstance($this->database)->GetAllowTimedStats()) return $this;
        foreach (Limits\FilesystemTimed::LoadAllForFilesystem($this->database, $this->GetFilesystem()) as $lim) $func($lim);        
        if ($this->GetOwnerID()) foreach (Limits\AccountTimed::LoadAllForAccountAll($this->database, $this->GetOwner()) as $lim) $func($lim);        
        return $this;
    }
    
    protected function GetLimitsBool(callable $func, ?Account $account) : bool
    {
        $fslim = Limits\FilesystemTotal::LoadByFilesystem($this->database, $this->GetFilesystem());
        $aclim = Limits\AccountTotal::LoadByAccount($this->database, $account, true);
        return ($fslim === null || $func($fslim) !== false) && $func($aclim);
    }
    
    public function GetAllowPublicModify() : bool {
       return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicModify(); }, null); }
    
    public function GetAllowPublicUpload() : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowPublicUplaod(); }, null); }    
    
    public function GetAllowRandomWrite(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowRandomWrite(); }, $account); }
    
    public function GetAllowItemSharing(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowItemSharing(); }, $account); }
    
    public function GetAllowShareEveryone(Account $account) : bool {
        return $this->GetLimitsBool(function(Limits\Total $lim){ return $lim->GetAllowShareEveryone(); }, $account); }
    
    public function Delete() : void
    {
        $this->DeleteObjectRefs('likes');
        $this->DeleteObjectRefs('tags');
        $this->DeleteObjectRefs('comments');
        $this->DeleteObjectRefs('shares');
        
        $this->MapToLimits(function(Limits\Base $lim){ $lim->CountItem(false); });
        
        parent::Delete();
    }    

    public static function TryLoadByParentAndName(ObjectDatabase $database, Folder $parent, string $name) : ?self
    {
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('parent',$parent->ID()), $q->Equals('name',$name));        
        return parent::TryLoadUniqueByQuery($database, $q->Where($where));
    }
    
    public static function LoadByOwner(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $where = $q->Equals('owner',$account->ID());
        return parent::LoadByQuery($database, $q->Where($where));
    }
    
    const DETAILS_NONE = 0; const DETAILS_PUBLIC = 1; const DETAILS_OWNER = 2; 
    
    public function GetItemClientObject(int $details = self::DETAILS_NONE) : ?array
    {
        if ($this->isDeleted()) return null;
        
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'owner' => $this->GetOwnerID(),
            'parent' => $this->GetParentID()
        );
                
        $mapobj = function($e) { return $e->GetClientObject(); };

        if ($details)
        {
            $comments = $this->GetComments(); // TODO limit/offset for these!
            if ($details < self::DETAILS_OWNER)
                $comments = array_filter($comments, function($c){ return !$c->IsPrivate(); });
                
            $data['likes'] = array_map($mapobj, array_values($this->GetLikes())); // TODO limit/offset for these!
            $data['tags'] = array_map($mapobj, $this->GetTags());
            $data['comments'] = array_map($mapobj, $comments);
            $data['shares'] = array_map($mapobj, $this->GetShares());
        }
        
        return $data;
    }
}
