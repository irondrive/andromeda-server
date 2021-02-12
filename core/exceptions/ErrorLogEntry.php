<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/core/exceptions/ErrorManager.php");

use Andromeda\Core\JSONEncodingException;

/** Represents an error log entry in the database */
class ErrorLogEntry extends BaseObject
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
    
    /** Returns the command usage for LoadByInput() */
    public static function GetLoadUsage() : string { return "[--mintime int] [--maxtime int] [--code raw] [--addr raw] [--agent raw] [--app alphanum] [--action alphanum] [--logic and|or] [--limit int] [--offset int]"; }
    
    /** Returns all error log entries matching the given input */
    public static function LoadByInput(ObjectDatabase $database, Input $input) : array
    {
        $q = new QueryBuilder(); $criteria = array();
        
        if ($input->HasParam('maxtime')) array_push($criteria, $q->LessThan('time', $input->GetParam('maxtime',SafeParam::TYPE_INT)));        
        if ($input->HasParam('mintime')) array_push($criteria, $q->GreaterThan('time', $input->GetParam('mintime',SafeParam::TYPE_INT)));
        
        if ($input->HasParam('code')) array_push($criteria, $q->Equals('code', $input->GetParam('code',SafeParam::TYPE_RAW)));
        if ($input->HasParam('addr')) array_push($criteria, $q->Equals('addr', $input->GetParam('addr',SafeParam::TYPE_RAW)));  
        if ($input->HasParam('agent')) array_push($criteria, $q->Like('agent', $input->GetParam('agent',SafeParam::TYPE_RAW)));
        
        if ($input->HasParam('app')) array_push($criteria, $q->Equals('app', $input->GetParam('app',SafeParam::TYPE_ALPHANUM)));
        if ($input->HasParam('action')) array_push($criteria, $q->Equals('action', $input->GetParam('action',SafeParam::TYPE_ALPHANUM)));
                
        $or = $input->TryGetParam('logic',SafeParam::TYPE_ALPHANUM,
            function($v){ return $v === 'and' || $v === 'or'; }) === 'or'; // default AND
        
        if (!count($criteria)) array_push($criteria, $or ? "FALSE" : "TRUE");
        
        if ($input->HasParam('limit')) $q->Limit($input->GetParam('limit',SafeParam::TYPE_INT));
        if ($input->HasParam('offset')) $q->Limit($input->GetParam('offset',SafeParam::TYPE_INT));
        
        return static::LoadByQuery($database, $q->Where($or ? $q->OrArr($criteria) : $q->AndArr($criteria)));
    }
    
    /** Returns the values of all fields of this error log entry */
    public function GetClientObject() : array 
    { 
        return array_map(function(FieldTypes\Scalar $e){ return $e->GetValue(); }, $this->scalars);
    }
    
    /**
     * Creates a new error log entry object
     * @param Main $api reference to the main API to get debug data
     * @param ObjectDatabase $database referene to the database
     * @param \Throwable $e reference to the exception this is created for
     * @return ErrorLogEntry
     */
    public static function Create(?Main $api, ObjectDatabase $database, \Throwable $e) : ErrorLogEntry
    {
        $base = parent::BaseCreate($database);
        
        $data = static::GetDebugData($api, $e);
        
        array_walk($data, function($value, $key) use($base) { 
            $base->SetScalar($key, $value); });
        
        return $base;
    }

    /**
     * Builds an array of debug data from the given exception
     * 
     * What is logged depends on the configured debug level
     * @param Main $api reference to the main API
     * @param \Throwable $e the exception being debugged
     * @return array<string, string|mixed> array of debug data
     */
    public static function GetDebugData(?Main $api, \Throwable $e) : array
    {
        try
        {
            $data = array(
                
                'time'=>    $api ? $api->GetTime() : time(),
                
                'addr'=>    $api ? $api->GetInterface()->GetAddress() : "",
                'agent'=>   $api ? $api->GetInterface()->GetUserAgent() : "",
                
                'code'=>    $e->getCode(),
                'file'=>    $e->getFile()."(".$e->getLine().")",
                'message'=> $e->getMessage(),
                
                'app'=>     ($api && $api->GetContext() !== null) ? $api->GetContext()->GetApp() : "",
                'action'=>  ($api && $api->GetContext() !== null) ? $api->GetContext()->GetAction() : "",
            );
    
            $extended = $api && $api->GetDebugLevel() >= Config::LOG_DEVELOPMENT;
            $sensitive = $api && $api->GetDebugLevel() >= Config::LOG_SENSITIVE;
            
            if ($extended)
            {
                
                if ($api) { $log = $api->GetDebugLog(); if ($log !== null) $data['log'] = $log; }
                
                $data['objects'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getLoadedObjects() : "";
                $data['queries'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getAllQueries() : "";
            }
            
            if ($sensitive)
            {
                $data['params'] = ($api && $api->GetContext() !== null) ? $api->GetContext()->GetParams()->GetClientObject() : "";                

            }
            
            $data['trace_basic'] = explode("\n",$e->getTraceAsString());
              
            if ($extended)
            {
                $data['trace_full'] = $e->getTrace();
                
                foreach (array_keys($data['trace_full']) as $key)
                {
                    if (!array_key_exists('args', $data['trace_full'][$key])) continue;
                    if (!$sensitive) { unset($data['trace_full'][$key]['args']); continue; }
                    
                    try { Utilities::JSONEncode($data['trace_full'][$key]['args']); }
                    catch (JSONEncodingException $e) { 
                        $data['trace_full'][$key]['args'] = base64_encode(print_r($data['trace_full'][$key]['args'],true)); }
                }
            }
            
            return $data;
        } 
        catch (\Throwable $e2) { return array('message'=>'ErrorLogEntry failed: '.$e2->getMessage()); }       
    }   
}