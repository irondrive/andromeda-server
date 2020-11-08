<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php");
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class EmailUnavailableException extends Exceptions\ServerException    { public $message = "EMAIL_UNAVAILABLE"; }

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'datadir' => null,
            'features__debug_log' => null,
            'features__debug_http' => null,
            'features__debug_file' => null,
            'features__read_only' => null,
            'features__enabled' => null,
            'features__email' => null,
            'apps' => new FieldTypes\JSON()
        ));
    }
    
    public function isEnabled() : bool { return $this->TryGetFeature('enabled') ?? true; }
    
    const RUN_NORMAL = 0; const RUN_READONLY = 1; const RUN_DRYRUN = 2;
    public function isReadOnly() : int { return $this->TryGetFeature('read_only') ?? self::RUN_NORMAL; }
    public function SetReadOnly(int $data, bool $temp = true) : self { return $this->SetFeature('read_only', $data, $temp); }
    
    public function GetApps() : array { return $this->GetScalar('apps'); }
    public function GetDataDir() : ?string { $dir = $this->TryGetScalar('datadir'); if ($dir) $dir .= '/'; return $dir; }
    
    const LOG_NONE = 0; const LOG_BASIC = 1; const LOG_EXTENDED = 2; const LOG_SENSITIVE = 3;    
    public function GetDebugLogLevel() : int { return $this->TryGetFeature('debug_log') ?? self::LOG_BASIC; }
    public function SetDebugLogLevel(int $data, bool $temp = true) : self { return $this->SetFeature('debug_log', $data, $temp); }
    
    public function GetDebugLog2File() : bool { return $this->TryGetFeature('debug_file') ?? false; }
    public function GetDebugOverHTTP() : bool { return $this->TryGetFeature('debug_http') ?? false; }        
    
    const EMAIL_MODE_OFF = 0; const EMAIL_MODE_PHP = 1; const EMAIL_MODE_LIB = 2;
    
    public function GetMailer() : Emailer
    {
        $feature = $this->TryGetFeature("email") ?? self::EMAIL_MODE_OFF;
        
        if ($feature === self::EMAIL_MODE_OFF) throw new EmailUnavailableException();
        if ($feature === self::EMAIL_MODE_PHP) return new BasicEmailer();
        
        else if ($feature === self::EMAIL_MODE_LIB)
        {
            $emailers = array_values(FullEmailer::LoadAll($this->database));
            if (count($emailers) == 0) throw new EmailUnavailableException();
            return $emailers[array_rand($emailers)];
        }
        else throw new EmailUnavailableException();
    }
}
