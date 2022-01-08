<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/Database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/Core/Logging/RequestLog.php");
require_once(ROOT."/Core/Logging/ActionMetrics.php");
require_once(ROOT."/Core/Logging/CommitMetrics.php");

/** Log entry representing an metrics for a request */
class RequestMetrics extends StandardObject
{    
    public const IDLength = 20;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'objs_actions' => new FieldTypes\ObjectRefs(ActionMetrics::class, 'request'),
            'objs_commits' => new FieldTypes\ObjectRefs(CommitMetrics::class, 'request'),
            'obj_requestlog' => new FieldTypes\ObjectRef(RequestLog::class),
            'peak_memory' => null,
            'nincludes' => null,
            'nobjects' => null,
            'construct_db_reads' => null,
            'construct_db_read_time' => null,
            'construct_db_writes' => null,
            'construct_db_write_time' => null,
            'construct_code_time' => null,
            'construct_total_time' => null,
            'construct_queries' => new FieldTypes\JSON(),
            'total_db_reads' => null,
            'total_db_read_time' => null,
            'total_db_writes' => null,
            'total_db_write_time' => null,
            'total_code_time' => null,
            'total_total_time' => null,
            'gcstats' => new FieldTypes\JSON(),
            'rusage' => new FieldTypes\JSON(),
            'includes' => new FieldTypes\JSON(),
            'objects' => new FieldTypes\JSON(),
            'queries' => new FieldTypes\JSON(),
            'debuglog' => new FieldTypes\JSON()
        ));
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
        $obj = static::BaseCreate($database)->SetObject('requestlog',$reqlog);
        
        $obj->SetScalar('peak_memory', memory_get_peak_usage())
            ->SetScalar('nincludes', count(get_included_files()))
            ->SetScalar('nobjects', count($database->getLoadedObjectIDs()));
        
        foreach ($construct->getStats() as $statkey=>$statval)
            $obj->SetScalar("construct_$statkey", $statval);
        
        foreach ($total->getStats() as $statkey=>$statval)
            $obj->SetScalar("total_$statkey", $statval);
    
        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->SetScalar('construct_queries', $construct->getQueries())
                ->SetScalar('gcstats',gc_status())->SetScalar('rusage',getrusage())
                ->SetScalar('includes',get_included_files())
                ->SetScalar('objects',$database->getLoadedObjectIDs())
                ->SetScalar('queries',$database->getAllQueries())
                ->SetScalar('debuglog',ErrorManager::GetInstance()->GetDebugLog());
        }
        
        foreach ($actions as $context)
            ActionMetrics::Create($level, $database, $obj, $context);
        
        foreach ($commits as $cstats)
            CommitMetrics::Create($level, $database, $obj, $cstats);
            
        return $obj;
    }
    
    /**
     * Saves this metrics log, either to DB or file (or both)
     * {@inheritDoc}
     * @see BaseObject::Save()
     */
    public function Save(bool $onlyMandatory = false) : self
    {
        $config = Main::GetInstance()->TryGetConfig();
        
        if ($config && $config->GetMetricsLog2DB())
        {            
            parent::Save(); // ignore $onlyMandatory 
            
            foreach ($this->GetObjectRefs('actions') as $action) $action->Save();
            foreach ($this->GetObjectRefs('commits') as $commit) $commit->Save();
        }
        
        if ($config && $config->GetMetricsLog2File() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            $data = $this->GetClientObject(); 
            
            $data = Utilities::JSONEncode($data);
            
            file_put_contents("$logdir/metrics.log", $data."\r\n", FILE_APPEND); 
        }

        return $this;
    }
    
    /** @return array<string, ActionMetrics> */
    private function GetActions() : array 
    {
        $obj = $this->GetObjectRefs('actions');
        
        foreach ($obj as $o) assert($o instanceof ActionMetrics);
        
        return $obj;
    }
    
    /**
     * Returns the printable client object of this metrics
     * @param bool $isError if true, omit duplicated debugging information
     * @return array `{peak_memory:int, nincludes:int, nobjects:int, action_stats:[ActionMetrics] \
           construct_stats:{DBStats,queries:[string]}, total_stats:DBStats}`,
        if extended, add `{gcstats:array,rusage:array,includes:array,objects:array<class,[id]>,queries:array,debuglog:array}`
        if accompanying debug output, omit (objects, queries, debuglog)
     * @see DBStats::getStats()
     */
    public function GetClientObject(bool $isError = false) : array
    {
        $retval = array(
            'peak_memory' => $this->GetScalar('peak_memory'),
            'nincludes' => $this->GetScalar('nincludes'),
            'nobjects' => $this->GetScalar('nobjects'),
            'construct_stats' => $this->GetAllScalars('construct'),
            'action_stats' => array_values(array_map(function(ActionMetrics $o){
                return $o->GetClientObject(); }, $this->GetActions())),
            'commit_stats' => array_values(array_map(function(CommitMetrics $o){
                return $o->GetClientObject(); }, $this->GetObjectRefs('commits'))),
            'total_stats' => $this->GetAllScalars('total')
        );
            
        $props = array('gcstats','rusage','includes');
        
        if (!$isError) array_push($props, 'objects','queries','debuglog');
        
        foreach ($props as $prop)
        {
            $val = $this->TryGetScalar($prop); 
            
            if ($val !== null) $retval[$prop] = $val;
        }
        
        return $retval;
    }
}
