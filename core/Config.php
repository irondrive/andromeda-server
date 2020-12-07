<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php");
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php");

class EmailUnavailableException extends Exceptions\ClientErrorException { public $message = "EMAIL_UNAVAILABLE"; }
class UnwriteableDatadirException extends Exceptions\ClientErrorException { public $message = "DATADIR_NOT_WRITEABLE"; }

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
            'features__email' => null, // TODO where is this used??? add to client object
            'apps' => new FieldTypes\JSON()
        ));
    }
    
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database); }
    
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('datadir')) 
        {
            $datadir = $input->TryGetParam('datadir',SafeParam::TYPE_TEXT);
            if (!is_readable($datadir) || !is_writeable($datadir)) throw new UnwriteableDatadirException();
            $this->SetScalar('datadir', $datadir);
        }
        
        if ($input->HasParam('debug_log')) $this->SetFeature('debug_log',$input->TryGetParam('debug_log',SafeParam::TYPE_INT));
        if ($input->HasParam('debug_http')) $this->SetFeature('debug_http',$input->TryGetParam('debug_http',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_file')) $this->SetFeature('debug_file',$input->TryGetParam('debug_file',SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('read_only')) $this->SetFeature('read_only',$input->TryGetParam('read_only',SafeParam::TYPE_INT));
        if ($input->HasParam('enabled')) $this->SetFeature('enabled',$input->TryGetParam('enabled',SafeParam::TYPE_BOOL));
        
        return $this;
    }
    
    public function GetApps() : array { return $this->GetScalar('apps'); }
    private function TryGetApps() : ?array { return $this->TryGetScalar('apps'); }
    
    public function enableApp(string $app) : self
    {
        $apps = $this->TryGetScalar('apps') ?? array();
        if (!in_array($app, $apps)) array_push($apps, $app);
        return $this->SetScalar('apps', $apps);
    }
    
    public function disableApp(string $app) : self
    {
        $apps = $this->GetScalar('apps');
        if (($key = array_search($app, $apps)) !== false) unset($apps[$key]);
        return $this->SetScalar('apps', array_values($apps));
    }
    
    public function isEnabled() : bool { return $this->TryGetFeature('enabled') ?? true; }
    public function setEnabled(bool $enable) : self { return $this->SetFeature('enabled',$enable); }
    
    const RUN_NORMAL = 0; const RUN_READONLY = 1; const RUN_DRYRUN = 2;
    public function isReadOnly() : int { return $this->TryGetFeature('read_only') ?? self::RUN_NORMAL; }
    public function overrideReadOnly(int $data) : self { return $this->SetFeature('read_only', $data, true); }
    
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
    
    public function GetClientObject(bool $admin) : array
    { 
        $data = array(
            'features' => array(
                'read_only' => $this->isReadOnly(),
                'enabled' => $this->isEnabled(),
                'debug_http' => $this->GetDebugOverHTTP()
            )
        );
        
        $data['apps'] = array_map(function($app){ return $app::getVersion(); }, 
            Main::GetInstance()->GetApps());
                
        if ($admin)
        {
            $data['datadir'] = $this->GetDataDir();
            $data['features']['debug_log'] = $this->GetDebugLogLevel();
            $data['features']['debug_file'] = $this->GetDebugLog2File();
        }
        
        return $data;
    }
}
