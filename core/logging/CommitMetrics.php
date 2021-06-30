<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

require_once(ROOT."/core/logging/RequestMetrics.php");

/** Log entry representing metrics for a commit */
class CommitMetrics extends StandardObject
{    
    public const IDLength = 20;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'request' => new FieldTypes\ObjectRef(RequestMetrics::class, 'commits'),
            'stats__db_reads' => null,
            'stats__db_read_time' => null,
            'stats__db_writes' => null,
            'stats__db_write_time' => null,
            'stats__code_time' => null,
            'stats__total_time' => null
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
            $obj->SetScalar("stats__$statkey", $statval);            
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
