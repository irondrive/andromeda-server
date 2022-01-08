<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

require_once(ROOT."/Core/Logging/RequestMetrics.php");

/** Log entry representing metrics for a commit */
class CommitMetrics extends StandardObject
{    
    public const IDLength = 20;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_request' => new FieldTypes\ObjectRef(RequestMetrics::class, 'commits'),
            'stats_db_reads' => new FieldTypes\IntType(),
            'stats_db_read_time' => new FieldTypes\FloatType(),
            'stats_db_writes' => new FieldTypes\IntType(),
            'stats_db_write_time' => new FieldTypes\FloatType(),
            'stats_code_time' => new FieldTypes\FloatType(),
            'stats_total_time' => new FieldTypes\FloatType()
        ));
    }
    
    /**
     * Creates a commit metrics log entry
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestMetrics $request the main request metrics
     * @param DBStats $stats stats for the commit
     */
    public static function Create(int $level, ObjectDatabase $database, RequestMetrics $request, DBStats $stats)
    {
        $obj = static::BaseCreate($database)->SetObject('request',$request);

        foreach ($stats->getStats() as $statkey=>$statval)
            $obj->SetScalar("stats_$statkey", $statval);            
    }
    
    /**
     * Gets the printable client object for this object
     * @return array `DBStats`
     * @see DBStats::getStats()
     */
    public function GetClientObject() : array
    {
        return $this->GetAllScalars('stats');
    }
}
