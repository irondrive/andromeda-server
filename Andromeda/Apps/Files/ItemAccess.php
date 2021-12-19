<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

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
    private Item $item;
    private ?Share $share;
    
    private function __construct(Item $item, ?Share $share){ 
        $this->item = $item; $this->share = $share; }
    
    /** Returns the item that is being accessed */
    public function GetItem() : Item { return $this->item; }
    
    /** Returns the item that is being accessed (if applicable) */
    public function GetFile() : File { return $this->item; }
    
    /** Returns the item that is being accessed (if applicable) */
    public function GetFolder() : Folder { return $this->item; }
    
    /** Returns the share object that grants access, or null if the item is owned */
    public function GetShare() : ?Share { return $this->share; }

    /**
     * Primary authentication routine for granting access to an item
     * 
     * First option is the item is given and the account owns either the item or one of its parents.
     * Second option is a share ID is given. Either the account must have access via a share, or 
     * a share key must be provided.  The shared object will be used if one is not given.
     * @see ItemAccess::OwnerInChain() possible method of access
     * @see Share::Authenticate() access via account
     * @see Share::AuthenticateByLink() access via link
     * @param ObjectDatabase $database database reference
     * @param Input $input user input possibly containing share info
     * @param Authenticator $authenticator current account auth
     * @param ?Item $item the item being requested access to (or null if implicit via the share)
     * @throws InvalidSharePasswordException if the input share password is invalid
     * @throws AuthenticationFailedException if a specific item is requested and auth is null
     * @return self new ItemAccess object
     */
    public static function Authenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?Item $item = null) : self
    {
        if (($shareid = $input->GetOptParam('sid',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER)) !== null)
        {
            $share = Share::TryLoadByID($database, $shareid);
            if ($share === null) throw new UnknownItemException();
            
            $item ??= $share->GetItem();
            
            if ($input->HasParam('skey'))
            {
                $sharekey = $input->GetParam('skey',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
                
                if (!$share->AuthenticateByLink($sharekey, $item)) 
                    throw new ItemAccessDeniedException();            
            }
            else 
            {
                if ($authenticator === null) throw new AuthenticationFailedException();
                $account = $authenticator->GetAccount();
                
                if (!$share->Authenticate($account, $item))
                    throw new ItemAccessDeniedException();
            }

            if ($share->NeedsPassword() && !$share->CheckPassword($input->GetParam(
                    'spassword',SafeParam::TYPE_RAW,SafeParams::PARAMLOG_NEVER)))
                throw new InvalidSharePasswordException();
        }
        else if ($item !== null)
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();
            
            if ($item->GetOwner() !== $account && !static::ItemOwnerAccess($item, $account))
            {
                throw new ItemAccessDeniedException();
            }
            else $share = null; // owner-access
        }
        else throw new UnknownItemException();       
        
        if ($share) $share->SetAccessed();

        return new self($item, $share);
    }
    
    /**
     * Returns whether the given account can access the given item without a share.
     * 
     * The account must own either the item or one of its parents
     * @param Item $item item to access
     * @param Account $account account accessing
     * @return bool true if access is allowed
     */
    protected static function ItemOwnerAccess(Item $item, Account $account) : bool
    {
        if ($item->isWorldAccess()) return true;
        
        do {
            if ($item->GetOwnerID() === $account->ID()) return true;
        }
        while (($item = $item->GetParent()) !== null); return false;
    }
    
    /**
     * Same as ItemAccess::Authenticate() but returns null rather than client exceptions
     * @see ItemAccess::Authenticate()
     */
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?Item $item = null) : ?self
    {
        try { return static::Authenticate($database, $input, $authenticator, $item); }
        catch (Exceptions\ClientException $e) { return null; }
    }
}
