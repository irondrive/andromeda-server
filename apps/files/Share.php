<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/AuthObject.php"); use Andromeda\Apps\Accounts\AuthObject;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\{AuthEntity, GroupJoin};

/** Exception indicating that the requested share has expired */
class ShareExpiredException extends Exceptions\ClientDeniedException { public $message = "SHARE_EXPIRED"; }

/** Exception indicating that the requested share already exists */
class ShareExistsException extends Exceptions\ClientErrorException { public $message = "SHARE_ALREADY_EXISTS"; }

/** Exception indicating that a share was requested for a public item */
class SharePublicItemException extends Exceptions\ClientErrorException { public $message = "CANNOT_SHARE_PUBLIC_ITEM"; }

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
            'counters_limits__accessed' => null,     // the maximum number of accesses
            'dates__expires' => null,   // the timestamp past which the share is not valid
            'features__read' => new FieldTypes\Scalar(true),
            'features__upload' => new FieldTypes\Scalar(false),
            'features__modify' => new FieldTypes\Scalar(false),
            'features__social' => new FieldTypes\Scalar(true),
            'features__reshare' => new FieldTypes\Scalar(false),
            'features__keepowner' => new FieldTypes\Scalar(true)
        ));
    }
    
    /** Returns true if this share is via a link rather than to an account/group */
    public function IsLink() : bool { return boolval($this->TryGetScalar('authkey')); }
    
    /** Returns the item being shared */
    public function GetItem() : Item { return $this->GetObject('item'); }
    
    /** Returns the ID of the item being shared */
    public function GetItemID() : string { return $this->GetObjectID('item'); }
    
    /** Returns the account that created the share */
    public function GetOwner() : Account { return $this->GetObject('owner'); }
    
    /** Returns the ID of the account that created the share */
    public function GetOwnerID() : string { return $this->GetObjectID('owner'); }
    
    /** Returns the destination user/group of this share */
    public function GetDest() : ?AuthEntity { return $this->TryGetObject('dest'); }
    
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
    
    /** True if the uploader should stay the owner, else the owner of the parent is the owner */
    public function KeepOwner() : bool { return $this->GetFeature('keepowner'); }

    /** Returns true if the share is expired, either by access count or expiry time  */
    public function IsExpired() : bool
    {
        $expires = $this->TryGetDate('expires');
        if ($expires !== null && Main::GetInstance()->GetTime() > $expires) return true;
        
        return !$this->CheckCounter('accessed', 1, false);
    }
    
    /** Sets the share's access date to now and increments the access counter */
    public function SetAccessed() : self 
    {
        if ($this->IsExpired()) throw new ShareExpiredException();
        return $this->SetDate('accessed')->DeltaCounter('accessed');
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
        if ($item->isWorldAccess()) throw new SharePublicItemException();
        
        $q = new QueryBuilder(); $w = $q->And(
            $q->IsNull('authkey'), $q->Equals('owner',$owner->ID()),
            $q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),
            $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($dest)));
            
        if (static::TryLoadUniqueByQuery($database, $q->Where($w)) !== null) throw new ShareExistsException(); 
        
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
    protected function SetPassword(?string $password, bool $check = false) : self
    {
        if ($password === null) return $this->SetScalar('password',null);
        
        $algo = Utilities::GetHashAlgo();        
        if (!$check || password_needs_rehash($this->GetScalar('password'), $algo))
            $this->SetScalar('password', password_hash($password, $algo));
        
        return $this;
    }
    
    /** Returns the command usage for SetShareOptions() */
    public static function GetSetShareOptionsUsage() : string { return "[--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--keepowner bool] ".
                                                                       "[--spassword ?raw] [--expires ?int] [--maxaccess ?int]"; }
    
    /**
     * Modifies share permissions and properties from the given input
     * @param Share $access if not null, the share object granting this request access
     * @return $this
     */
    public function SetShareOptions(Input $input, ?Share $access = null) : self
    {
        $f_read =    $input->GetOptParam('read',SafeParam::TYPE_BOOL);
        $f_upload =  $input->GetOptParam('upload',SafeParam::TYPE_BOOL);
        $f_modify =  $input->GetOptParam('modify',SafeParam::TYPE_BOOL);
        $f_social =  $input->GetOptParam('social',SafeParam::TYPE_BOOL);
        $f_reshare = $input->GetOptParam('reshare',SafeParam::TYPE_BOOL);
        $f_keepown = $input->GetOptParam('keepowner',SafeParam::TYPE_BOOL);
        
        if ($f_read !== null)    $this->SetFeature('read', $f_read && ($access === null || $access->CanRead()));
        if ($f_upload !== null)  $this->SetFeature('upload', $f_upload && ($access === null || $access->CanUpload()));
        if ($f_modify !== null)  $this->SetFeature('modify', $f_modify && ($access === null || $access->CanModify()));
        if ($f_social !== null)  $this->SetFeature('social', $f_social && ($access === null || $access->CanSocial()));
        if ($f_reshare !== null) $this->SetFeature('reshare', $f_reshare && ($access === null || $access->CanReshare()));
        if ($f_keepown !== null) $this->SetFeature('keepowner', $f_keepown && ($access === null || $access->KeepOwner()));
                
        if ($input->HasParam('spassword')) $this->SetPassword($input->GetNullParam('spassword',SafeParam::TYPE_RAW,SafeParams::PARAMLOG_NEVER));
        
        if ($input->HasParam('expires')) $this->SetDate('expires',$input->GetNullParam('expires',SafeParam::TYPE_UINT));
        if ($input->HasParam('maxaccess')) $this->SetCounterLimit('maxaccess',$input->GetNullParam('maxaccess',SafeParam::TYPE_UINT));
        
        return $this;
    }

    /**
     * Returns all shares owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account owner
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountOwner(ObjectDatabase $database, Account $account) : array
    {
        return static::LoadByObject($database, 'owner', $account);
    }
    
    /**
     * Returns all shares targeted at the given account or any of its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account share target
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountDest(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); 
        
        $q->Join($database, GroupJoin::class, 'groups', self::class, 'dest', Group::class);        
        $w = $q->Equals($database->GetClassTableName(GroupJoin::class).'.accounts', $account->ID());

        $shares = static::LoadByQuery($database, $q->Where($w));
        
        $q = new QueryBuilder(); 
        
        $defgroups = $q->OrArr(array_map(function(Group $group)use($q){ 
            return $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($group));
        }, $account->GetDefaultGroups()));

        $w = $q->Or($q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($account)),
                    $defgroups, $q->And($q->IsNull('authkey'),$q->IsNull('dest')));

        return array_merge($shares, static::LoadByQuery($database, $q->Where($w)));
    }

    /**
     * Checks whether this share gives access to the given item to the given account
     * 
     * Access to the item is given if this share is for the item or one of its parents,
     * and the share target is null (everyone), the given account, or a group held by the account
     * @param Account $account account requesting access
     * @param Item $item item requesting access for
     * @return bool true if access is granted
     */
    public function Authenticate(Account $account, Item $item) : bool
    {
        if ($this->IsLink()) return false;
        
        do
        {
            if ($item->ID() === $this->GetItemID())
            {
                $destobj = $this->GetDest();
                
                if ($destobj === null || $destobj === $account) return true;
                
                else if ($destobj instanceof Group && $account->HasGroup($destobj)) return true;
            }
        }
        while (($item = $item->GetParent()) !== null);
        
        return false;
    }
    
    /**
     * Checks whether this share gives access to the given item and checks the key
     * 
     * Access to the item is given if this share is for the item or one of its parents
     * @param string $key key that authenticates the share
     * @param Item $item item being requested access for
     * @return bool true if access is granted
     */
    public function AuthenticateByLink(string $key, Item $item) : bool
    {
        if (!$this->IsLink() || !$this->CheckKeyMatch($key)) return false;
        
        do { if ($item->ID() === $this->GetItemID()) return true; }
        while (($item = $item->GetParent()) !== null);
        
        return false;
    }
    
    /**
     * Returns a printable client object of this share
     * @param bool $item if true, show the item client object
     * @return array `{id:id, owner:id, item:Item|id, itemtype:string, islink:bool, password:bool, dest:?id, desttype:?string,
        expired:bool, dates:{created:float, accessed:?float, expires:?float}, counters:{accessed:int}, limits:{accessed:?int},
        features:{read:bool, upload:bool, modify:bool, social:bool, reshare:bool}}`
     * @see Item::SubGetClientObject()
     * @see AuthObject::GetClientObject()
     */
    public function GetClientObject(bool $item = false, bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'owner' => $this->GetOwnerID(),
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
        );
        
        if ($secret) $data['authkey'] = $this->GetAuthKey();
        
        return $data;
    }
}
