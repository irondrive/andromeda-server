<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, DBStats, FieldTypes, ObjectDatabase, TableTypes};

/** Log entry representing metrics for a commit */
final class CommitMetrics extends BaseObject
{
    use TableTypes\TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** 
     * The request metrics object for this commit 
     * @var FieldTypes\ObjectRefT<RequestMetrics>
     */
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
        $obj = static::BaseCreate($database);
        $obj->requestmet->SetObject($request);
        
        $obj->SetDBStats($metrics);
        
        return $obj;
    }
    
    /** 
     * Loads objects matching the given request metrics 
     * @return array<string, static>
     */
    public static function LoadByRequest(ObjectDatabase $database, RequestMetrics $requestmet) : array
    {
        return $database->LoadObjectsByKey(static::class, 'requestmet', $requestmet->ID());
    }
    
    /**
     * Gets the printable client object for this object
     * @return array<mixed> DBStatsLog
     * @see DBStatsLog::GetDBStatsClientObject()
     */
    public function GetClientObject() : array
    {
        return $this->GetDBStatsClientObject();
    }
}
