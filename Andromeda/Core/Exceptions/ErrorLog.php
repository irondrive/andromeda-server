<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/Core/Logging/BaseLog.php"); use Andromeda\Core\Logging\BaseLog;

require_once(ROOT."/Core/Exceptions/ErrorManager.php");

/** Represents an error log entry in the database */
final class ErrorLog extends BaseLog
{
    protected const IDLength = 12;
    
    use TableNoChildren;
    
    /** time of the request */
    private FieldTypes\FloatType $time;
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
    private FieldTypes\NullJsonArray $log;
    
    /** @var FieldTypes\BaseField[] our copy of our fields */
    private array $fields;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->time = $fields[] =        new FieldTypes\Date('time');
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
        $this->log = $fields[] =         new FieldTypes\NullJsonArray('log');
        
        $this->fields = $fields;
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
     * Creates an errorLog object from the given exception
     *
     * What is logged depends on the configured debug level
     * @param ?Main $api reference to the main API
     * @param \Throwable $e the exception being debugged
     * @return self new error log entry object
     */
    public static function Create(?Main $api, \Throwable $e) : self
    {
        $obj = new self(null, array('id'=>static::GenerateID()));

        $obj->time->SetValue($api ? $api->GetTime() : microtime(true));
        $obj->addr->SetValue($api ? $api->GetInterface()->GetAddress() : "");
        $obj->agent->SetValue($api ? $api->GetInterface()->GetUserAgent() : "");
        
        $obj->code->SetValue((string)$e->getCode());
        $obj->message->SetValue($e->getMessage());
        $obj->file->SetValue($e->getFile()."(".$e->getLine().")");

        $input = ($api && ($context = $api->GetContext()) !== null) ? $context->GetInput() : null;
        
        if ($input !== null)
        {
            $obj->app->SetValue($input->GetApp());
            $obj->action->SetValue($input->GetAction());
        }
        
        $details = $api && $api->GetDebugLevel() >= Config::ERRLOG_DETAILS;
        $sensitive = $api && $api->GetDebugLevel() >= Config::ERRLOG_SENSITIVE;
        
        if ($details)
        {
            if ($api && $api->HasDatabase())
            {
                $obj->objects->SetArray($api->GetDatabase()->getLoadedObjects());
                $obj->queries->SetArray($api->GetDatabase()->GetInternal()->getAllQueries());
            }
        }
        
        if ($sensitive && $input !== null)
        {
            $params = $input->GetParams()->GetClientObject();
            $obj->params->SetArray(Utilities::arrayStrings($params));
        }
        
        $obj->trace_basic->SetArray(explode("\n",$e->getTraceAsString()));
        
        if ($details)
        {
            $trace_full = $e->getTrace();
            
            foreach ($trace_full as &$val)
            {
                if (!$sensitive) unset($val['args']);
                
                else if (array_key_exists('args', $val))
                    Utilities::arrayStrings($val['args']);
            }
            
            $obj->trace_full->SetArray($trace_full);
        }
        
        return $obj;
    }
    
    /**
     * Sets the supplemental debug log
     * @param int $level debug level for output
     * @param array $debuglog array of debug details
     * @return $this
     */
    public function SetDebugLog(int $level, array $debuglog) : self
    {   
        if ($level >= Config::ERRLOG_DETAILS && $debuglog !== null)
            $this->log->SetArray($debuglog);
        
        return $this;
    }
    
    /**
     * Force-saves this entry to the given database
     * @param ObjectDatabase $database database to save to
     * @return $this
     */
    public function SaveToDatabase(ObjectDatabase $database) : self
    {
        $idf = (new FieldTypes\StringType('id')); $idf->SetValue($this->ID());
        
        $fields = $this->fields; $fields['id'] = $idf;
        
        $database->InsertObject($this, array(self::class => $fields)); return $this;
    }
    
    /**
     * Returns the printable client object of this error log
     * @param ?int $level debug level for output, null for unfiltered
     * @return array<mixed> `{time:float,addr:string,agent:string,code:string,file:string,message:string,app:?string,action:?string,trace_basic:array}`
        if details or null level, add `{trace_full:array,objects:?array,queries:?array,log:?array}`
        if sensitive or null level, add `{params:?array}`
     */
    public function GetClientObject(?int $level = null) : array
    {
        $retval = array(
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue(),
            'code' => $this->code->GetValue(),
            'file' => $this->file->GetValue(),
            'message' => $this->message->GetValue(),
            'app' => $this->app->TryGetValue(),
            'action' => $this->action->TryGetValue(),
            'trace_basic' => $this->trace_basic->GetArray()
        );
        
        $details = $level === null || $level >= Config::ERRLOG_DETAILS;
        $sensitive = $level === null || $level >= Config::ERRLOG_SENSITIVE;

        if ($details)
        {
            $trace_full = $this->trace_full->TryGetArray();
            if (!$sensitive) foreach ($trace_full as &$val) unset($val['args']);
            $retval['trace_full'] = $trace_full;
            
            $retval['objects'] = $this->objects->TryGetArray();
            $retval['queries'] = $this->queries->TryGetArray();
            $retval['log'] = $this->log->TryGetArray();
        }

        if ($sensitive)
        {
            $retval['params'] = $this->params->TryGetArray();
        }
        
        return $retval;
    }
}
