<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

abstract class AuthEntity extends StandardObject
{
    public abstract function hasCrypto() : bool;
    public abstract function useCrypto() : bool;
    
    public function GetPublicKey() : string
    {
        if (!$this->hasCrypto()) throw new CryptoUnavailableException();        
        return $this->GetScalar('public_key');
    }
    
    public abstract function GetEmailRecipients() : array;
    public abstract function SendMailTo(Emailer $mailer, string $subject, string $message, ?EmailRecipient $from = null);
}