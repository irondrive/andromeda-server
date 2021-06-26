<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/core/logging/RequestLog.php");
require_once(ROOT."/core/logging/ActionMetrics.php");

/** Log entry representing an API request */
class RequestMetrics extends StandardObject
{    
    public const IDLength = 20;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'actions' => new FieldTypes\Scalar(0),//new FieldTypes\ObjectRefs(ActionMetrics::class, 'request'), // TODO
            'request' => new FieldTypes\ObjectRef(RequestLog::class),
            'peak_memory' => null,
            'nincludes' => null,
            'nobjects' => null,
            'construct__db_reads' => null,
            'construct__db_read_time' => null,
            'construct__db_writes' => null,
            'construct__db_write_time' => null,
            'construct__code_time' => null,
            'construct__total_time' => null,
            'construct__queries' => new FieldTypes\JSON(),
            'total__db_reads' => null,
            'total__db_read_time' => null,
            'total__db_writes' => null,
            'total__db_write_time' => null,
            'total__code_time' => null,
            'total__total_time' => null,
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
     * @param ObjectDatabase $origdb DB used for the request
     * @param RequestLog $reqlog request log for the request
     * @param DBStats $construct construct stats
     * @param DBStats $total total request stats
     * @return self created metrics object
     */
    public static function Create(int $level, ObjectDatabase $origdb, RequestLog $reqlog, DBStats $construct, DBStats $total) : self
    {
        $database = new ObjectDatabase();
        
        $obj = static::BaseCreate($database)->SetObject('request',$reqlog);
        
        $obj->SetScalar('peak_memory', memory_get_peak_usage())
            ->SetScalar('nincludes', count(get_included_files()))
            ->SetScalar('nobjects', count($origdb->getLoadedObjects()));
        
        foreach ($construct->getStats() as $statkey=>$statval)
            $obj->SetScalar("construct__$statkey", $statval);
        
        foreach ($total->getStats() as $statkey=>$statval)
            $obj->SetScalar("total__$statkey", $statval);
    
        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->SetScalar('construct__queries', $construct->getQueries())
                ->SetScalar('gcstats',gc_status())->SetScalar('rusage',getrusage())
                ->SetScalar('includes',get_included_files())
                ->SetScalar('objects',$origdb->getLoadedObjects())
                ->SetScalar('queries',$origdb->getAllQueries())
                ->SetScalar('debuglog',ErrorManager::GetInstance()->GetDebugLog());
        }
        
        $obj->Save(); $database->commit(); return $obj;
    }
    
    /**
     * Saves this metrics log, either to DB or file (or both)
     * {@inheritDoc}
     * @see BaseObject::Save()
     */
    public function Save(bool $onlyMandatory = false) : self
    {
        $main = Main::GetInstance();

        $config = $main->GetConfig();
        
        if ($config->GetMetricsLog2DB())
        {            
            parent::Save(); // ignore $onlyMandatory
        }
        
        if ($config->GetMetricsLog2File() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            $data = $this->GetClientObject();
            
            $data = Utilities::JSONEncode($data);
            
            file_put_contents("$logdir/metrics.log", $data."\r\n", FILE_APPEND); 
        }

        return $this;
    }
    
    /**
     * Returns the printable client object of this metrics
     * @return array `{peak_memory:int, nincludes:int, nobjects:int \
           construct:{db_reads:int,db_read_time:double,db_writes:int,db_write_time:double,code_time:double,total_time:double}, \
           total:{db_reads:int,db_read_time:double,db_writes:int,db_write_time:double,code_time:double,total_time:double}}`,
        if extended, add `{gcstats:array,rusage:array,includes:array,objects:array<class,[id]>,queries:array,debuglog:array}`
     */
    public function GetClientObject() : array
    {
        $retval = array(
            'peak_memory' => $this->GetScalar('peak_memory'),
            'nincludes' => $this->GetScalar('nincludes'),
            'nobjects' => $this->GetScalar('nobjects'),
            'construct' => $this->GetAllScalars('construct'),
            'total' => $this->GetAllScalars('total')
        );
        
        foreach (array('gcstats','rusage','includes','objects','queries','debuglog') as $prop)
        {
            $val = $this->GetScalar($prop); if ($val !== null) $retval[$prop] = $val;
        }
        
        return $retval;
    }
}
