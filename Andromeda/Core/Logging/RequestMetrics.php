<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, /*phpstan*/RunContext, Utilities};
use Andromeda\Core\Database\{BaseObject, DBStats, FieldTypes, ObjectDatabase, TableTypes};

require_once(ROOT."/Core/Logging/Exceptions.php");

/** Log entry representing a performance metrics for a request */
final class RequestMetrics extends BaseObject
{
    use TableTypes\TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** 
     * Link to the main log for this request
     * @var FieldTypes\NullObjectRefT<RequestLog>
     */
    private FieldTypes\NullObjectRefT $requestlog;
    /** Timestamp of the request */
    private FieldTypes\Timestamp $date_created;
    /** Peak memory usage reported by PHP */
    private FieldTypes\IntType $peak_memory;
    /** The number of included PHP files */
    private FieldTypes\IntType $nincludes;
    /** The number of objects loaded in database memory */
    private FieldTypes\IntType $nobjects;
    
    private FieldTypes\IntType $init_db_reads;
    private FieldTypes\FloatType $init_db_read_time;
    private FieldTypes\IntType $init_db_writes;
    private FieldTypes\FloatType $init_db_write_time;
    private FieldTypes\FloatType $init_code_time;
    private FieldTypes\FloatType $init_total_time;
    
    /** @var FieldTypes\NullJsonArray<array<array{query:string,time:float}>> */
    private FieldTypes\NullJsonArray $init_queries;
    
    /** 
     * Garbage collection stats reported by PHP 
     * @var FieldTypes\NullJsonArray<array<string,scalar>>
     */
    private FieldTypes\NullJsonArray $gcstats;
    /**
     * Resource usage reported by PHP 
     * @var FieldTypes\NullJsonArray<array<string,scalar>>
     */
    private FieldTypes\NullJsonArray $rusage;
    /** 
     * List of files included by PHP 
     * @var FieldTypes\NullJsonArray<array<string>>
     */
    private FieldTypes\NullJsonArray $includes;
    /** 
     * List of objects in database memory 
     * @var FieldTypes\NullJsonArray<array<string, array<string, string>>>
     */
    private FieldTypes\NullJsonArray $objects;
    /** 
     * List of database queries 
     * @var FieldTypes\NullJsonArray<array<array{query:string,time:float}>>
     */
    private FieldTypes\NullJsonArray $queries;
    /** 
     * The main debug log supplement 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $debughints;
    
    private bool $writtenToFile = false;
    
    /** 
     * if not saved yet
     * @var array<ActionMetrics>
     */
    private array $actions;
    /** 
     * if not saved yet
     * @var array<CommitMetrics>
     */
    private array $commits;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $fields[] = $this->requestlog =   new FieldTypes\NullObjectRefT(RequestLog::class,'requestlog');
        $fields[] = $this->date_created = new FieldTypes\Timestamp('date_created');
        $fields[] = $this->peak_memory =  new FieldTypes\IntType('peak_memory');
        $fields[] = $this->nincludes =    new FieldTypes\IntType('nincludes');
        $fields[] = $this->nobjects =     new FieldTypes\IntType('nobjects');
        
        $fields[] = $this->init_db_reads =      new FieldTypes\IntType('init_db_reads');
        $fields[] = $this->init_db_read_time =  new FieldTypes\FloatType('init_db_read_time');
        $fields[] = $this->init_db_writes =     new FieldTypes\IntType('init_db_writes');
        $fields[] = $this->init_db_write_time = new FieldTypes\FloatType('init_db_write_time');
        $fields[] = $this->init_code_time =     new FieldTypes\FloatType('init_code_time');
        $fields[] = $this->init_total_time =    new FieldTypes\FloatType('init_total_time');
        $fields[] = $this->init_queries =       new FieldTypes\NullJsonArray('init_queries');

        $fields[] = $this->gcstats =  new FieldTypes\NullJsonArray('gcstats');
        $fields[] = $this->rusage =   new FieldTypes\NullJsonArray('rusage');
        $fields[] = $this->includes = new FieldTypes\NullJsonArray('includes');
        $fields[] = $this->objects =  new FieldTypes\NullJsonArray('objects');
        $fields[] = $this->queries =  new FieldTypes\NullJsonArray('queries');
        $fields[] = $this->debughints = new FieldTypes\NullJsonArray('debughints');
        
        $this->RegisterFields($fields, self::class);
        $this->DBStatsCreateFields();
         
