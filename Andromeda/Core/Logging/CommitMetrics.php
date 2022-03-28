<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;

require_once(ROOT."/Core/Logging/RequestMetrics.php");
require_once(ROOT."/Core/Logging/DBStatsLog.php");

/** Log entry representing metrics for a commit */
final class CommitMetrics extends BaseObject
{
    use TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** The request metrics object for this commit */
    private FieldTypes\ObjectRefT $requestmet;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->requestmet = $fields[] = new FieldTypes\ObjectRefT(RequestMetrics::class,'requestmet');

        $this->RegisterFields($fields, self::class);
        $this->DBStatsCreateFields();
        
        parent::CreateFields();
    }
    
    /**
     * Creates a commit metrics log entry
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestMetrics $request the main request metrics
     * @param DBStats $metrics stats for the commit
     */
    public static function Create(int $level, ObjectDatabase $database, RequestMetrics $request, DBStats $metrics) : self
    {
        $obj = parent::BaseCreate($database);
        $obj->requestmet->SetObject($request);
        
        $obj->SetDBStats($metrics);
        
        return $obj;
    }
    
    /** Loads objects matching the given request metrics */
    public static function LoadByRequest(ObjectDatabase $database, RequestMetrics $requestmet) : array
    {
        return $database->LoadObjectsByKey(static::class, 'requestmet', $requestmet->ID());
    }
    
    /**
     * Gets the printable client object for this object
     * @return array DBStatsLog
     * @see DBStatsLog::GetDBStatsClientObject()
     */
    public function GetClientObject() : array
    {
        return $this->GetDBStatsClientObject();
    }
}
