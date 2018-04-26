<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, BasicEmailer, FullEmailer};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class EmailUnavailableException extends Exceptions\ServerException    { public $message = "EMAIL_UNAVAILABLE"; }

class Config extends SingletonObject
{
    public function isEnabled() : bool { return $this->TryGetFeature('enabled') ?? true; }
    public function isReadOnly() : bool { return $this->TryGetFeature('read_only') ?? false; }
    public function GetApps() : array { return $this->GetScalar('apps'); }
    public function GetDataDir() : ?string { return $this->TryGetScalar('datadir'); }
    
    const LOG_NONE = 0; const LOG_BASIC = 1; const LOG_SENSITIVE = 2;
    
    public function GetDebugLogLevel() : int { return $this->TryGetFeature('debug_log') ?? self::LOG_BASIC; }
    public function GetDebugLog2File() : bool { return $this->TryGetFeature('debug_file') ?? false; }
    public function GetDebugOverHTTP() : bool { return $this->TryGetFeature('debug_http') ?? false; }    
    
    const EMAIL_MODE_OFF = 0; const EMAIL_MODE_PHP = 1; const EMAIL_MODE_LIB = 2;
    
    public function GetMailer() : Emailer
    {
        $feature = $this->TryGetFeature("email") ?? 0;
        
        if ($feature == self::EMAIL_MODE_OFF) throw new EmailUnavailableException();
        if ($feature == self::EMAIL_MODE_PHP) return new BasicEmailer();
        
        else if ($feature == self::EMAIL_MODE_LIB)
        {
            $emailers = $this->GetObjectRefs('emailers');
            if (count($emailers) == 0) throw new EmailUnavailableException();
            return $emailers[array_rand($emailers)];
        }
        else throw new EmailUnavailableException();
    }
}
