<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Social; if (!defined('Andromeda')) die();

use Andromeda\Core\{Crypto, Utilities};
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\{Account, Group, PolicyBase};
use Andromeda\Apps\Accounts\Crypto\AuthObject;
use Andromeda\Apps\Files\Items\Item;

/**
 * A share granting access to an item
 *
 * Share targets can be via links with auth keys, with users,
 * or with groups of users.  They optionally can have extra
 * passwords, and expire based on certain criteria.  Shares also
 * track specific permissions for the access (see functions).
 * Folder shares also share all content under them.
 * 
 * @phpstan-import-type ScalarArray from Utilities
 * @phpstan-import-type ItemJ from Item
 *     NOTE for item we use ScalarArray because phpstan doesn't like circular definitions
 * @phpstan-type ShareJ array{id:string, owner:string, item:string|ScalarArray, islink:bool, needpass:bool, dest:?string, date_created:float, date_expires:?float, expired:bool, can_read:bool, can_upload:bool, can_modify:bool, can_social:bool, can_reshare:bool, can_keepowner:bool}
 * @phpstan-type OwnerShareJ \Union<ShareJ, array{label:?string, date_accessed:?float, count_accessed:int, limit_accessed:?int, secret?:string}>
 */
class Share extends BaseObject
{
    protected const IDLength = 16;

    use AuthObject, TableTypes\TableNoChildren;
    
    /** 
     * The account that created this share
     * @var FieldTypes\ObjectRefT<Account>
     */
    protected FieldTypes\ObjectRefT $owner;
    /**
     * The item that this share refers to
     * @var FieldTypes\ObjectRefT<Item>
     */
    protected FieldTypes\ObjectRefT $item;
    /**
     * The target account or group of the share (or null if link-based)
     * @var FieldTypes\NullObjectRefT<PolicyBase>
     */
    protected FieldTypes\NullObjectRefT $dest;
    /** The optional label of the share */
    protected FieldTypes\NullStringType $label;
    /** The optional password hash for the share */
    protected FieldTypes\NullStringType $password;
    /** The date this share was created */
    protected FieldTypes\Timestamp $date_created;
    /** The date this share was last accessed */
    protected FieldTypes\NullTimestamp $date_accessed;
    /** The access count for this share */
    protected FieldTypes\Counter $count_accessed;
    /** The limit of the access count */
    protected FieldTypes\NullIntType $limit_accessed;
    /** The optional expiration date of the share */
    protected FieldTypes\NullTimestamp $date_expires;

