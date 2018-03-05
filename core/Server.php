<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\Emailer;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class EmailDisabledException extends Exceptions\ServerException    { public $message = "EMAIL_DISABLED"; }

class Server extends SingletonObject
{
    public function isEnabled() : bool { return $this->TryGetFeature('enabled') ?? true; }
    public function isReadOnly() : bool { return $this->TryGetFeature('read_only') ?? false; }
    public function GetApps() : array { return $this->GetScalar('apps'); }
    public function GetDataDir() : ?string { return $this->TryGetScalar('datadir'); }
    
    public function GetDebugLog2File() : bool { return $this->TryGetFeature('debug_file') ?? false; }
    public function GetDebugLogLevel() : int { return $this->TryGetFeature('debug_log') ?? 1; }
    public function GetDebugOverHTTP() : bool { return $this->TryGetFeature('debug_http') ?? false; }
    
    const EMAIL_MODE_OFF = 0; const EMAIL_MODE_PHP = 1; const EMAIL_MODE_LIB = 2;
    
    public function SendMail(string $recipient, string $subject, string $message)
    {        
        $feature = $this->TryGetFeature("email") ?? 0;
        if ($feature <= self::EMAIL_MODE_OFF) throw new EmailDisabledException();
        
        if ($feature == self::EMAIL_MODE_PHP)
        {
            Emailer::PHPMail($recipient, $subject, $message);
        }
        
        else if ($feature == self::EMAIL_MODE_LIB)
        {
            $emailers = $this->GetObjectRefs('emailers');
            if (count($emailers) == 0) throw new EmailDisabledException();            
            $emailers[array_rand($emailers)]->SendMail($recipient, $subject, $message);
        }
        
        else throw new EmailDisabledException();
    }
}
