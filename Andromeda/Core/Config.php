<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Emailer.php");
require_once(ROOT."/Core/BaseApp.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/Exceptions.php");

/** Exception indicating that a mailer was requested but none are configured (or it is disabled) */
class EmailUnavailableException extends Exceptions\ClientErrorException { public $message = "EMAIL_UNAVAILABLE"; }

/** Exception indicating that the configured data directory is not valid */
class UnwriteableDatadirException extends Exceptions\ClientErrorException { public $message = "DATADIR_NOT_WRITEABLE"; }

/** Exception indicating an invalid app name was given */
class InvalidAppException extends Exceptions\ClientErrorException { public $message = "INVALID_APPNAME"; }

/** Exception indicating that an app dependency was not met */
class AppDependencyException extends Exceptions\ClientErrorException { public $message = "APP_DEPENDENCY_FAILURE"; }

/** Exception indicating that the app is not compatible with this framework version */
class AppVersionException extends Exceptions\ClientErrorException { public $message = "APP_VERSION_MISMATCH"; }

/** A singleton object that stores a version field */
abstract class BaseConfig extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array( 'version'=>null ));
    }
    
    /** Returns the database schema version */
    public function getVersion() : string { return $this->GetScalar('version'); }
    
    /** Sets the database schema version to the given value */
    public function setVersion(string $version) : self { return $this->SetScalar('version',$version); }
}

