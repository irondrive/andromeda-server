<?php declare(strict_types=1); namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/Core/Logging/BaseLog.php"); use Andromeda\Core\Logging\BaseLog;

require_once(ROOT."/Core/Exceptions/ErrorInfo.php");

/** Represents an error log entry in the database */
final class ErrorLog extends BaseLog
{
    protected const IDLength = 12;
    
    use TableNoChildren;
    
    /** time of the error */
    private FieldTypes\Timestamp $time;
    /** user address for the request */
    private FieldTypes\StringType $addr;
    /** user agent for the request */
    private FieldTypes\StringType $agent;
    /** command app */
    private FieldTypes\NullStringType $app;
    /** command action */
    private FieldTypes\NullStringType $action;
    /** error code string */
    private FieldTypes\StringType $code;
    /** the file with the error */
    private FieldTypes\StringType $file;
    /** the error message */
    private FieldTypes\StringType $message;
    /** a basic backtrace */
    private FieldTypes\JsonArray $trace_basic;
    /** full backtrace including all arguments */
    private FieldTypes\NullJsonArray $trace_full;
    /** objects in memory in the database */
    private FieldTypes\NullJsonArray $objects;
    /** db queries that were performed */
    private FieldTypes\NullJsonArray $queries;
    /** all client input parameters */
    private FieldTypes\NullJsonArray $params;
    /** the custom API log */
    private FieldTypes\NullJsonArray $hints;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->time = $fields[] =        new FieldTypes\Timestamp('time');
        $this->addr = $fields[] =        new FieldTypes\StringType('addr');
        $this->agent = $fields[] =       new FieldTypes\StringType('agent');
        $this->app = $fields[] =         new FieldTypes\NullStringType('app');
        $this->action = $fields[] =      new FieldTypes\NullStringType('action');
        $this->code = $fields[] =        new FieldTypes\StringType('code');
        $this->file = $fields[] =        new FieldTypes\StringType('file');
        $this->message = $fields[] =     new FieldTypes\StringType('message');
        $this->trace_basic = $fields[] = new FieldTypes\JsonArray('trace_basic');
        $this->trace_full = $fields[] =  new FieldTypes\NullJsonArray('trace_full');
        $this->objects = $fields[] =     new FieldTypes\NullJsonArray('objects');
        $this->queries = $fields[] =     new FieldTypes\NullJsonArray('queries');
        $this->params = $fields[] =      new FieldTypes\NullJsonArray('params');
        $this->hints = $fields[] =       new FieldTypes\NullJsonArray('hints');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns the common command usage for LoadByParams() and CountByParams() */
    public static function GetPropUsage() : string { return "[--mintime float] [--maxtime float] [--code utf8] [--addr utf8] [--agent utf8] [--app alphanum] [--action alphanum] [--message utf8] [--asc bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $join = true) : array
    {
        $criteria = array();
        
        if ($params->HasParam('maxtime')) $criteria[] = $q->LessThan('time', $params->GetParam('maxtime')->GetFloat());
        if ($params->HasParam('mintime')) $criteria[] = $q->GreaterThan('time', $params->GetParam('mintime')->GetFloat());
        
        if ($params->HasParam('code')) $criteria[] = $q->Equals('code', $params->GetParam('code')->GetUTF8String());
        if ($params->HasParam('addr')) $criteria[] = $q->Equals('addr', $params->GetParam('addr')->GetUTF8String());
        if ($params->HasParam('agent')) $criteria[] = $q->Like('agent', $params->GetParam('agent')->GetUTF8String());
        
        if ($params->HasParam('app')) $criteria[] = $q->Equals('app', $params->GetParam('app')->GetNullAlphanum());
        if ($params->HasParam('action')) $criteria[] = $q->Equals('action', $params->GetParam('action')->GetNullAlphanum());
        
        if ($params->HasParam('message')) $criteria[] = $q->Like('message', $params->GetParam('message')->GetUTF8String());
        
        $q->OrderBy("time", !$params->GetOptParam('asc',false)->GetBool()); // always sort by time, default desc
        
        return $criteria;
    }

    /**
     * Create a new DB error log entry from an ErrorInfo
     * @param ObjectDatabase $database database reference
     * @param ErrorInfo $info error log info to copy
     * @return static new ErrorLog database object
     */
    public static function Create(ObjectDatabase $database, ErrorInfo $info) : self
    {
        $obj = static::BaseCreate($database);
        
        $obj->time->SetValue($info->GetTime());
        $obj->addr->SetValue($info->GetAddr());
        $obj->agent->SetValue($info->GetAgent());
        $obj->app->SetValue($info->TryGetApp());
        $obj->action->SetValue($info->TryGetAction());
        $obj->code->SetValue($info->GetCode());
        $obj->file->SetValue($info->GetFile());
        $obj->message->SetValue($info->GetMessage());
        $obj->trace_basic->SetArray($info->GetTraceBasic());
        $obj->trace_full->SetArray($info->TryGetTraceFull());
        $obj->objects->SetArray($info->TryGetObjects());
        $obj->queries->SetArray($info->TryGetQueries());
        $obj->params->SetArray($info->TryGetParams());
        $obj->hints->SetArray($info->TryGetHints());
        
        return $obj;
    }

    /**
     * Returns the printable client object of this error log
     * @return array<mixed> `{time:float,addr:string,agent:string,code:string,file:string,message:string,app:?string,action:?string,
          trace_basic:array,trace_full:array,objects:?array,queries:?array,params:?array,hints:?array}`
     */
    public function GetClientObject() : array
    {
        $retval = array(
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue(),
            'app' => $this->app->TryGetValue(),
            'action' => $this->action->TryGetValue(),
            'code' => $this->code->GetValue(),
            'file' => $this->file->GetValue(),
            'message' => $this->message->GetValue(),
            'trace_basic' => $this->trace_basic->GetArray(),
            'trace_full' => $this->trace_full->TryGetArray(),
            'objects' => $this->objects->TryGetArray(),
            'queries' => $this->queries->TryGetArray(),
            'params' => $this->params->TryGetArray(),
            'hints' => $this->hints->TryGetArray()
        );

        return $retval;
    }
}
