<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{Config, RunContext};
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, TableTypes};

/** Log entry representing metrics for an app action */
class ActionMetrics extends BaseObject
{
    use TableTypes\TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** 
     * The request metrics object for this action 
     * @var FieldTypes\ObjectRefT<RequestMetrics>
     */
    private FieldTypes\ObjectRefT $requestmet;
    /** 
     * The action log corresponding to this metrics 
     * @var FieldTypes\NullObjectRefT<ActionLog>
     */
    private FieldTypes\NullObjectRefT $actionlog;
    /** The command action app name */
    private FieldTypes\StringType $app;
    /** The command action name */
    private FieldTypes\StringType $action;
    /** 
     * Queries performed during this action 
     * @var FieldTypes\NullJsonArray<array<mixed>>
     */
    private FieldTypes\NullJsonArray $queries;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $fields[] = $this->requestmet = new FieldTypes\ObjectRefT(RequestMetrics::class,'requestmet');
        $fields[] = $this->actionlog = new FieldTypes\NullObjectRefT(ActionLog::class,'actionlog');
        
        $fields[] = $this->app =     new FieldTypes\StringType('app');
        $fields[] = $this->action =  new FieldTypes\StringType('action');
        $fields[] = $this->queries = new FieldTypes\NullJsonArray('queries');

        $this->RegisterFields($fields, self::class);
        $this->DBStatsCreateFields();
        
        parent::CreateFields();
    }
    
    public static function GetUniqueKeys() : array
    {
        return array(self::class => array('id','actionlog'));
    }

    /**
     * Creates an action metrics log entry
     * @param int $level logging level
     * @param ObjectDatabase $database database reference
     * @param RequestMetrics $request the main request metrics
     * @param RunContext $context the context for the app action
     */
    public static function Create(int $level, ObjectDatabase $database, RequestMetrics $request, RunContext $context) : self
    {
        $obj = static::BaseCreate($database);
        $obj->requestmet->SetObject($request);
        $obj->actionlog->SetObject($context->GetActionLog());
        
        $metrics = $context->GetMetrics();
        $obj->SetDBStats($metrics);

        $input = $context->GetInput();
        $obj->app->SetValue($input->GetApp());
        $obj->action->SetValue($input->GetAction());

        if ($level >= Config::METRICS_EXTENDED)
            $obj->queries->SetArray($metrics->getQueries());
        
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
     * @return array<mixed> DBStatsLog + `{app:string,action:string,queries:[{time:float,query:string}]}`
     * @see DBStatsLog::GetDBStatsClientObject()
     */
    public function GetClientObject() : array
    {
        $retval = $this->GetDBStatsClientObject();

        $retval['app'] = $this->app->GetValue();
        $retval['action'] = $this->action->GetValue();
        
        if (($queries = $this->queries->TryGetArray()) !== null)
            $retval['queries'] = $queries;
        
        return $retval;
    }
}
