<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\Config;
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\{Input, SafeParams};

/** 
 * Log entry representing an app action in a request 
 * 
 * Performs a join with RequestLog when loading so that the user can
 * filter by request parameters and action parameters simulataneously.
 */
class ActionLog extends BaseLog
{
    use TableTypes\HasTable;

    /** @return array<string, class-string<self>> */
    public static function GetChildMap() : array 
    {
        $map = array("" => self::class); 
        
        foreach (array()/* TODO FIX ME AppRunner::GetInstance()->GetApps()*/ as $name=>$app)
        {
            $logclass = $app->getLogClass();
            if ($logclass !== null) $map[$name] = $logclass;
        } 
        return $map;
    }

    public static function HasTypedRows() : bool { return true; }
    
    public static function GetWhereChild(ObjectDatabase $db, QueryBuilder $q, string $class) : string
    {
        $map = array_flip(self::GetChildMap());
        $table = $db->GetClassTableName(self::class);
        
        if ($class !== self::class && array_key_exists($class, $map))
            return $q->Equals("$table.app", $map[$class]);
        else 
        {
            return $q->Not($q->ManyEqualsOr("$table.app",
                array_keys(array()/* TODO FIX ME AppRunner::GetInstance()->GetApps()*/)));
        }
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(array $row) : string
    {
        $app = $row['app']; $map = self::GetChildMap();
        
        return array_key_exists($app, $map) ? $map[$app] : self::class;
    }
    
    /** 
     * The request log this action was a part of 
     * @var FieldTypes\ObjectRefT<RequestLog>
     */
    private FieldTypes\ObjectRefT $requestlog;
    /** Action app name */
    private FieldTypes\StringType $app;
    /** Action action name */
    private FieldTypes\StringType $action;
    /** Basic auth username if present */
    private FieldTypes\NullStringType $authuser;
    /** 
     * Optional input parameter logging 
     * @var FieldTypes\NullJsonArray<array<string, mixed>>
     */
    private FieldTypes\NullJsonArray $params;
    /**
     * Optional input files logging
     * @var FieldTypes\NullJsonArray<array<string, mixed>>
     */
    private FieldTypes\NullJsonArray $files;
    /** 
     * Optional app-specific details if no subtable 
     * @var FieldTypes\NullJsonArray<array<string, mixed>>
     */
    private FieldTypes\NullJsonArray $details;
    
    /** 
     * Temporary array of logged input params to be saved 
     * @var array<string, mixed>
     */
    private array $params_tmp;
    /**
     * Temporary array of logged input files to be saved
     * @var array<string, mixed>
     */
    private array $files_tmp;
    /** 
     * Temporary array of logged details to be saved 
     * @var array<string, mixed>
     */
    private array $details_tmp;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->requestlog = new FieldTypes\ObjectRefT(RequestLog::class, 'requestlog');
        
        $fields[] = $this->app =     new FieldTypes\StringType('app');
        $fields[] = $this->action =  new FieldTypes\StringType('action');
        $fields[] = $this->authuser = new FieldTypes\NullStringType('authuser');
        $fields[] = $this->params =  new FieldTypes\NullJsonArray('params');
        $fields[] = $this->files =   new FieldTypes\NullJsonArray('files');
        $fields[] = $this->details = new FieldTypes\NullJsonArray('details');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** 
     * Creates a new action log with the given input and request log 
     * @return static
     */
    public static function Create(ObjectDatabase $database, RequestLog $reqlog, Input $input) : self
    {
        $obj = static::BaseCreate($database);
        
        $obj->requestlog->SetObject($reqlog);
        $obj->app->SetValue($input->GetApp());
        $obj->action->SetValue($input->GetAction());
        
        $input->SetLogger($obj);
        
        return $obj;
    }

    /**
     * Returns all action logs for a request log
     * @param ObjectDatabase $database database reference
     * @param RequestLog $reqlog request log
     * @return array<static> loaded ActionLogs
     */
    public static function LoadByRequest(ObjectDatabase $database, RequestLog $reqlog) : array
    {
        return $database->LoadObjectsByKey(static::class, 'requestlog', $reqlog->ID());
    }
    
    /**
     * Returns the configured details log detail level
     *
     * If 0, details logs will be discarded, else see Config enum
     * @see \Andromeda\Core\Config::GetRequestLogDetails()
     */
    public function GetDetailsLevel() : int { return $this->GetApiPackage()->GetConfig()->GetRequestLogDetails(); }
    
    /**
     * Returns true if the configured details log detail level is >= full
     * @see \Andromeda\Core\Config::GetRequestLogDetails()
     */
    public function isFullDetails() : bool { return $this->GetDetailsLevel() >= Config::RQLOG_DETAILS_FULL; }
    
    /** 
     * Log to the app-specific "details" field
     * 
     * This should be used for data that doesn't make sense to have its own DB column.
     * As this field is stored as JSON, its subfields cannot be selected by in the DB.
     * 
     * @param string $key array key in log
     * @param mixed $value the data value
     * @return $this
     */
    public function LogDetails(string $key, $value) : self
    {
        $this->details_tmp ??= array();
        $this->details_tmp[$key] = $value;
        return $this;
    }

    /** 
     * Returns a direct reference to the input params log array 
     * @return array<string, mixed>
     */
    public function &GetParamsLogRef() : array
    {
        $this->params_tmp ??= array();
        return $this->params_tmp;
    }
    
    /**
     * Returns a direct reference to the input files log array
     * @return array<string, mixed>
     */
    public function &GetFilesLogRef() : array
    {
        $this->files_tmp ??= array();
        return $this->files_tmp;
    }
    
    /** @return $this */
    public function SetAuthUser(string $username) : self
    {
        $this->authuser->SetValue($username); return $this;
    }
    
    public function Save(bool $isRollback = false) : self
    {
        if (!empty($this->params_tmp))
            $this->params->SetArray($this->params_tmp);
        
        if (!empty($this->files_tmp))
            $this->files->SetArray($this->files_tmp);
            
        if (!empty($this->details_tmp))
            $this->details->SetArray($this->details_tmp);
            
        if (!$this->GetApiPackage()->GetConfig()->GetEnableRequestLogDB())
            return $this; // might only be doing file logging
        
        return parent::Save(); // ignore isRollback
    }
    
    public static function GetPropUsage(bool $join = true) : string 
    {
        $appstr = implode("|",array_filter(array_keys(self::GetChildMap())));
        return "[--app $appstr] [--action alphanum]".($join ? ' '.RequestLog::GetPropUsage(false):''); 
    }
    
    /** Returns the app-specific usage for classes that extend this one */
    protected static function GetAppPropUsage() : string { return ""; }
    
    /** 
     * Returns the array of app-specific propUsage strings
      * @return array<string> 
      */
    public static function GetAppPropUsages() : array
    {
        $retval = array();
        foreach (self::GetChildMap() as $appname=>$logclass)
            if ($appname) $retval[] = "--app $appname ".$logclass::GetAppPropUsage();
        return $retval;
    }

    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $join = true) : array
    {
        $criteria = array();

        if ($params->HasParam('app')) $criteria[] = $q->Equals("app", $params->GetParam('app')->GetAlphanum());
        if ($params->HasParam('action')) $criteria[] = $q->Equals("action", $params->GetParam('action')->GetAlphanum());
        
        if (!$join) return $criteria;
        
        $q->Join($database, RequestLog::class, 'id', self::class, 'requestlog'); // enable loading by RequestLog criteria
        return array_merge($criteria, RequestLog::GetPropCriteria($database, $q, $params, false));
    }
    