/** The global framework config stored in the database */
class Config extends BaseConfig
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'datadir' => null,
            'features__requestlog_db' => new FieldTypes\Scalar(false),
            'features__requestlog_file' => new FieldTypes\Scalar(false),
            'features__requestlog_details' => new FieldTypes\Scalar(self::RQLOG_DETAILS_BASIC),
            'features__debug' => new FieldTypes\Scalar(self::ERRLOG_ERRORS),
            'features__debug_http' => new FieldTypes\Scalar(false),
            'features__debug_dblog' => new FieldTypes\Scalar(true),
            'features__debug_filelog' => new FieldTypes\Scalar(false),
            'features__metrics' => new FieldTypes\Scalar(0),
            'features__metrics_dblog' => new FieldTypes\Scalar(false),
            'features__metrics_filelog' => new FieldTypes\Scalar(false),
            'features__read_only' => new FieldTypes\Scalar(false),
            'features__enabled' => new FieldTypes\Scalar(true),
            'features__email' => new FieldTypes\Scalar(true),
            'apps' => new FieldTypes\JSON()
        ));
    }
    
    /** Creates a new config singleton with default values */
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database)->SetScalar('apps',array())->setVersion(andromeda_version); }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return "[--requestlog_db bool] [--requestlog_file bool] [--requestlog_details ".implode('|',array_keys(self::RQLOG_DETAILS_TYPES))."] ".
                                                                 "[--debug ".implode('|',array_keys(self::DEBUG_TYPES))."] [--debug_http bool] [--debug_dblog bool] [--debug_filelog bool] ".
                                                                 "[--metrics ".implode('|',array_keys(self::METRICS_TYPES))."] [--metrics_dblog bool] [--metrics_filelog bool] ".
                                                                 "[--read_only bool] [--enabled bool] [--email bool] [--datadir ?text]"; }
    
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
            $datadir = $input->GetNullParam('datadir',SafeParam::TYPE_FSPATH);
            if ($datadir !== null && !is_readable($datadir) || !is_writeable($datadir)) 
                throw new UnwriteableDatadirException();
            $this->SetScalar('datadir', $datadir);
        }
        
        if ($input->HasParam('requestlog_db')) $this->SetFeature('requestlog_db',$input->GetParam('requestlog_db',SafeParam::TYPE_BOOL));
        if ($input->HasParam('requestlog_file')) $this->SetFeature('requestlog_file',$input->GetParam('requestlog_file',SafeParam::TYPE_BOOL));

        if ($input->HasParam('requestlog_details'))
        {
            $param = $input->GetParam('requestlog_details',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
                function($v){ return array_key_exists($v, self::RQLOG_DETAILS_TYPES); });
            
            $this->SetFeature('requestlog_details', self::RQLOG_DETAILS_TYPES[$param]);
        }
        
        if ($input->HasParam('debug'))
        {
            $param = $input->GetParam('debug',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
                function($v){ return array_key_exists($v, self::DEBUG_TYPES); });
            
            $this->SetFeature('debug', self::DEBUG_TYPES[$param]);
        }
        
        if ($input->HasParam('debug_http')) $this->SetFeature('debug_http',$input->GetParam('debug_http',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_dblog')) $this->SetFeature('debug_dblog',$input->GetParam('debug_dblog',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_filelog')) $this->SetFeature('debug_filelog',$input->GetParam('debug_filelog',SafeParam::TYPE_BOOL));

        if ($input->HasParam('metrics'))
        {
            $param = $input->GetParam('metrics',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
                function($v){ return array_key_exists($v, self::METRICS_TYPES); });
            
            $this->SetFeature('metrics', self::METRICS_TYPES[$param]);
        }
        
        if ($input->HasParam('metrics_dblog')) $this->SetFeature('metrics_dblog',$input->GetParam('metrics_dblog',SafeParam::TYPE_BOOL));
        if ($input->HasParam('metrics_filelog')) $this->SetFeature('metrics_filelog',$input->GetParam('metrics_filelog',SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('read_only')) 
        {
            $ro = $input->GetParam('read_only',SafeParam::TYPE_BOOL);
            
            if (!$ro) $this->database->setReadOnly(false); // make DB writable
            
            $this->SetFeature('read_only',$ro);
            
            if ($ro) $this->SetFeature('read_only',false,true); // not really RO yet 
        }
        
        if ($input->HasParam('enabled')) $this->SetFeature('enabled',$input->GetParam('enabled',SafeParam::TYPE_BOOL));
        if ($input->HasParam('email')) $this->SetFeature('email',$input->GetParam('email',SafeParam::TYPE_BOOL));        
       
        return $this;
    }
    
    /**
     * returns the array of registered apps
     * @return String[]
     */
    public function GetApps() : array { return $this->GetScalar('apps'); }
    
    /** List all installable app folders that exist in the filesystem */
    public static function ListApps() : array
    {
        $valid = function(string $app)
        {
            if (in_array($app,array('.','..'))) return false;
            return file_exists(ROOT."/Apps/$app/metadata.json");
        };
            
        $apps = array_values(array_filter(scandir(ROOT."/Apps"), $valid));
        
        return array_map(function(string $s){ return strtolower($s); }, $apps);
    }
    
    /** Registers the specified app name */
    public function EnableApp(string $app) : self
    {
        $app = strtolower($app);
        
        $apps = array_keys(Main::GetInstance()->GetApps()); 

        foreach (BaseApp::getAppRequires($app) as $tapp)
        {
            if (!in_array($tapp, $apps))
                throw new AppDependencyException("$app requires $tapp");
        }
        
        $reqver = BaseApp::getAppReqVersion($app);
        if ($reqver != (new VersionInfo())->major)
            throw new AppVersionException($reqver);
        
        Main::GetInstance()->LoadApp($app);
        
        $capps = $this->GetApps();        
        if (!in_array($app, $capps)) $capps[] = $app;        
        return $this->SetScalar('apps', $capps);
    }
    
    /** Unregisters the specified app name */
    public function DisableApp(string $app) : self
    {
        $app = strtolower($app);
        
        if (($key = array_search($app, $this->GetApps())) === false)
            throw new InvalidAppException();
    
        foreach (array_keys(Main::GetInstance()->GetApps()) as $tapp)
        {
            if (in_array($app, BaseApp::getAppRequires($tapp)))
                throw new AppDependencyException("$tapp requires $app");
        }            
        
        $capps = $this->GetApps(); unset($capps[$key]);
        
        return $this->SetScalar('apps', array_values($capps));
    }
    
    /** Returns whether the server is allowed to respond to requests */
    public function isEnabled() : bool { return $this->GetFeature('enabled'); }
    
    /** Set whether the server is allowed to respond to requests */
    public function setEnabled(bool $enable) : self { return $this->SetFeature('enabled',$enable); }
    
    private bool $dryrun = false;

    /** Returns true if the server is set to dry-run mode */
    public function isDryRun() : bool { return $this->dryrun; }
    
    /** Sets the server to dryrun mode if $val is true */
    public function setDryRun(bool $val = true) : self { $this->dryrun = $val; return $this; }
    
    /** Returns true if the server is set to read-only (not dry run) */
    public function isReadOnly() : bool { return $this->GetFeature('read_only'); }
    
    /** Returns the configured global data directory path */
    public function GetDataDir() : ?string { $dir = $this->TryGetScalar('datadir'); if ($dir) $dir .= '/'; return $dir; }
    
    /** Returns true if request logging to DB is enabled */
    public function GetEnableRequestLogDB() : bool { return $this->GetFeature('requestlog_db'); }
    
    /** Returns true if request logging to data dir file is enabled */
    public function GetEnableRequestLogFile() : bool { return $this->GetFeature('requestlog_file'); }
    
    /** Returns true if request logging is enabled */
    public function GetEnableRequestLog() : bool { return $this->GetEnableRequestLogDB() || $this->GetEnableRequestLogFile(); }
    
    /** log basic details params and object IDs */
    const RQLOG_DETAILS_BASIC = 1;
    
    /** log more detailed info, and full objects when deleted */
    const RQLOG_DETAILS_FULL = 2;
    
    const RQLOG_DETAILS_TYPES = array('none'=>0, 'basic'=>self::RQLOG_DETAILS_BASIC, 'full'=>self::RQLOG_DETAILS_FULL);
    
    /** Returns the configured request log details detail level */
    public function GetRequestLogDetails() : int { return $this->GetFeature('requestlog_details'); }
    
    /** show a basic back trace */ 
    const ERRLOG_ERRORS = 1; 
    
    /** show a full back trace, loaded objects, SQL queries */
    const ERRLOG_DEVELOPMENT = 2;
    
    /** also show input params, function arguments, SQL values */ 
    const ERRLOG_SENSITIVE = 3;
    
    const DEBUG_TYPES = array('none'=>0, 'errors'=>self::ERRLOG_ERRORS, 'development'=>self::ERRLOG_DEVELOPMENT, 'sensitive'=>self::ERRLOG_SENSITIVE);
    
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
    
    /** Show basic performance metrics */
    const METRICS_BASIC = 1;
    
    /** Show extended performance metrics */
    const METRICS_EXTENDED = 2;
    
    const METRICS_TYPES = array('none'=>0, 'basic'=>1, 'extended'=>2);
    
    /** Returns the current metrics log level */
    public function GetMetricsLevel() : int { return $this->GetFeature('metrics'); }
    
    /**
     * Sets the current metrics log level
     * @param bool $temp if true, only for this request
     */
    public function SetMetricsLevel(int $data, bool $temp = true) : self { return $this->SetFeature('metrics', $data, $temp); }
    
    /** Gets whether the server should log metrics to the database */
    public function GetMetricsLog2DB()   : bool { return $this->GetFeature('metrics_dblog'); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetMetricsLog2File() : bool { return $this->GetFeature('metrics_filelog'); } 
    
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
     * @return array `{api:int, features: {read_only:bool, enabled:bool}, apps:[{string:string}]}` \
         if admin, add: `{datadir:?string, features:{ \
            requestlog_file:bool, requestlog_db:bool, requestlog_details:enum, \
            metrics:enum, metrics_dblog:bool, metrics_filelog:bool, email:bool
            debug:enum, debug_http:bool, debug_dblog:bool, debug_filelog:bool }}`
     */
    public function GetClientObject(bool $admin = false) : array
    { 
        $data = array( 'api' => (new VersionInfo())->major, 'apps' => array() );
        
        foreach (Main::GetInstance()->GetApps() as $appname=>$app)
        {
            $data['apps'][$appname] = $admin ? $app::getVersion() :
                implode('.',array_slice(explode('.',$app::getVersion()),0,2));
        }
        
        $data['features'] = array(
            'enabled' => $this->isEnabled(),
            'read_only' => (bool)$this->GetFeature('read_only',false) // no temp
        );
        
        if ($admin)
        {
            $data['datadir'] = $this->GetDataDir();
            
            $data['features'] = array_merge($data['features'], array(
                'requestlog_file' => $this->GetEnableRequestLogFile(),
                'requestlog_db' => $this->GetEnableRequestLogDB(),
                'requestlog_details' => array_flip(self::RQLOG_DETAILS_TYPES)[$this->GetRequestLogDetails()],
                'metrics' => array_flip(self::METRICS_TYPES)[$this->GetFeature('metrics',false)], // no temp
                'metrics_dblog' => $this->GetMetricsLog2DB(),
                'metrics_filelog' => $this->GetMetricsLog2File(),
                'email' => $this->GetEnableEmail(),
                'debug' => array_flip(self::DEBUG_TYPES)[$this->GetFeature('debug',false)], // no temp
                'debug_http' => $this->GetDebugOverHTTP(),
                'debug_dblog' => $this->GetDebugLog2DB(),
                'debug_filelog' => $this->GetDebugLog2File()
            ));
        }

        return $data;
    }
}
