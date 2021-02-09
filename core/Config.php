<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php");
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php");

/** Exception indicating that a mailer was requested but none are configured (or it is disabled) */
class EmailUnavailableException extends Exceptions\ClientErrorException { public $message = "EMAIL_UNAVAILABLE"; }

/** Exception indicating that the configured data directory is not valid */
class UnwriteableDatadirException extends Exceptions\ClientErrorException { public $message = "DATADIR_NOT_WRITEABLE"; }

/** The global framework config stored in the database */
class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'datadir' => null,
            'features__debug' => new FieldTypes\Scalar(self::LOG_ERRORS),
            'features__debug_http' => new FieldTypes\Scalar(false),
            'features__debug_dblog' => new FieldTypes\Scalar(true),
            'features__debug_filelog' => new FieldTypes\Scalar(false),
            'features__read_only' => new FieldTypes\Scalar(0),
            'features__enabled' => new FieldTypes\Scalar(true),
            'features__email' => new FieldTypes\Scalar(true),
            'apps' => new FieldTypes\JSON()
        ));
    }
    
    /** Creates a new config singleton with default values */
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database)->SetScalar('apps',array()); }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return "[--datadir text] [--debug int] [--debug_http bool] [--debug_dblog bool] [--debug_filelog bool] [--read_only int] [--enabled bool] [--email bool]"; }
    
    /**
     * Updates config with the parameters in the given input (see CLI usage)
     * @throws UnwriteableDatadirException if given a new datadir that is invalid
     * @return $this
     * @source show source
     */
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('datadir')) 
        {
            $datadir = $input->TryGetParam('datadir',SafeParam::TYPE_FSPATH);
            if (!is_readable($datadir) || !is_writeable($datadir)) throw new UnwriteableDatadirException();
            $this->SetScalar('datadir', $datadir);
        }
        
        if ($input->HasParam('debug')) $this->SetFeature('debug',$input->GetParam('debug',SafeParam::TYPE_INT));
        if ($input->HasParam('debug_http')) $this->SetFeature('debug_http',$input->GetParam('debug_http',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_dblog')) $this->SetFeature('debug_dblog',$input->GetParam('debug_dblog',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_filelog')) $this->SetFeature('debug_filelog',$input->GetParam('debug_filelog',SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('read_only')) $this->SetFeature('read_only',$input->GetParam('read_only',SafeParam::TYPE_INT));
        if ($input->HasParam('enabled')) $this->SetFeature('enabled',$input->GetParam('enabled',SafeParam::TYPE_BOOL));
        if ($input->HasParam('email')) $this->SetFeature('email',$input->GetParam('email',SafeParam::TYPE_BOOL));        
       
        return $this;
    }
    
    /**
     * returns the array of registered apps
     * @return String[]
     */
    public function GetApps() : array { return $this->GetScalar('apps'); }
    
    /** Registers the specified app name */
    public function EnableApp(string $app) : self
    {
        $apps = $this->GetApps();
        if (!in_array($app, $apps)) array_push($apps, $app);
        // TODO try loading app to check validity
        return $this->SetScalar('apps', $apps);
    }
    
    /** Unregisters the specified app name */
    public function DisableApp(string $app) : self
    {
        $apps = $this->GetApps();
        if (($key = array_search($app, $apps)) !== false) unset($apps[$key]);
        return $this->SetScalar('apps', array_values($apps));
    }
    
    /** Returns whether the server is allowed to respond to requests */
    public function isEnabled() : bool { return $this->GetFeature('enabled'); }
    
    /** Set whether the server is allowed to respond to requests */
    public function setEnabled(bool $enable) : self { return $this->SetFeature('enabled',$enable); }
    
    const RUN_READONLY = 1; const RUN_DRYRUN = 2;
    
    /** Returns whether the server is set to read-only (or dry run) */
    public function isReadOnly() : int { return $this->GetFeature('read_only'); }
    
    /** Temporarily overrides the read-only steting in config */
    public function overrideReadOnly(int $data) : self { return $this->SetFeature('read_only', $data, true); }
    
    /** Returns the configured global data directory path */
    public function GetDataDir() : ?string { $dir = $this->TryGetScalar('datadir'); if ($dir) $dir .= '/'; return $dir; }

    const LOG_ERRORS = 1; const LOG_DEVELOPMENT = 2; const LOG_SENSITIVE = 3;
    
    /** Returns the current debug level */
    public function GetDebugLevel() : int { return $this->GetFeature('debug'); }
    
    /**
     * Sets the current debug level
     * @param bool $temp if true, only for this request
     */
    public function SetDebugLevel(int $data, bool $temp = true) : self { return $this->SetFeature('debug', $data, $temp); }
    
    /** Gets whether the server should log errors to the database */
    public function GetDebugLog2DB()   : bool { return $this->GetFeature('debug_dblog'); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetDebugLog2File() : bool { return $this->GetFeature('debug_filelog'); } 
    
    /** Gets whether debug should be allowed over a non-privileged interface */
    public function GetDebugOverHTTP() : bool { return $this->GetFeature('debug_http'); }       
    
    /** Gets whether using configured emailers is currently allowed */
    public function GetEnableEmail() : bool { return $this->GetFeature('email'); }

    /**
     * Retrieves a configured mailer service, picking one randomly 
     * @throws EmailUnavailableException if not configured or not allowed
     */
    public function GetMailer() : Emailer
    {
        if (!$this->GetEnableEmail()) throw new EmailUnavailableException();
        
        $mailers = Emailer::LoadAll($this->database);
        if (count($mailers) == 0) throw new EmailUnavailableException();
        return $mailers[array_rand($mailers)]->Activate();
    }
    
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array `{features: {read_only:bool, enabled:bool}, apps:[{string:[int]}]}` \
         if admin, add: `{datadir:?string, features:{ debug:int, 
         debug_http:bool, debug_dblog:bool, debug_filelog:bool, email:bool }}`
     */
    public function GetClientObject(bool $admin = false) : array
    { 
        $data = array(
            'features' => array(
                'read_only' => $this->isReadOnly(),
                'enabled' => $this->isEnabled()
            )
        );
        
        $data['apps'] = array_map(function($app){ return $app::getVersion(); }, 
            Main::GetInstance()->GetApps());
                
        if ($admin)
        {
            $data['datadir'] = $this->GetDataDir();
            $data['features'] = $this->GetAllFeatures();
        }
        
        return $data;
    }
}
