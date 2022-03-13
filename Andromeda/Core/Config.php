<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Emailer.php");
require_once(ROOT."/Core/BaseApp.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/Exceptions.php");

/** Exception indicating that a mailer was requested but it is disabled */
class EmailDisabledException extends Exceptions\ClientErrorException { public $message = "EMAIL_DISABLED"; }

/** Exception indicating that a mailer was requested but none are configured */
class EmailerUnavailableException extends Exceptions\ClientErrorException { public $message = "EMAILER_UNAVAILABLE"; }

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
    protected FieldTypes\Date $date_created;
    protected FieldTypes\StringType $version; // TODO comments
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Date('date_created');
        $this->version = $fields[] = new FieldTypes\StringType('version');
        
        $this->RegisterFields($fields);
        
        parent::CreateFields();
    }
    
    /** Returns the database schema version */
    public function getVersion() : string 
    {
        return $this->version->GetValue(); 
    }
    
    /** 
     * Sets the database schema version to the given value
     * @param string $version schema version
     * @return $this
     */
    public function setVersion(string $version) : self 
    { 
        $this->version->SetValue($version); return $this; 
    }
    
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array if admin: `{date_created:float}`
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $data = array();

        if ($admin)
        {
            $data['date_created'] = $this->date_created->GetValue();
            // TODO what about version? not sure how accounts/files handle it
            // TODO don't forget to have files/accounts call this also
        }
        
        return $data;
    }
}

/** The global framework config stored in the database */
final class Config extends BaseConfig
{
    use TableNoChildren;
    
    private FieldTypes\NullStringType $datadir;
    private FieldTypes\BoolType $read_only;
    private FieldTypes\BoolType $enabled; // TODO comments
    private FieldTypes\BoolType $email;
    private FieldTypes\JsonArray $apps;
    
    private FieldTypes\BoolType $requestlog_db;
    private FieldTypes\BoolType $requestlog_file;
    private FieldTypes\IntType $requestlog_details;
    private FieldTypes\IntType $debug;
    private FieldTypes\BoolType $debug_http;
    private FieldTypes\BoolType $debug_dblog;
    private FieldTypes\BoolType $debug_filelog;
    private FieldTypes\IntType $metrics;
    private FieldTypes\BoolType $metrics_dblog;
    private FieldTypes\BoolType $metrics_filelog;
    
