<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

class InvalidSharePasswordException extends Exceptions\ClientDeniedException { public $message = "INVALID_SHARE_PASSWORD"; }

class ItemAccess
{
    private function __construct(Item $item, ?Share $share){ 
        $this->item = $item; $this->share = $share; }
    
    public function GetItem() : Item { return $this->item; }
    public function GetShare() : ?Share { return $this->share; }
    
    public static function ItemException(?string $class)
    {
        switch ($class)
        {
            case File::class: throw new UnknownFileException();
            case Folder::class: throw new UnknownFolderException();
            default: throw new UnknownItemException();
        }
    }
    
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?string $class, ?string $itemid) : ?self
    {
        $item = null; if ($itemid !== null)
        {
            $item = $class::TryLoadByID($database, $itemid);
            if ($item === null) return null;
        }
        
        if (($shareid = $input->TryGetParam('sid',SafeParam::TYPE_ID)) !== null)
        {
            $sharekey = $input->GetParam('skey',SafeParam::TYPE_ALPHANUM);

            $share = Share::TryAuthenticateByLink($database, $shareid, $sharekey, $item);            
            if ($share === null) static::ItemException($class);

            $item ??= $share->GetItem();
            
            if ($share->NeedsPassword() && !$share->CheckPassword($input->GetParam('spassword',SafeParam::TYPE_RAW)))
                throw new InvalidSharePasswordException();
        }
        else if ($item !== null)
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();

            if ($item->GetOwner() !== $account && !$item->isGlobal())
            {
                $share = Share::TryAuthenticate($database, $item, $account);
                if ($share === null)
                {
                    $ownParent = false;
                    do { if ($item->GetOwnerID() === $account->ID()) $ownParent = true; }
                    while (($item = $item->GetParent()) !== null && !$ownParent);
                    if (!$ownParent) return null;
                }
            }
            else $share = null;
        }
        else return null;
        
        if ($share) $share->SetAccessed();
        
        if ($item && $class && !is_a($item, $class)) static::ItemException($class);

        return new self($item, $share);
    }
    
    public static function Authenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, ?string $class, ?string $itemid) : self
    {
        $retval = self::TryAuthenticate($database, $input, $authenticator, $class, $itemid);
        if ($retval === null) static::ItemException($class); else return $retval;
    }
}
