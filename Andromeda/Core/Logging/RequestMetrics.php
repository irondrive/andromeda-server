<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/Database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/Logging/RequestLog.php");
require_once(ROOT."/Core/Logging/DBStatsLog.php");
require_once(ROOT."/Core/Logging/ActionMetrics.php");
require_once(ROOT."/Core/Logging/CommitMetrics.php");

/** Log entry representing a performance metrics for a request */
final class RequestMetrics extends BaseObject
{
    use TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** @var FieldTypes\NullObjectRefT<RequestLog> */
    private FieldTypes\NullObjectRefT $requestlog;
    /** Timestamp of the request */
    private FieldTypes\Date $date_created;
    /** Peak memory usage reported by PHP */
    private FieldTypes\IntType $peak_memory;
    /** The number of included PHP files */
    private FieldTypes\IntType $nincludes;
    /** The number of objects loaded in database memory */
    private FieldTypes\IntType $nobjects;
    
    private FieldTypes\IntType $construct_db_reads;
    private FieldTypes\FloatType $construct_db_read_time;
    private FieldTypes\IntType $construct_db_writes;
    private FieldTypes\FloatType $construct_db_write_time;
    private FieldTypes\FloatType $construct_code_time;
    private FieldTypes\FloatType $construct_total_time;
    private FieldTypes\NullJsonArray $construct_queries;
    
    /** Garbage collection stats reported by PHP */
    private FieldTypes\NullJsonArray $gcstats;
    /** Resource usage reported by PHP */
    private FieldTypes\NullJsonArray $rusage;
    /** List of files included by PHP */
    private FieldTypes\NullJsonArray $includes;
    /** List of objects in database memory */
    private FieldTypes\NullJsonArray $objects;
    /** List of database queries */
    private FieldTypes\NullJsonArray $queries;
    /** The main debug log supplement */
    private FieldTypes\NullJsonArray $debuglog;
    
    private bool $writtenToFile = false;
    
    /** Array of action metrics if not saved */
    private array $actions;
    /** Array of commit metrics if not saved */
    private array $commits;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->requestlog = $fields[] =   new FieldTypes\NullObjectRefT(RequestLog::class,'requestlog');
        $this->date_created = $fields[] = new FieldTypes\Date('date_created');
        $this->peak_memory = $fields[] =  new FieldTypes\IntType('peak_memory');
        $this->nincludes = $fields[] =    new FieldTypes\IntType('nincludes');
        $this->nobjects = $fields[] =     new FieldTypes\IntType('nobjects');
        
        $this->construct_db_reads = $fields[] =      new FieldTypes\IntType('construct_db_reads');
        $this->construct_db_read_time = $fields[] =  new FieldTypes\FloatType('construct_db_read_time');
        $this->construct_db_writes = $fields[] =     new FieldTypes\IntType('construct_db_writes');
        $this->construct_db_write_time = $fields[] = new FieldTypes\FloatType('construct_db_write_time');
        $this->construct_code_time = $fields[] =     new FieldTypes\FloatType('construct_code_time');
        $this->construct_total_time = $fields[] =    new FieldTypes\FloatType('construct_total_time');
        $this->construct_queries = $fields[] =       new FieldTypes\NullJsonArray('construct_queries');

        $this->gcstats = $fields[] =  new FieldTypes\NullJsonArray('gcstats');
        $this->rusage = $fields[] =   new FieldTypes\NullJsonArray('rusage');
        $this->includes = $fields[] = new FieldTypes\NullJsonArray('includes');
        $this->objects = $fields[] =  new FieldTypes\NullJsonArray('objects');
        $this->queries = $fields[] =  new FieldTypes\NullJsonArray('queries');
        $this->debuglog = $fields[] = new FieldTypes\NullJsonArray('debuglog');
        
        $this->RegisterFields($fields, self::class);
        $this->DBStatsCreateFields();
        
        $this->database->RegisterUniqueKey(self::class, 'requestlog');
         