    /** @return class-string<self> */
    protected static function GetPropClass(SafeParams $params) : string
    {
        if ($params->HasParam('app'))
        {
            $map = self::GetChildMap();
            $app = $params->GetParam('app')->GetAlphanum();
            if (array_key_exists($app, $map)) return $map[$app];
        }
        
        return self::class;
    }

    public static function LoadByParams(ObjectDatabase $database, SafeParams $params) : array
    {
        RequestLog::LoadByParams($database, $params); // pre-load in one query
        
        $objs = parent::LoadByParams($database, $params);
        
        // now we need to re-sort by time as loading via child classes may not sort the result
        $desc = !$params->GetOptParam('asc',false)->GetBool(); // default desc

        uasort($objs, function(self $a, self $b)use($desc)
        {
            $v1 = $a->requestlog->GetObject()->GetTime();
            $v2 = $b->requestlog->GetObject()->GetTime();
            return $desc ? ($v2 <=> $v1) : ($v1 <=> $v2);
        });
 
        return $objs;
    }
    
    /**
     * Returns the printable client object of this action log
     * @param bool $expand if true, expand linked objects
     * @return array<mixed> `{app:string, action:string, ?authuser:string, ?params:array, ?files:array, ?details:array}`
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = array(
            'app' => $this->app->GetValue(),
            'action' => $this->action->GetValue()
        );
        
        if (($authuser = $this->authuser->TryGetValue() !== null))
            $retval['authuser'] = $authuser;
        
        if (($params = $this->params->TryGetArray()) !== null)
            $retval['params'] = $params;
        
        if (($files = $this->files->TryGetArray()) !== null)
            $retval['files'] = $files;
            
        if (($details = $this->details->TryGetArray()) !== null)
            $retval['details'] = $details;
        
        return $retval;
    }
    
    /**
     * Returns the printable client object of this action log + its request
     * @see RequestLog::GetClientObject
     * @see ActionLog::GetClientObject
     * @return array<mixed> ActionLog + `{request:RequestLog}`
     */
    public function GetFullClientObject(bool $expand = false) : array
    {
        $retval = $this->GetClientObject($expand);
        
        $retval['request'] = $this->requestlog->GetObject()->GetClientObject();
        
        return $retval;
    }
}