    /** True if the share allows read access */
    protected FieldTypes\BoolType $can_read;
    /** True if the share allows upload access to the item */
    protected FieldTypes\BoolType $can_upload; // TODO DBREVAMP what is the difference between upload/modify?
    /** True if the share allows modifying the item */
    protected FieldTypes\BoolType $can_modify;
    /** True if the share allows creating new social objects on the item */
    protected FieldTypes\BoolType $can_social;
    /** True if the shrae allows creating new share objects on the item */
    protected FieldTypes\BoolType $can_reshare;
    /** True if uploaders to this folder remain the owner of their items (they become adopted) */
    protected FieldTypes\BoolType $keepowner;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->owner = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'owner');
        $this->item = $fields[] = new FieldTypes\ObjectRefT(Item::class, 'item');
        $this->dest = $fields[] = new FieldTypes\NullObjectRefT(PolicyBase::class, 'dest');
        $this->label = $fields[] = new FieldTypes\NullStringType('label');
        $this->password = $fields[] = new FieldTypes\NullStringType('password');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_accessed = $fields[] = new FieldTypes\NullTimestamp('date_accessed',saveOnRollback:true);
        $this->limit_accessed = $fields[] = new FieldTypes\NullIntType('limit_accessed');
        $this->count_accessed = $fields[] = new FieldTypes\Counter('count_accessed',limit:$this->limit_accessed);
        $this->date_expires = $fields[] = new FieldTypes\NullTimestamp('date_expires');

        $this->can_read = $fields[] = new FieldTypes\BoolType('can_read',       default:true);
        $this->can_upload = $fields[] = new FieldTypes\BoolType('can_upload',   default:false);
        $this->can_modify = $fields[] = new FieldTypes\BoolType('can_modify',   default:false);
        $this->can_social = $fields[] = new FieldTypes\BoolType('can_social',   default:true);
        $this->can_reshare = $fields[] = new FieldTypes\BoolType('can_reshare', default:false);
        $this->keepowner = $fields[] = new FieldTypes\BoolType('keepowner',     default:true);

        $this->RegisterFields($fields, self::class);

        $this->AuthObjectCreateFields();
        parent::CreateFields();
    }

    /** @return positive-int */
    protected static function GetAuthKeyLength() : int { return 16; } // not cryptographic
    
    /** Returns true if this share is via a link rather than to an account/group */
    public function IsLink() : bool { return $this->authkey->TryGetValue() !== null; }
    
    /** Returns the item being shared */
    public function GetItem() : Item { return $this->item->GetObject(); }
    
    /** Returns the ID of the item being shared */
    public function GetItemID() : string { return $this->item->GetObjectID(); }
    
    /** Returns the account that created the share */
    public function GetOwner() : Account { return $this->owner->GetObject(); }
    
    /** Returns the ID of the account that created the share */
    public function GetOwnerID() : string { return $this->owner->GetObjectID(); }
    
    /** Returns the destination user/group of this share */
    public function TryGetDest() : ?PolicyBase { return $this->dest->TryGetObject(); }

    /** Returns the destination user/group ID of this share */
    public function TryGetDestID() : ?string { return $this->dest->TryGetObjectID(); }

    /** Returns true if the share grants read access to the item */
    public function CanRead() : bool { return $this->can_read->GetValue(); }
    
    /** Returns true if the share grants upload (create new files) to the item */
    public function CanUpload() : bool { return $this->can_upload->GetValue(); }
    
    /** Returns true if the share grants write access to the item */
    public function CanModify() : bool { return $this->can_modify->GetValue(); }
    
    /** Returns true if the share allows social features (comments, likes) on the item */
    public function CanSocial() : bool { return $this->can_social->GetValue(); }
    
    /** Returns true if the share allows the target to re-share the item */
    public function CanReshare() : bool { return $this->can_reshare->GetValue(); }
    
    /** True if the uploader should stay the owner, else the owner of the parent is the owner */
    public function KeepOwner() : bool { return $this->keepowner->GetValue(); }

    /** Returns true if the share is expired, either by access count or expiry time  */
    public function isExpired() : bool
    {
        $expires = $this->date_expires->TryGetValue();
        if ($expires !== null && $this->database->GetTime() > $expires)
            return true; // time expired
        
        return !$this->count_accessed->CheckDelta(1,throw:false);
    }
    
    /** Sets the share's access date to now and increments the access counter */
    public function SetAccessed() : void
    {
        if ($this->isExpired())
            throw new Exceptions\ShareExpiredException();
        $this->date_accessed->SetTimeNow();
        $this->count_accessed->DeltaValue();
        // TODO DBREVAMP only delta the access counter when downloading byte 0 of a file (to replace old file pubdownloads)
    }

    // TODO DBREVAMP have a DB check constraint that you must have either dest or authkey

    /**
     * Creates a new share to a share target
     * @param ObjectDatabase $database database reference
     * @param Account $owner the owner of the share
     * @param Item $item the item being shared
     * @param PolicyBase $dest account or group target
     * @return static new share object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, PolicyBase $dest) : static
    {
        if ($item->isWorldAccess())
            throw new Exceptions\SharePublicItemException();
        
        $q = new QueryBuilder(); 
        $q->Where($q->And(
            $q->IsNull('authkey'), 
            $q->Equals('owner',$owner->ID()),
            $q->Equals('item',$item->ID()), 
            $q->Equals('dest',$dest->ID())));
            
        if ($database->TryLoadUniqueByQuery(static::class, $q) !== null)
            throw new Exceptions\ShareExistsException(); 

        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->owner->SetObject($owner);
        $obj->item->SetObject($item);
        $obj->dest->SetObject($dest);
        return $obj;
    }

    /**
     * Returns a new link-based share object
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner of the share
     * @param Item $item item being shared
     * @return static new share object
     */
    public static function CreateLink(ObjectDatabase $database, Account $owner, Item $item) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->owner->SetObject($owner);
        $obj->item->SetObject($item);
        $obj->InitAuthKey();
        return $obj;
    }
    
    /**
     * Load all shares for the given item
     * @return array<string, static>
     */
    public static function LoadByItem(ObjectDatabase $database, Item $item) : array
    {
        return $database->LoadObjectsByKey(static::class, 'item', $item->ID());
    }

    /** Returns true if this share requires a password to access */
    public function NeedsPassword() : bool { return $this->password->TryGetValue() !== null; }
    
    /** Returns true if the given password matches this share */
    public function CheckPassword(string $password) : bool
    {
        $hash = $this->password->TryGetValue();
        if ($hash === null) return false;
        
        $correct = password_verify($password, $hash);
        
        if ($correct)
            $this->SetPassword($password, check:true);
            
        return $correct;
    }
    
    /**
     * Sets the given password for this share
     * @param string $password the password to set
     * @param bool $check if true, only set the password if it's already set and a rehash is required
     */
    protected function SetPassword(?string $password, bool $check = false) : void
    {
        if ($password === null)
            $this->password->SetValue(null);
        else
        {
            $oldpw = $this->password->TryGetValue();
            // TODO move this to AuthObject, make it take a param with the field name (since we use it twice), and support $false=false, use LocalTest.php.old
            if (!$check || $oldpw === null || password_needs_rehash($oldpw, PASSWORD_ARGON2ID))
                $this->password->SetValue(password_hash($password, PASSWORD_ARGON2ID));
        }
    }
    
    /** Returns the command usage for SetOptions() */
    public static function GetSetOptionsUsage() : string { return "[--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--keepowner bool] ".
                                                                  "[--label ?text] [--spassword ?raw] [--expires ?float] [--maxaccess ?uint32]"; }
    
    /**
     * Modifies share permissions and properties from the given input
     * @param Share $access if not null, the share object granting this request access (caps permissions)
     */
    public function SetOptions(SafeParams $params, ?Share $access = null) : void
    {
        if ($params->HasParam('read'))
            $this->can_read->SetValue($params->GetParam('read')->GetBool() && ($access === null || $access->CanRead()));
        // TODO DBREVAMP double check logic here, why allow setting to false? seems weird
    
        if ($params->HasParam('upload'))
            $this->can_upload->SetValue($params->GetParam('upload')->GetBool() && ($access === null || $access->CanUpload()));
        
        if ($params->HasParam('modify'))
            $this->can_modify->SetValue($params->GetParam('modify')->GetBool() && ($access === null || $access->CanModify()));
        
        if ($params->HasParam('social'))
            $this->can_social->SetValue($params->GetParam('social')->GetBool() && ($access === null || $access->CanSocial()));
        
        if ($params->HasParam('reshare'))
            $this->can_reshare->SetValue($params->GetParam('reshare')->GetBool() && ($access === null || $access->CanReshare()));
        
        if ($params->HasParam('keepowner'))
            $this->keepowner->SetValue($params->GetParam('keepowner')->GetBool() && ($access === null || $access->KeepOwner()));

        if ($params->HasParam('label'))
            $this->label->SetValue($params->GetParam('label')->GetNullHTMLText());
        
        if ($params->HasParam('spassword')) 
            $this->SetPassword($params->GetParam('spassword',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        
        if ($params->HasParam('expires')) 
            $this->date_expires->SetValue($params->GetParam('expires')->GetNullFloat());
        
        if ($params->HasParam('maxaccess')) 
            $this->limit_accessed->SetValue($params->GetParam('maxaccess')->GetNullUint32());
    }

    /**
     * Returns all shares owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account owner
     * @return array<string, Share> shares indexed by ID
     */
    public static function LoadByAccountOwner(ObjectDatabase $database, Account $account) : array
    {
        return $database->LoadObjectsByKey(static::class, 'owner', $account->ID());
    }
    
    /**
     * Returns all shares targeted at the given account or any of its groups
     * @param ObjectDatabase $database database reference
     * @param Account $account share target
     * @return array<string, static> shares indexed by ID
     */
    public static function LoadByAccountDest(ObjectDatabase $database, Account $account) : array
    {
        // first load shares targeted at the account's groups
        $q = new QueryBuilder(); 
        
        //$q->Join($database, GroupJoin::class, 'objs_groups', self::class, 'obj_dest', Group::class);        
        //$q->Where($q->Equals($database->GetClassTableName(GroupJoin::class).'.objs_accounts', $account->ID()));
        // TODO DBREVAMP figure out how to re-implement this

        $gshares = $database->LoadObjectsByQuery(static::class, $q);

        // then load shares targeted at this account or its default groups
        $q = new QueryBuilder(); 
        
        $defgroupsq = $q->Or(...array_map(function(Group $group)use($q){ 
            return $q->Equals('dest',$group->ID());
        }, array_values($account->GetDefaultGroups())));

        $q->Where($q->Or($defgroupsq, // default groups
            $q->Equals('dest',$account->ID()), // this account
            $q->And($q->IsNull('authkey'),$q->IsNull('dest')))); // everyone

        return $gshares + $database->LoadObjectsByQuery(static::class, $q);
    }

    /**
     * Delete all likes for the given item
     * @return int
     */
    public static function DeleteByItem(ObjectDatabase $database, Item $item) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'item', $item->ID());
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
                $destobj = $this->TryGetDest();
                if ($destobj === null || $destobj === $account) return true;
                if ($destobj instanceof Group && $account->HasGroup($destobj)) return true;
            }
        }
        while (($item = $item->TryGetParent()) !== null);
        
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
        while (($item = $item->TryGetParent()) !== null);
        
        return false;
    }
    
    /**
     * Returns a printable client object of this share
     * @param bool $fullitem if true, show the item client object
     * @param bool $owner if true, we are showing the owner of the share
     * @return ($owner is true ? OwnerShareJ : ShareJ)
     */
    public function GetClientObject(bool $fullitem, bool $owner, bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'owner' => $this->GetOwnerID(),
            'item' => !$fullitem ? $this->GetItemID() :
                $this->GetItem()->GetClientObject(owner:false,details:false),

            'islink' => $this->IsLink(),
            'needpass' => $this->NeedsPassword(),
            'dest' => $this->TryGetDestID(),
            // TODO FUTURE what can clients do with this info? should we return a basic client object here that includes the type?
            // or should there be a accounts client function that can lookup an account OR group by ID?
            
            // TODO DBREVAMP non-owners shouldn't be able to get info on expired shares. should just not show up. also should we auto-delete them?
            'date_created' => $this->date_created->GetValue(),
            'date_expires' => $this->date_expires->TryGetValue(),
            'expired' => $this->isExpired(),

            'can_read' => $this->CanRead(),
            'can_upload' => $this->CanUpload(),
            'can_modify' => $this->CanModify(),
            'can_social' => $this->CanSocial(),
            'can_reshare' => $this->CanReshare(),
            'can_keepowner' => $this->KeepOwner()
        );
        
        if ($owner)
        {
            $data += array(
                'label' => $this->label->TryGetValue(),
                'date_accessed' => $this->date_accessed->TryGetValue(),
                'count_accessed' => $this->count_accessed->GetValue(),
                'limit_accessed' => $this->limit_accessed->TryGetValue()
            );
        }

        if ($secret) $data['authkey'] = Crypto::base64_encode($this->GetAuthKey());
        
        return $data;
    }
}