        parent::CreateFields();
    }

    /**
     * Logs metrics and returns a metrics object
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestLog $reqlog request log for the request
     * @param DBStats $construct construct stats
     * @param array $actions array<RunContext> actions with metrics
     * @param array $commits array<DBStats> commit metrics
     * @param DBStats $total total request stats
     * @return static created metrics object
     */
    public static function Create(int $level, ObjectDatabase $database, ?RequestLog $reqlog,
                                  DBStats $construct, array $actions, array $commits, DBStats $total) : self
    {        
        $obj = parent::BaseCreate($database);
        $obj->requestlog->SetValue($reqlog);
        
        $obj->peak_memory->SetValue(memory_get_peak_usage());
        $obj->nincludes->SetValue(count(get_included_files()));
        $obj->nobjects->SetValue($database->getLoadedCount());
        
        $obj->construct_db_reads->SetValue($construct->GetReads());
        $obj->construct_db_read_time->SetValue($construct->GetReadTime());
        $obj->construct_db_writes->SetValue($construct->GetWrites());
        $obj->construct_db_write_time->SetValue($construct->GetWriteTime());
        $obj->construct_code_time->SetValue($construct->GetCodeTime());
        $obj->construct_total_time->SetValue($construct->GetTotalTime());

        $obj->SetDBStats($total);

        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->construct_queries->SetValue($construct->getQueries());
            $obj->gcstats->SetValue(gc_status());
            $obj->rusage->SetValue(getrusage());
            $obj->includes->SetValue(get_included_files());
            $obj->objects->SetValue($database->getLoadedObjects());
            $obj->queries->SetValue($total->getQueries());
            $obj->debuglog->SetValue(ErrorManager::GetInstance()->GetDebugLog());
        }
        
        $obj->actions = array();
        $obj->commits = array();
        
        foreach ($actions as $context)
            $obj->actions[] = ActionMetrics::Create($level, $database, $obj, $context);
        
        foreach ($commits as $cstats)
            $obj->commits[] = CommitMetrics::Create($level, $database, $obj, $cstats);
            
        return $obj;
    }

    public function Save(bool $isRollback = false) : self
    {
        $config = Main::GetInstance()->TryGetConfig();
        
        if ($config && $config->GetMetricsLog2DB())
        {
            parent::Save(); // ignore $isRollback (not used)
            
            if (isset($this->actions)) foreach ($this->actions as $action) $action->Save();
            if (isset($this->commits)) foreach ($this->commits as $commit) $commit->Save();
        }
        
        if ($config && $config->GetMetricsLog2File() &&
            ($logdir = $config->GetDataDir()) !== null && !$this->writtenToFile)
        {
            $this->writtenToFile = true;
            
            $data = Utilities::JSONEncode($this->GetClientObject());
            
            file_put_contents("$logdir/metrics.log", $data."\r\n", FILE_APPEND); 
        }

        return $this;
    }

    /**
     * Returns the printable client object of this metrics
     * @param bool $isError if true, omit duplicated debugging information
     * @return array `{date_created:float, peak_memory:int, nincludes:int, nobjects:int, total_stats:DBStatsLog, action_stats:[ActionMetrics] \
           construct_stats:{reads:int,read_time:float,writes:int,write_time:float,code_time:float,total_time:float}}`
        if extended, add `{gcstats:array,rusage:array,includes:array,construct_stats:{queries:[{time:float,query:string}]}}`
        if extended and not accompanying debug output, omit add `{objects:array<class,[string]>,queries:array,debuglog:array}`
     * @see DBStatsLog::GetDBStatsClientObject()
     */
    public function GetClientObject(bool $isError = false) : array
    {
        $actions = $this->actions ?? ActionMetrics::LoadByRequest($this->database,$this);
        $commits = $this->commits ?? CommitMetrics::LoadByRequest($this->database,$this);
        
        $retval = array(
            'date_created' => $this->date_created->GetValue(),
            'peak_memory' =>  $this->peak_memory->GetValue(),
            'nincludes' =>    $this->nincludes->GetValue(),
            'nobjects' =>     $this->nobjects->GetValue(),
            'construct_stats' => array
            (
                'reads' =>      $this->construct_db_reads->GetValue(),
                'read_time' =>  $this->construct_db_read_time->GetValue(),
                'writes' =>     $this->construct_db_writes->GetValue(),
                'write_time' => $this->construct_db_write_time->GetValue(),
                'code_time' =>  $this->construct_code_time->GetValue(),
                'total_time' => $this->construct_total_time->GetValue()
            ),
            'action_stats' => array_values(array_map(function(ActionMetrics $o){
                return $o->GetClientObject(); }, $actions)),
            'commit_stats' => array_values(array_map(function(CommitMetrics $o){
                return $o->GetClientObject(); }, $commits)),
            'total_stats' => $this->GetDBStatsClientObject()
        );
        
        if ($this->gcstats->TryGetValue() !== null) // is EXTENDED
        {
            $retval['construct_stats']['queries'] = $this->construct_queries->TryGetValue();
            
            $retval['gcstats'] = $this->gcstats->TryGetValue();
            $retval['rusage'] = $this->rusage->TryGetValue();
            $retval['includes'] = $this->includes->TryGetValue();
            
            if (!$isError) // duplicated in error log
            {
                $retval['objects'] = $this->objects->TryGetValue();
                $retval['queries'] = $this->queries->TryGetValue();
                $retval['debuglog'] = $this->debuglog->TryGetValue();
            }
        }

        return $retval;
    }
}
