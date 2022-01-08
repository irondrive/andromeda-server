<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\RunContext;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

require_once(ROOT."/Core/Logging/RequestMetrics.php");

/** Log entry representing metrics for an app action */
class ActionMetrics extends StandardObject
{    
    public const IDLength = 20;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_request' => new FieldTypes\ObjectRef(RequestMetrics::class, 'actions'),
            'obj_actionlog' => new FieldTypes\ObjectRef(ActionLog::class),
            'app' => new FieldTypes\StringType(),
            'action' => new FieldTypes\StringType(),
            'stats_db_reads' => new FieldTypes\IntType(),
            'stats_db_read_time' => new FieldTypes\FloatType(),
            'stats_db_writes' => new FieldTypes\IntType(),
            'stats_db_write_time' => new FieldTypes\FloatType(),
            'stats_code_time' => new FieldTypes\FloatType(),
            'stats_total_time' => new FieldTypes\FloatType(),
            'stats_queries' => new FieldTypes\JSON()
        ));
    }
    
    /**
     * Creates an action metrics log entry
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestMetrics $request the main request metrics
     * @param RunContext $context the context for the app action
     */
    public static function Create(int $level, ObjectDatabase $database, RequestMetrics $request, RunContext $context)
    {
        $obj = static::BaseCreate($database)->SetObject('request',$request);
        
        $input = $context->GetInput();
        $metrics = $context->GetMetrics();
        
        $obj->SetObject('actionlog',$context->GetActionLog())
            ->SetScalar('app',$input->GetApp())
            ->SetScalar('action',$input->GetAction());

        foreach ($metrics->getStats() as $statkey=>$statval)
            $obj->SetScalar("stats_$statkey", $statval);
    
        if ($level >= Config::METRICS_EXTENDED)
        {
            $obj->SetScalar('stats_queries', $metrics->getQueries());
        }
            
    }

    /**
     * Gets the printable client object for this object
     * @return array `{app:string,action:string,stats:{DBStats,queries:[string]}`
     * @see DBStats::getStats()
     */
    public function GetClientObject() : array
    {
        return array(
            'app' => $this->GetScalar('app'),
            'action' => $this->GetScalar('action'),
            'stats' => $this->GetAllScalars('stats')
        );
    }
}
