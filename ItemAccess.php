<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

class ItemAccess
{
    private function __construct(Item $item, ?Share $share){ 
        $this->item = $item; $this->share = $share; }
    
    public function GetItem() : Item { return $this->item; }
    public function GetShare() : ?Share { return $this->share; }
    
    public static function TryAuthenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, string $class, ?string $itemid) : ?self
    {        
        $shareid = $input->TryGetParam('share',SafeParam::TYPE_ID);        
        
        if ($shareid !== null)
        {
            $sharekey = $input->GetParam('shkey',SafeParam::TYPE_ALPHANUM);
            
            $share = Share::TryLoadByIDAndKey($database, $shareid, $sharekey);
            if ($share === null) throw new UnknownItemException();
            
            $item = $share->GetItem(); if (!is_a($item, $class)) return null;
        }
        else if ($itemid !== null)
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();
            
            $item = $class::TryLoadByID($database, $itemid);
            if ($item === null) return null;
            
            $iowner = $item->GetOwner();
            if ($iowner !== null && $iowner !== $account)
            {
                $share = Share::TryLoadByItemAndAccount($database, $item, $account);
                if ($share === null) return null;
            }
            else $share = null;
        }
        else return null;
        
        if ($share) $share->SetAccessed();
        
        return new self($item, $share);
    }
    
    public static function Authenticate(ObjectDatabase $database, Input $input, ?Authenticator $authenticator, string $class, ?string $itemid) : self
    {
        $retval = self::TryAuthenticate($database, $input, $authenticator, $class, $itemid);
        if ($retval === null) throw new UnknownItemException(); else return $retval;
    }
}
