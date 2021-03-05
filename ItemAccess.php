<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Apps\Accounts\{Account, Authenticator, AuthenticationFailedException};

/** Exception indicating that the given share password is invalid */
class InvalidSharePasswordException extends Exceptions\ClientDeniedException { public $message = "INVALID_SHARE_PASSWORD"; }

/** 
 * Authenticator class that implements item access rules 
 * 
 * Andromeda's access model goes as follows -
 * 1) if you own an item (created it), you can access it and anything under it
 * 2) users and groups can be granted access to an item (and its contents) via a Share
 * 2b) Shares can control granular permissions like read/write/reshare, etc.
 */
class ItemAccess
{
    private function __construct(Item $item, ?Share $share){ 
        $this->item = $item; $this->share = $share; }
    
    /** Returns the item that is being accessed */
    public function GetItem() : Item { return $this->item; }
    
    /** Returns the share object that grants access, or null if the item is owned */
    public function GetShare() : ?Share { return $this->share; }
    
    /** Throws an unknown item exception for the given item class */
    protected static function UnknownItemException(?string $class)
    {
        switch ($class)
        {
            case File::class: throw new UnknownFileException();
            case Folder::class: throw new UnknownFolderException();
            default: throw new UnknownItemException();
        }
    }
    
    /** Throws an item access denied exception for the given item class */
    protected static function ItemDeniedException(?string $class)
    {
        switch ($class)
        {
            case File::class: throw new FileAccessDeniedException();
            case Folder::class: throw new FolderAccessDeniedException();
            default: throw new ItemAccessDeniedException();
        }
    }
    
    /**
     * Primary authentication routine for granting access to an item
     * 
     * First option is a share ID/key are given and authenticates them, which also loads the item.
     * Second option is auth is given and a specific item is requested (class and itemid).
     * The second option has 3 suboptions: direct ownership, OwnerInChain() and Share::TryAuthenticate()
     * @see ItemAccess::OwnerInChain() possible method of access
     * @see Share::TryAuthenticate() possible method of access
     * @param ObjectDatabase $database database reference
     * @param Input $input user input possibly containing share info
     * @param Authenticator $authenticator current account auth
     * @param string $class class of item being accessed if known or ID given
     * @param string $itemid item ID being accessed (null if a share URL is given)
     * @throws InvalidSharePasswordException if the input share password is invalid
     * @throws AuthenticationFailedException if a specific item is requested and auth is null
     * @return self new ItemAccess object
     */
    public static function Authenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?string $class = null, ?string $itemid = null) : self
    {
        $item = null; if ($itemid !== null)
        {
            $item = $class::TryLoadByID($database, $itemid);
            if ($item === null) return static::UnknownItemException($class);
        }

        if (($shareid = $input->TryGetParam('sid',SafeParam::TYPE_RANDSTR)) !== null)
        {
            $sharekey = $input->GetParam('skey',SafeParam::TYPE_RANDSTR);

            $share = Share::TryAuthenticateByLink($database, $shareid, $sharekey, $item);            
            if ($share === null) return static::UnknownItemException($class);

            $item ??= $share->GetItem();
            
            if ($share->NeedsPassword() && !$share->CheckPassword($input->GetParam('spassword',SafeParam::TYPE_RAW)))
                throw new InvalidSharePasswordException();
        }
        else if ($item !== null)
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();

            // first check if we are the owner of the item or a parent (simple case)
            if ($item->GetOwner() !== $account && !static::AccountInChain($item, $account)) 
            {                
                // second, check if there is a share in the chain that gives us access
                $share = Share::TryAuthenticate($database, $item, $account);
                if ($share === null) return static::ItemDeniedException($class);
            }
            else $share = null;
        }
        else return static::UnknownItemException($class);
        
        if ($share) $share->SetAccessed();
        
        if ($item && $class && !is_a($item, $class)) 
            return static::UnknownItemException($class);

        return new self($item, $share);
    }
    
    /**
     * Returns whether the given account can access the given item without a share.
     * 
     * The account must either own the item or one of its parents, 
     * or the item or any of its parents must have no owner (shared FS).
     * @param Item $item item to access
     * @param Account $account account accessing
     * @return bool true if access is allowed
     */
    public static function AccountInChain(Item $item, Account $account) : bool
    {
        do
        {
            $owner = $item->GetOwnerID();
            
            if ($owner === $account->ID() || $owner === null) return true;
        }
        while (($item = $item->GetParent()) !== null); return false;
    }
    
    /**
     * Same as ItemAccess::Authenticate() but returns null rather than client exceptions
     * @see ItemAccess::Authenticate()
     */
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?string $class = null, ?string $itemid = null) : ?self
    {
        try { static::Authenticate($database, $input, $authenticator, $class, $itemid); }
        catch (Exceptions\ClientException $e) { return null; }
    }
}
