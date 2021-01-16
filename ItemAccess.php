<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

use Andromeda\Apps\Accounts\{Account, Authenticator, AuthenticationFailedException};

class InvalidSharePasswordException extends Exceptions\ClientDeniedException { public $message = "INVALID_SHARE_PASSWORD"; }

class ItemAccess
{
    private function __construct(Item $item, ?Share $share){ 
        $this->item = $item; $this->share = $share; }
    
    public function GetItem() : Item { return $this->item; }
    public function GetShare() : ?Share { return $this->share; }
    
    public static function UnknownItemException(?string $class)
    {
        switch ($class)
        {
            case File::class: throw new UnknownFileException();
            case Folder::class: throw new UnknownFolderException();
            default: throw new UnknownItemException();
        }
    }
    
    public static function ItemDeniedException(?string $class)
    {
        switch ($class)
        {
            case File::class: throw new FileAccessDeniedException();
            case Folder::class: throw new FolderAccessDeniedException();
            default: throw new ItemAccessDeniedException();
        }
    }
    
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

            // first check if we are the owner of the item (simple case)
            if ($item->GetOwner() !== $account) 
            {                
                // second, check if there is a share in the chain that gives us access
                $share = Share::TryAuthenticate($database, $item, $account);
                if ($share === null)
                {
                    // third, check if we are elsewhere in the owner chain
                    if (!static::AccountInChain($item, $account))
                        return static::ItemDeniedException($class);
                }
            }
            else $share = null;
        }
        else return static::UnknownItemException($class);
        
        if ($share) $share->SetAccessed();
        
        if ($item && $class && !is_a($item, $class)) 
            return static::UnknownItemException($class);

        return new self($item, $share);
    }
    
    public static function AccountInChain(Item $item, Account $account) : bool
    {
        $haveOwner = false; $amOwner = false;
        do {
            $iowner = $item->GetOwnerID();
            if ($iowner !== null) $haveOwner = true;
            if ($iowner === $account->ID()) $amOwner = true;
        }
        while (($item = $item->GetParent()) !== null && !$amOwner);
        return (!$haveOwner || $amOwner);
    }
    
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?string $class = null, ?string $itemid = null) : ?self
    {
        try { static::Authenticate($database, $input, $authenticator, $class, $itemid); }
        catch (Exceptions\ClientException $e) { return null; }
    }
}
