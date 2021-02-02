<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/core/exceptions/ErrorManager.php");

use Andromeda\Core\JSONEncodingException;

/** Represents an error log entry in the database */
class ErrorLogEntry extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'time' => null,     // time of the request
            'addr' => null,     // user address for the request
            'agent' => null,    // user agent for the request
            'app' => null,      // command app
            'action' => null,   // command action
            'code' => null,     // error code
            'file' => null,     // the file with the error
            'message' => null,  // the error message
            'trace_basic' => null,  // a basic backtrace
            'trace_full' => null,   // full backtrace including all arguments
            'objects' => null,  // objects in memory in the database
            'queries' => null,  // db queries that were performed
            'params' => null,   // all client input parameters
            'log' => null       // the custom API log
         ));
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
        
        $data = static::GetDebugData($api, $e, true);
        
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
     * @param bool $asStrings if true, make sure all values are strings (JSON)
     * @return array<string, string|mixed> array of debug data
     */
    public static function GetDebugData(?Main $api, \Throwable $e, bool $asStrings = false) : array
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
                
                if ($api) { $log = $api->GetDebugLog(); if ($log !== null) $data['log'] =
                    array_filter($log, function($e)use($asStrings){ return !$asStrings || is_string($e); }); }
                
                $data['objects'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getLoadedObjects() : "";
                $data['queries'] = ($api && $api->GetDatabase() !== null) ? $api->GetDatabase()->getAllQueries() : "";
    
                if ($asStrings)
                {
                    if (isset($data['log'])) $data['log'] = Utilities::JSONEncode($data['log']);
                    
                    $data['objects'] = Utilities::JSONEncode($data['objects']);
                    $data['queries'] = Utilities::JSONEncode($data['queries']);
                }
            }
            
            if ($sensitive)
            {
                $data['params'] = ($api && $api->GetContext() !== null) ? $api->GetContext()->GetParams()->GetClientObject() : "";
                
                if ($asStrings)
                {
                    $data['params'] = Utilities::JSONEncode($data['params']);
                }
            }
            
            $data['trace_basic'] = $e->getTraceAsString();
            if (!$asStrings) $data['trace_basic'] = explode("\n",$data['trace_basic']);
              
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
                
                if ($asStrings)
                {               
                    try { $data['trace_full'] = Utilities::JSONEncode($data['trace_full']); }
                    catch (JSONEncodingException $e) { $data['trace_full'] = "TRACE_JSON_ENCODING_FAILURE"; }
                }
            }
            
            return $data;
        } 
        catch (\Throwable $e2) { return array('message'=>'ErrorLogEntry failed: '.$e2->getMessage()); }       
    }   
}