    /** Creates a new config singleton with default values */
    public static function Create(ObjectDatabase $database) : self
    {
        $obj = parent::BaseCreate($database);
        $obj->version->SetValue(andromeda_version);
        return $obj;
    }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return 
        "[--read_only bool] [--enabled bool] [--email bool] [--datadir ?fspath] ".
        "[--requestlog_db bool] [--requestlog_file bool] [--requestlog_details ".implode('|',array_keys(self::RQLOG_DETAILS_TYPES))."] ".
        "[--debug ".implode('|',array_keys(self::DEBUG_TYPES))."] [--debug_http bool] [--debug_dblog bool] [--debug_filelog bool] ".
        "[--metrics ".implode('|',array_keys(self::METRICS_TYPES))."] [--metrics_dblog bool] [--metrics_filelog bool]"; }
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->datadir = $fields[] =            new FieldTypes\NullStringType('datadir');
        $this->read_only = $fields[] =          new FieldTypes\BoolType('read_only',false, false);
        $this->enabled = $fields[] =            new FieldTypes\BoolType('enabled',false, true);
        $this->email = $fields[] =              new FieldTypes\BoolType('email',false, true);
        $this->apps = $fields[] =               new FieldTypes\JsonArray('apps',false, array());
        
        $this->requestlog_db = $fields[] =      new FieldTypes\BoolType('requestlog_db',false, false);
        $this->requestlog_file  = $fields[] =   new FieldTypes\BoolType('requestlog_file',false, false);
        $this->requestlog_details = $fields[] = new FieldTypes\IntType ('requestlog_details',false, self::RQLOG_DETAILS_BASIC);
        $this->debug = $fields[] =              new FieldTypes\IntType ('debug',false, self::ERRLOG_ERRORS);
        $this->debug_http = $fields[] =         new FieldTypes\BoolType('debug_http',false, false);
        $this->debug_dblog = $fields[] =        new FieldTypes\BoolType('debug_dblog',false, true);
        $this->debug_filelog = $fields[] =      new FieldTypes\BoolType('debug_filelog',false, false);
        $this->metrics = $fields[] =            new FieldTypes\IntType ('metrics',false, 0);
        $this->metrics_dblog = $fields[] =      new FieldTypes\BoolType('metrics_dblog',false, false);
        $this->metrics_filelog = $fields[] =    new FieldTypes\BoolType('metrics_filelog',false, false);
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
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
            if ($datadir !== null && (!is_readable($datadir) || !is_writeable($datadir)))
                throw new UnwriteableDatadirException();
            $this->datadir->SetValue($datadir);
        }
        
        if ($input->HasParam('requestlog_db')) $this->requestlog_db->SetValue($input->GetParam('requestlog_db',SafeParam::TYPE_BOOL));
        if ($input->HasParam('requestlog_file')) $this->requestlog_file->SetValue($input->GetParam('requestlog_file',SafeParam::TYPE_BOOL));

        if ($input->HasParam('requestlog_details'))
        {
            $param = $input->GetParam('requestlog_details',SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, array_keys(self::RQLOG_DETAILS_TYPES));
            
            $this->requestlog_details->SetValue(self::RQLOG_DETAILS_TYPES[$param]);
        }
        
        if ($input->HasParam('debug'))
        {
            $param = $input->GetParam('debug',SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, array_keys(self::DEBUG_TYPES));
            
            $this->debug->SetValue(self::DEBUG_TYPES[$param]);
        }
        
        if ($input->HasParam('debug_http')) $this->debug_http->SetValue($input->GetParam('debug_http',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_dblog')) $this->debug_dblog->SetValue($input->GetParam('debug_dblog',SafeParam::TYPE_BOOL));
        if ($input->HasParam('debug_filelog')) $this->debug_filelog->SetValue($input->GetParam('debug_filelog',SafeParam::TYPE_BOOL));

        if ($input->HasParam('metrics'))
        {
            $param = $input->GetParam('metrics',SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, array_keys(self::METRICS_TYPES));
            
            $this->metrics->SetValue(self::METRICS_TYPES[$param]);
        }
        
        if ($input->HasParam('metrics_dblog')) $this->metrics_dblog->SetValue($input->GetParam('metrics_dblog',SafeParam::TYPE_BOOL));
        if ($input->HasParam('metrics_filelog')) $this->metrics_filelog->SetValue($input->GetParam('metrics_filelog',SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('read_only')) 
        {
            $ro = $input->GetParam('read_only',SafeParam::TYPE_BOOL);
            
            if (!$ro) $this->database->GetInternal()->setReadOnly(false); // make DB writable
            
            $this->read_only->SetValue($ro);
            
            if ($ro) $this->read_only->SetValue(false,true); // not really RO yet 
        }
        
        if ($input->HasParam('enabled')) $this->enabled->SetValue($input->GetParam('enabled',SafeParam::TYPE_BOOL));
        if ($input->HasParam('email')) $this->email->SetValue($input->GetParam('email',SafeParam::TYPE_BOOL));        
       
        return $this;
    }
    
    /**
     * returns the array of registered apps
     * @return String[]
     */
    public function GetApps() : array { return $this->apps->GetValue(); }
    
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
        
        $appver = BaseApp::getAppApiVersion($app);
        $ourver = (new VersionInfo(andromeda_version))->getCompatVer();
        if ($appver !== $ourver) 
            throw new AppVersionException("$app($appver) core($ourver)");
        
        Main::GetInstance()->LoadApp($app);
        
        $capps = $this->GetApps();        
        if (!in_array($app, $capps)) $capps[] = $app;        
        $this->apps->SetValue($capps); return $this;
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
        
        $this->apps->SetValue(array_values($capps)); return $this;
    }
    
    /** Returns whether the server is allowed to respond to requests */
    public function isEnabled() : bool { return $this->enabled->GetValue(); }
    
    /** Set whether the server is allowed to respond to requests */
    public function setEnabled(bool $enable) : self { $this->enabled->SetValue($enable); return $this; }
    
    private bool $dryrun = false;

    /** Returns true if the server is set to dry-run mode */
    public function isDryRun() : bool { return $this->dryrun; }
    
    /** Sets the server to dryrun mode if $val is true */
    public function setDryRun(bool $val = true) : self { $this->dryrun = $val; return $this; }
    
    /** Returns true if the server is set to read-only (not dry run) */
    public function isReadOnly() : bool { return $this->read_only->GetValue(); }
    
    /** Returns the configured global data directory path */
    public function GetDataDir() : ?string { $dir = $this->datadir->GetValue(); if ($dir) $dir .= '/'; return $dir; }
    
    /** Returns true if request logging to DB is enabled */
    public function GetEnableRequestLogDB() : bool { return $this->requestlog_db->GetValue(); }
    
    /** Returns true if request logging to data dir file is enabled */
    public function GetEnableRequestLogFile() : bool { return $this->requestlog_file->GetValue(); }
    
    /** Returns true if request logging is enabled */
    public function GetEnableRequestLog() : bool { return $this->GetEnableRequestLogDB() || $this->GetEnableRequestLogFile(); }
    
    /** log basic details params and object IDs */
    const RQLOG_DETAILS_BASIC = 1;
    
    /** log more detailed info, and full objects when deleted */
    const RQLOG_DETAILS_FULL = 2;
    
    const RQLOG_DETAILS_TYPES = array('none'=>0, 'basic'=>self::RQLOG_DETAILS_BASIC, 'full'=>self::RQLOG_DETAILS_FULL);
    
    /** Returns the configured request log details detail level */
    public function GetRequestLogDetails() : int { return $this->requestlog_details->GetValue(); }
    
    /** show a basic back trace */ 
    const ERRLOG_ERRORS = 1; 
    
    /** show a full back trace, loaded objects, SQL queries */
    const ERRLOG_DETAILS = 2;
    
    /** also show input params, function arguments, SQL values */ 
    const ERRLOG_SENSITIVE = 3;
    
    const DEBUG_TYPES = array('none'=>0, 'errors'=>self::ERRLOG_ERRORS, 'details'=>self::ERRLOG_DETAILS, 'sensitive'=>self::ERRLOG_SENSITIVE);
    
    /** Returns the current debug level */
    public function GetDebugLevel() : int { return $this->debug->GetValue(); }
    
    /**
     * Sets the current debug level
     * @param bool $temp if true, only for this request
     */
    public function SetDebugLevel(int $data, bool $temp = true) : self { $this->debug->SetValue($data, $temp); return $this; }
    
    /** Gets whether the server should log errors to the database */
    public function GetDebugLog2DB()   : bool { return $this->debug_dblog->GetValue(); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetDebugLog2File() : bool { return $this->debug_filelog->GetValue(); } 
    
    /** Gets whether debug should be allowed over a non-privileged interface (also affects metrics) */
    public function GetDebugOverHTTP() : bool { return $this->debug_http->GetValue(); }    
    
    /** Show basic performance metrics */
    const METRICS_BASIC = 1;
    
    /** Show extended performance metrics */
    const METRICS_EXTENDED = 2;
    
    const METRICS_TYPES = array('none'=>0, 'basic'=>self::METRICS_BASIC, 'extended'=>self::METRICS_EXTENDED);
    
    /** Returns the current metrics log level */
    public function GetMetricsLevel() : int { return $this->metrics->GetValue(); }
    
    /**
     * Sets the current metrics log level
     * @param bool $temp if true, only for this request
     */
    public function SetMetricsLevel(int $data, bool $temp = true) : self { $this->metrics->SetValue($data, $temp); return $this; }
    
    /** Gets whether the server should log metrics to the database */
    public function GetMetricsLog2DB()   : bool { return $this->metrics_dblog->GetValue(); }
    
    /** Gets whether the server should log errors to a log file in the datadir */
    public function GetMetricsLog2File() : bool { return $this->metrics_filelog->GetValue(); } 
    
    /** Gets whether using configured emailers is currently allowed */
    public function GetEnableEmail() : bool { return $this->email->GetValue(); }

    /**
     * Retrieves a configured mailer service, picking one randomly 
     * @throws EmailDisabledException if email is disabled
     * @throws EmailerUnavailableException if not configured
     */
    public function GetMailer() : Emailer
    {
        if (!$this->GetEnableEmail()) throw new EmailDisabledException();
        
        $mailers = Emailer::LoadAll($this->database);
        if (count($mailers) == 0) throw new EmailerUnavailableException();
        return $mailers[array_rand($mailers)]->Activate();
    }
    
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array `{apiver:int, apps:[{string:string}], read_only:bool, enabled:bool}` \
         if admin, add: `{datadir:?string, \
            requestlog_file:bool, requestlog_db:bool, requestlog_details:enum, \
            metrics:enum, metrics_dblog:bool, metrics_filelog:bool, email:bool
            debug:enum, debug_http:bool, debug_dblog:bool, debug_filelog:bool }`
     * @see BaseConfig::GetClientObject()
     */
    public function GetClientObject(bool $admin = false) : array
    { 
        $data = parent::GetClientObject($admin);
        
        $data['apiver'] = (new VersionInfo(andromeda_version))->major;
        
        foreach (Main::GetInstance()->GetApps() as $name=>$app)
        {
            $data['apps'][$name] = $admin ? $app::getVersion() : 
                (new VersionInfo($app::getVersion()))->getCompatVer();
        }
        
        $data['enabled'] = $this->enabled->GetValue();
        $data['read_only'] = $this->read_only->GetValue(false);
        
        if ($admin)
        {
            $data['datadir'] =            $this->datadir->GetValue();
            $data['email'] =              $this->email->GetValue();
            $data['requestlog_file'] =    $this->requestlog_file->GetValue();
            $data['requestlog_db'] =      $this->requestlog_db->GetValue();
            $data['requestlog_details'] = array_flip(self::RQLOG_DETAILS_TYPES)[$this->requestlog_details->GetValue()];
            $data['metrics'] =            array_flip(self::METRICS_TYPES)[$this->metrics->GetValue(false)]; // no temp
            $data['metrics_dblog'] =      $this->metrics_dblog->GetValue();
            $data['metrics_filelog'] =    $this->metrics_filelog->GetValue();
            $data['debug'] =              array_flip(self::DEBUG_TYPES)[$this->debug->GetValue(false)]; // no temp
            $data['debug_http'] =         $this->debug_http->GetValue();
            $data['debug_dblog'] =        $this->debug_dblog->GetValue();
            $data['debug_filelog'] =      $this->debug_filelog->GetValue();

            foreach ($this->GetApps() as $app) 
                if (!array_key_exists($app, $data['apps']))
                    $data['apps'][$app] = "FAILED_LOAD";
        }

        return $data;
    }
}