        parent::CreateFields();
    }
    
    protected static function AddUniqueKeys(array& $keymap) : void
    {
        $keymap[self::class] = array('requestlog');
        
        parent::AddUniqueKeys($keymap);
    }
    
    /**
     * Logs metrics and returns a metrics object
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestLog $reqlog request log for the request
     * @param DBStats $initstat construct stats
     * @param array<RunContext> $actions actions with metrics
     * @param array<DBStats> $commits commit metrics
     * @param DBStats $totalstat total request stats
     * @return static created metrics object
     */
    public static function Create(int $level, ObjectDatabase $database, ?RequestLog $reqlog,
                                  DBStats $initstat, array $actions, array $commits, DBStats $totalstat) : self
    {        
        $obj = static::BaseCreate($database);
        $obj->date_created->SetValue($database->GetTime());
        $obj->requestlog->SetObject($reqlog);
        
        $obj->peak_memory->SetValue(memory_get_peak_usage());
        $obj->nincludes->SetValue(count(get_included_files()));
        $obj->nobjects->SetValue($database->getLoadedCount());
        
        $obj->init_db_reads->SetValue($initstat->GetReads());
        $obj->init_db_read_time->SetValue($initstat->GetReadTime());
        $obj->init_db_writes->SetValue($initstat->GetWrites());
        $obj->init_db_write_time->SetValue($initstat->GetWriteTime());
        $obj->init_code_time->SetValue($initstat->GetCodeTime());
        $obj->init_total_time->SetValue($initstat->GetTotalTime());

        $obj->SetDBStats($totalstat);

        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->init_queries->SetArray($initstat->getQueries());
            $obj->gcstats->SetArray(gc_status());
            
            $rusage = getrusage();
            if ($rusage === false) $rusage = null;
            $obj->rusage->SetArray($rusage);
            
            $obj->includes->SetArray(get_included_files());
            $obj->objects->SetArray($database->getLoadedObjects());
            $obj->queries->SetArray($totalstat->getQueries());
            
            $errman = $database->GetApiPackage()->GetErrorManager();
            $obj->debughints->SetArray($errman->GetDebugHints());
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
        $config = $this->GetApiPackage()->GetConfig();
        
        if ($config->GetMetricsLog2DB())
        {
            parent::Save(); // ignore $isRollback (not used)
            
            if (isset($this->actions)) foreach ($this->actions as $action) $action->Save();
            if (isset($this->commits)) foreach ($this->commits as $commit) $commit->Save();
        }
        
        if ($config->GetMetricsLog2File() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            if ($this->writtenToFile) 
                throw new MultiFileWriteException();
            $this->writtenToFile = true;
            
            $data = Utilities::JSONEncode($this->GetClientObject());
            file_put_contents("$logdir/metrics.log", $data."\r\n", FILE_APPEND); 
        }

        return $this;
    }

    /**
     * Returns the printable client object of this metrics
     * @param bool $isError if true, omit duplicated debugging information
     * @return array<mixed> `{date_created:float, peak_memory:int, nincludes:int, nobjects:int, total_stats:DBStatsLog, action_stats:[ActionMetrics] \
           init_stats:{reads:int,read_time:float,writes:int,write_time:float,code_time:float,total_time:float}}`
        if extended, add `{gcstats:array,rusage:array,includes:array,init_stats:{queries:[{time:float,query:string}]}}`
        if extended and not accompanying debug output, omit add `{objects:array<class,[string]>,queries:array,debughints:array}`
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
            'init_stats' => array
            (
                'reads' =>      $this->init_db_reads->GetValue(),
                'read_time' =>  $this->init_db_read_time->GetValue(),
                'writes' =>     $this->init_db_writes->GetValue(),
                'write_time' => $this->init_db_write_time->GetValue(),
                'code_time' =>  $this->init_code_time->GetValue(),
                'total_time' => $this->init_total_time->GetValue()
            ),
            'action_stats' => array_values(array_map(function(ActionMetrics $o){
                return $o->GetClientObject(); }, $actions)),
            'commit_stats' => array_values(array_map(function(CommitMetrics $o){
                return $o->GetClientObject(); }, $commits)),
            'total_stats' => $this->GetDBStatsClientObject()
        );
        
        if ($this->gcstats->TryGetArray() !== null) // is EXTENDED
        {
            $retval['init_stats']['queries'] = $this->init_queries->TryGetArray();
            
            $retval['gcstats'] = $this->gcstats->TryGetArray();
            $retval['rusage'] = $this->rusage->TryGetArray();
            $retval['includes'] = $this->includes->TryGetArray();
            
            if (!$isError) // duplicated in error log
            {
                $retval['objects'] = $this->objects->TryGetArray();
                $retval['queries'] = $this->queries->TryGetArray();
                $retval['debughints'] = $this->debughints->TryGetArray();
            }
        }

        return $retval;
    }
}
