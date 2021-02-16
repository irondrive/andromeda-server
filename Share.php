<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/AuthObject.php"); use Andromeda\Apps\Accounts\AuthObject;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\AuthEntity;

/** Exception indicating that the requested share has expired */
class ShareExpiredException extends Exceptions\ClientDeniedException { public $message = "SHARE_EXPIRED"; }

/**
 * A share granting access to an item
 *
 * Share targets can be via links with auth keys, with users,
 * or with groups of users.  They optionally can have extra
 * passwords, and expire based on certain criteria.  Shares also
 * track specific permissions for the access (see functions).
 * Folder shares also share all content under them.
 */
class Share extends AuthObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'shares'), // item being shared
            'owner' => new FieldTypes\ObjectRef(Account::class),    // the account that made the share
            'dest' => new FieldTypes\ObjectPoly(AuthEntity::class), // the group or account target of the share
            'password' => null, // possible password set on the share
            'dates__accessed' => new FieldTypes\Scalar(null, true),
            'counters__accessed' => new FieldTypes\Counter(), // the count of accesses
            'counters_limits__accessed' => null,    // the maximum number of accesses
            'dates__expires' => null,   // the timestamp past which the share is not valid
            'features__read' => new FieldTypes\Scalar(true),
            'features__upload' => new FieldTypes\Scalar(false),
            'features__modify' => new FieldTypes\Scalar(false),
            'features__social' => new FieldTypes\Scalar(true),
            'features__reshare' => new FieldTypes\Scalar(false)
        ));
    }
    
    /** Returns true if this share is via a link rather than to an account/group */
    public function IsLink() : bool { return boolval($this->TryGetScalar('authkey')); }
    
    /** Returns the item being shared */
    public function GetItem() : Item { return $this->GetObject('item'); }
    
    /** Returns the account that created the share */
    public function GetOwner() : Account { return $this->GetObject('owner'); }
    
    /** Returns true if the share grants read access to the item */
    public function CanRead() : bool { return $this->GetFeature('read'); }
    
    /** Returns true if the share grants upload (create new files) to the item */
    public function CanUpload() : bool { return $this->GetFeature('upload'); }
    
    /** Returns true if the share grants write access to the item */
    public function CanModify() : bool { return $this->GetFeature('modify'); }
    
    /** Returns true if the share allows social features (comments, likes) on the item */
    public function CanSocial() : bool { return $this->GetFeature('social'); }
    
    /** Returns true if the share allows the target to re-share the item */
    public function CanReshare() : bool { return $this->GetFeature('reshare'); }

    /** Returns true if the share is expired, either by access count or expiry time  */
    public function IsExpired() : bool
    {
        $expires = $this->TryGetDate('expires');
        if ($expires !== null && Main::GetInstance()->GetTime() > $expires) return true;
        
        return $this->IsCounterOverLimit('accessed', 1);
    }
    
    /** Sets the share's access date to now and increments the access counter */
    public function SetAccessed() : self 
    {
        if ($this->IsExpired()) throw new ShareExpiredException();
        return $this->SetDate('accessed')->DeltaCounter('accessed');
    }
    
    /** Returns a query string that matches shares for the given item to the given target */
    private static function GetItemDestQuery(Item $item, ?AuthEntity $dest, QueryBuilder $q) : string
    {
        return $q->And($q->IsNull('authkey'),
            $q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),
            $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($dest)));  
    }
    
    /**
     * Creates a new share to a share target
     * @param ObjectDatabase $database database reference
     * @param Account $owner the owner of the share
     * @param Item $item the item being shared
     * @param AuthEntity $dest account or group target, or null for everyone
     * @return self new share object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, ?AuthEntity $dest) : self
    {
        $q = new QueryBuilder(); if (($ex = static::TryLoadUniqueByQuery($database, $q->Where(static::GetItemDestQuery($item, $dest, $q)))) !== null) return $ex;    
        
        return parent::BaseCreate($database,false)->SetObject('owner',$owner)->SetObject('item',$item->CountShare())->SetObject('dest',$dest);
    }

    /**
     * Returns a new link-based share object
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner of the share
     * @param Item $item item being shared
     * @return self new share object
     */
    public static function CreateLink(ObjectDatabase $database, Account $owner, Item $item) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item->CountShare());
    }
    
    /** Deletes the share */
    public function Delete() : void
    {
        $this->GetItem()->CountShare(false);
        
        parent::Delete();
    }
    
    /** Returns true if this share requires a password to access */
    public function NeedsPassword() : bool { return boolval($this->TryGetScalar('password')); }
    
    /** Returns true if the given password matches this share */
    public function CheckPassword(string $password) : bool
    {
        $hash = $this->GetScalar('password');        
        $correct = password_verify($password, $hash);
        if ($correct) $this->SetPassword($password, true);
        return $correct;
    }
    
    /**
     * Sets the given password for this share
     * @param string $password the password to set
     * @param bool $check if true, only set the password if a rehash is required
     * @return $this
     */
    protected function SetPassword(string $password, bool $check = false) : self
    {
        $algo = Utilities::GetHashAlgo();        
        if (!$check || password_needs_rehash($this->GetScalar('password'), $algo))
            $this->SetScalar('password', password_hash($password, $algo));        
        return $this;
    }
    
    /** Returns the command usage for SetShareOptions() */
    public static function GetSetShareOptionsUsage() : string { return "[--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--spassword raw] [--expires int] [--maxaccess int]"; }
    
    /**
     * Modifies share permissions and properties from the given input
     * @param Share $access if not null, the share object granting this request access
     * @return $this
     */
    public function SetShareOptions(Input $input, ?Share $access = null) : self
    {
        $f_read =    $input->TryGetParam('read',SafeParam::TYPE_BOOL);
        $f_upload =  $input->TryGetParam('upload',SafeParam::TYPE_BOOL);
        $f_modify =  $input->TryGetParam('modify',SafeParam::TYPE_BOOL);
        $f_social =  $input->TryGetParam('social',SafeParam::TYPE_BOOL);
        $f_reshare = $input->TryGetParam('reshare',SafeParam::TYPE_BOOL);
        
        if ($f_read !== null)    $this->SetFeature('read', $f_read && ($access === null || $access->CanRead()));
        if ($f_upload !== null)  $this->SetFeature('upload', $f_upload && ($access === null || $access->CanUpload()));
        if ($f_modify !== null)  $this->SetFeature('modify', $f_modify && ($access === null || $access->CanModify()));
        if ($f_social !== null)  $this->SetFeature('social', $f_social && ($access === null || $access->CanSocial()));
        if ($f_reshare !== null) $this->SetFeature('reshare', $f_reshare && ($access === null || $access->CanReshare()));
        
        $password = $input->TryGetParam('spassword',SafeParam::TYPE_RAW);
        if ($password !== null) $this->SetPassword($password);
        
        $expires = $input->TryGetParam('expires',SafeParam::TYPE_INT);
        if ($expires !== null) $this->SetDate('expires', $expires);
        
        $maxaccess = $input->TryGetParam('maxaccess',SafeParam::TYPE_INT);
        if ($maxaccess !== null) $this->SetCounterLimit('accesses',$maxaccess);
        
        return $this;
    }
    
    /**
     * Returns a query matching shares that match any of the given dests
     * 
     * Also returns shares with no dest listed (a share to everyone)
     * @param string[] dests share destination DB values
     * @param QueryBuilder $q querybuilder to reference
     * @return string built SQL query
     */
    private static function GetDestsQuery(array $dests, QueryBuilder $q) : string
    {
        return $q->Or($q->OrArr(array_values($dests)),$q->And($q->IsNull('authkey'),$q->IsNull('dest'))); // TODO can we do this by join...?
    }
    
    /**
     * Returns all shares owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account owner
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountOwner(ObjectDatabase $database, Account $account) : array // TODO limit/offset
    {
        return static::LoadByObject($database, 'owner', $account);
    }
    
    /**
     * Returns all shares targeted at the given account or any of its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account share target
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountDest(ObjectDatabase $database, Account $account) : array // TODO limit/offset
    {        
        $dests = array_merge(array($account), $account->GetGroups());
        $q = new QueryBuilder(); $dests = array_map(function($dest)use($q){ 
            return $q->Equals('dest', FieldTypes\ObjectPoly::GetObjectDBValue($dest)); },$dests);   // TODO do by join...?
        
        return static::LoadByQuery($database, $q->Where(static::GetDestsQuery($dests,$q))); 
        
        // TODO what about duplicates? also integrate with group priority
    }
    
    /**
     * Returns a share with the given ID and "owned" by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account must be either the owner of the share or the owner of the shared item
     * @param string $id the ID of the share to fetch
     * @param bool $allowDest if true, the account can be the target of the share also
     * @return self|NULL
     */
    public static function TryLoadByOwnerAndID(ObjectDatabase $database, Account $account, string $id, bool $allowDest = false) : ?self
    {
        $found = static::TryLoadByID($database, $id); if (!$found) return null;
        
        $ok1 = $found->GetOwner() === $account;
        $ok2 = $found->GetItem()->GetOwner() === $account;
        $ok3 = $allowDest && $found->TryGetObject('dest') === $account;
        
        return ($ok1 || $ok2 || $ok3) ? $found : null;
    }
    
    /**
     * Primary share authentication routine for an account
     * 
     * Allowed if the given account (or any of its groups) is the 
     * target of a share for the item, or any of its parents
     * @param ObjectDatabase $database database reference
     * @param Item $item item that is shared
     * @param Account $account account requesting access
     * @return self|NULL share object if allowed, null if not
     */
    public static function TryAuthenticate(ObjectDatabase $database, Item $item, Account $account) : ?self
    {
        do {
            $dests = array_merge(array($account), $account->GetGroups());
            $q = new QueryBuilder(); $dests = array_map(function($dest)use($q,$item){ 
                return static::GetItemDestQuery($item, $dest, $q); },$dests);
            
            $found = static::TryLoadUniqueByQuery($database, $q->Where(static::GetDestsQuery($dests,$q)));  // TODO integrate with group priority if > 1 found
            if ($found) return $found;
        }
        while (($item = $item->GetParent()) !== null);
        return null;
    }
    
    /**
     * Primary authentication routine for link-based shares
     * 
     * If item is not null, checks whether the given item is allowed by this share.  
     * The item or any of its parents must be the item shared.
     * @param ObjectDatabase $database database reference
     * @param string $id the ID of the share in the link
     * @param string $key the key for the share in the link
     * @param Item $item if not null, authenticate against this item
     * @return self|NULL share object if allowed, null if not
     */
    public static function TryAuthenticateByLink(ObjectDatabase $database, string $id, string $key, ?Item $item = null) : ?self
    {
        $share = static::TryLoadByID($database, $id);
        if (!$share || !$share->CheckKeyMatch($key)) return null;        
        if ($item === null) return $share;
        
        do { if ($item === $share->GetItem()) return $share; }
        while (($item = $item->GetParent()) !== null);
        return null;
    }

    /**
     * Returns a printable client object of this share
     * @param bool $item if true, show the item client object
     * @return array `{id:id, owner:string, item:Item|id, itemtype:string, islink:bool, password:bool, dest:?id, desttype:?string,
        expired:bool, dates:{created:float, accessed:?float, expires:?float}, counters:{accessed:int}, limits:{accessed:?int},
        features:{read:bool, upload:bool, modify:bool, social:bool, reshare:bool}}`
     * @see Item::SubGetClientObject()
     * @see AuthObject::GetClientObject()
     */
    public function GetClientObject(bool $item = false, bool $secret = false) : array
    {
        return array_merge(parent::GetClientObject($secret),array(
            'id' => $this->ID(),
            'owner' => $this->GetOwner()->GetDisplayName(),
            'item' => $item ? $this->GetItem()->GetClientObject() : $this->GetObjectID('item'),
            'itemtype' => Utilities::ShortClassName($this->GetObjectType('item')),
            'islink' => $this->IsLink(),
            'password' => $this->NeedsPassword(),
            'dest' => $this->TryGetObjectID('dest'),
            'desttype' => Utilities::ShortClassName($this->TryGetObjectType('dest')),
            'expired' => $this->IsExpired(),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
            'features' => $this->GetAllFeatures()
        ));
    }
}
