<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/core/exceptions/ErrorManager.php");

use Andromeda\Core\JSONEncodingException;

/** Represents an error log entry in the database */
class ErrorLog extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array(
            'time' => null,     // time of the request
            'addr' => null,     // user address for the request
            'agent' => null,    // user agent for the request
            'app' => null,      // command app
            'action' => null,   // command action
            'code' => null,     // error code
            'file' => null,     // the file with the error
            'message' => null,  // the error message
            'trace_basic' => new FieldTypes\JSON(),  // a basic backtrace
            'trace_full' => new FieldTypes\JSON(),   // full backtrace including all arguments
            'objects' => new FieldTypes\JSON(),  // objects in memory in the database
            'queries' => new FieldTypes\JSON(),  // db queries that were performed
            'params' => new FieldTypes\JSON(),   // all client input parameters
            'log' => new FieldTypes\JSON()       // the custom API log
         );
    }
    
    /** Returns the common command usage for LoadByInput() and CountByInput() */
    public static function GetPropUsage() : string { return "[--mintime float] [--maxtime float] [--code raw] [--addr raw] [--agent raw] [--app alphanum] [--action alphanum] [--message text]"; }
    
    /** Returns the command usage for LoadByInput() */
    public static function GetLoadUsage() : string { return "[--logic and|or] [--limit int] [--offset int] [--desc bool]"; }
    
    /** Returns the command usage for CountByInput() */
    public static function GetCountUsage() : string { return "[--logic and|or]"; }
    
    protected static function GetWhereQuery(ObjectDatabase $database, Input $input) : QueryBuilder
    {
        $q = new QueryBuilder(); $criteria = array();
        
        if ($input->HasParam('maxtime')) $criteria[] = $q->LessThan('time', $input->GetParam('maxtime',SafeParam::TYPE_FLOAT));
        if ($input->HasParam('mintime')) $criteria[] = $q->GreaterThan('time', $input->GetParam('mintime',SafeParam::TYPE_FLOAT));
        
        if ($input->HasParam('code')) $criteria[] = $q->Equals('code', $input->GetParam('code',SafeParam::TYPE_RAW));
        if ($input->HasParam('addr')) $criteria[] = $q->Equals('addr', $input->GetParam('addr',SafeParam::TYPE_RAW));
        if ($input->HasParam('agent')) $criteria[] = $q->Like('agent', $input->GetParam('agent',SafeParam::TYPE_RAW));
        
        if ($input->HasParam('app')) $criteria[] = $q->Equals('app', $input->GetNullParam('app',SafeParam::TYPE_ALPHANUM));
        if ($input->HasParam('action')) $criteria[] = $q->Equals('action', $input->GetNullParam('action',SafeParam::TYPE_ALPHANUM));
        
        if ($input->HasParam('message')) $criteria[] = $q->Like('message', $input->GetParam('message',SafeParam::TYPE_TEXT));
        
        $or = $input->GetOptParam('logic',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
            function($v){ return $v === 'and' || $v === 'or'; }) === 'or'; // default AND
            
        if (!count($criteria)) $criteria[] = ($or ? "FALSE" : "TRUE");
        
        return $q->Where($or ? $q->Or(...$criteria) : $q->And(...$criteria));
    }
    
    /** Returns all error log entries matching the given input */
    public static function LoadByInput(ObjectDatabase $database, Input $input) : array
    {
        $q = static::GetWhereQuery($database, $input);
        
        $q->Limit($input->GetOptParam('limit',SafeParam::TYPE_UINT) ?? 1000);
        
        if ($input->HasParam('offset')) $q->Offset($input->GetParam('offset',SafeParam::TYPE_UINT));
        
        $q->OrderBy('time', $input->GetOptParam('desc',SafeParam::TYPE_BOOL));
        
        return static::LoadByQuery($database, $q);
    }
    
    /** Counts error log entries matching the given input */
    public static function CountByInput(ObjectDatabase $database, Input $input) : int
    {
        return static::CountByQuery($database, static::GetWhereQuery($database, $input));
    }
    
    /** Returns the values of all fields of this error log entry */
    public function GetClientObject() : array 
    { 
        return array_map(function(FieldTypes\Scalar $e){ return $e->GetValue(); }, $this->scalars);
    }
    
    /**
     * Creates a new error log entry object from GetDebugData()
     * @param ObjectDatabase $database referene to the database
     * @param array $debugdata the data from GetDebugData()
     * @return ErrorLog created object
     */
    public static function LogDebugData(ObjectDatabase $database, array $debugdata) : ErrorLog
    {
        $obj = parent::BaseCreate($database);
        
        foreach ($debugdata as $key=>$value)
            $obj->SetScalar($key, $value);
        
        return $obj;
    }
    
    private static function arrayStrings(array &$data) : void
    {
        foreach ($data as &$val)
        {
            if (is_object($val)) 
            {
                $val = method_exists($val,'__toString') ? (string)$val : get_class($val);
            }
            else if (is_array($val)) static::arrayStrings($val);
            
            try { Utilities::JSONEncode(array($val)); }
            catch (JSONEncodingException $e) { $val = base64_encode($val); }
        }
    }

    /**
     * Builds an array of debug data from the given exception
     * 
     * What is logged depends on the configured debug level
     * @param Main $api reference to the main API
     * @param \Throwable $e the exception being debugged
     * @param ?array $debuglog the custom debug log
     * @return array<string, string|mixed> array of debug data
     */
    public static function GetDebugData(?Main $api, \Throwable $e, ?array $debuglog = null) : array
    {
        try
        {
            $data = array(                
                'time'=>    $api ? $api->GetTime() : microtime(true),
                
                'addr'=>    $api ? $api->GetInterface()->GetAddress() : "",
                'agent'=>   $api ? $api->GetInterface()->GetUserAgent() : "",
                
                'code'=>    $e->getCode(),
                'file'=>    $e->getFile()."(".$e->getLine().")",
                'message'=> $e->getMessage(),
            );
            
            $input = ($api && ($context = $api->GetContext()) !== null) ? $context->GetInput() : null;
            
            if ($input !== null)
            {
                $data['app'] = $input->GetApp();
                $data['action'] = $input->GetAction();
            }
    
            $extended = $api && $api->GetDebugLevel() >= Config::ERRLOG_DEVELOPMENT;
            $sensitive = $api && $api->GetDebugLevel() >= Config::ERRLOG_SENSITIVE;
            
            if ($extended)
            {                
                if ($debuglog !== null) $data['log'] = $debuglog;
                
                $data['objects'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getLoadedObjectIDs() : "";
                $data['queries'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getAllQueries() : "";
            }
            
            if ($sensitive)
            {
                $data['params'] = ($input !== null) ? $input->GetParams()->GetClientObject() : "";   
            }
            
            $data['trace_basic'] = explode("\n",$e->getTraceAsString());
              
            if ($extended)
            {
                $data['trace_full'] = $e->getTrace();
                
                foreach (array_keys($data['trace_full']) as $key)
                {
                    if (!array_key_exists('args', $data['trace_full'][$key])) continue;
                    if (!$sensitive) { unset($data['trace_full'][$key]['args']); continue; }
                    
                    static::arrayStrings($data['trace_full'][$key]['args']);
                }
            }
            
            return $data;
        } 
        catch (\Throwable $e2) { return array('message'=>'ErrorLog failed: '.$e2->getMessage().' in '.$e2->getFile()."(".$e2->getLine().")"); }
    }   
}