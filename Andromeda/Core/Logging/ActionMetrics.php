<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/RunContext.php"); use Andromeda\Core\RunContext;

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;

require_once(ROOT."/Core/Logging/RequestMetrics.php");
require_once(ROOT."/Core/Logging/DBStatsLog.php");

/** Log entry representing metrics for an app action */
final class ActionMetrics extends BaseObject
{
    use TableNoChildren;
    use DBStatsLog;
    
    protected const IDLength = 20;
    
    /** @var FieldTypes\ObjectRefT<RequestMetrics> The request metrics object for this action */
    private FieldTypes\ObjectRefT $requestmet;
    /** @var FieldTypes\NullObjectRefT<ActionLog> The action log corresponding to this metrics */
    private FieldTypes\NullObjectRefT $actionlog;
    /** The command action app name */
    private FieldTypes\StringType $app;
    /** The command action name */
    private FieldTypes\StringType $action;
    /** Queries performed during this action */
    private FieldTypes\NullJsonArray $queries;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->requestmet = $fields[] = new FieldTypes\ObjectRefT(RequestMetrics::class,'requestmet');
        $this->actionlog = $fields[] = new FieldTypes\NullObjectRefT(ActionLog::class,'actionlog');
        
        $this->app = $fields[] =     new FieldTypes\StringType('app');
        $this->action = $fields[] =  new FieldTypes\StringType('action');
        $this->queries = $fields[] = new FieldTypes\NullJsonArray('queries');

        $this->RegisterFields($fields, self::class);
        $this->DBStatsCreateFields();
        
        parent::CreateFields();
    }
    
    protected static function AddUniqueKeys(array& $keymap) : void
    {
        $keymap[self::class] = array('actionlog');
        
        parent::AddUniqueKeys($keymap);
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
        $obj = parent::BaseCreate($database);
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
