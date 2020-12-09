<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/core/exceptions/ErrorManager.php");

use \Throwable; use Andromeda\Core\JSONEncodingException;

class ErrorLogEntry extends BaseObject
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'time' => null,
            'addr' => null,
            'agent' => null,
            'app' => null,
            'action' => null,
            'code' => null,
            'file' => null,
            'message' => null,
            'trace_basic' => null,
            'trace_full' => null,
            'objects' => null,
            'queries' => null,
            'params' => null,
            'log' => null
         ));
    }
    
    public static function Create(?Main $api, ObjectDatabase $database, Throwable $e) : ErrorLogEntry
    {
        $base = parent::BaseCreate($database);
        
        $data = static::GetDebugData($api, $e, true);
        
        array_walk($data, function($value, $key) use($base) { 
            $base->SetScalar($key, $value); });
        
        return $base;
    }

    public static function GetDebugData(?Main $api, Throwable $e, bool $asJson = false) : array
    {
        $details = ($e instanceof ServerException) ? $e->getDetails() : false; 
        
        $data = array(
            
            'time'=>    time(),
            
            'addr'=>    isset($api) ? $api->GetInterface()->GetAddress() : "",
            'agent'=>   isset($api) ? $api->GetInterface()->GetUserAgent() : "",
            
            'code'=>    $e->getCode(),
            'file'=>    $e->getFile()."(".$e->getLine().")",
            'message'=> $e->getMessage().($details?": ".$details:""),
            
            'app'=>     (isset($api) && $api->GetContext() !== null) ? $api->GetContext()->GetApp() : "",
            'action'=>  (isset($api) && $api->GetContext() !== null) ? $api->GetContext()->GetAction() : "",
            
            'log' =>    isset($api) ? $api->GetDebugLog() : ""
        );
        
        if ($asJson) $data['log'] = Utilities::JSONEncode($data['log']);
        
        $extended = isset($api) && $api->GetConfig() !== null && $api->GetConfig()->GetDebugLogLevel() >= Config::LOG_DEVELOPMENT;
        $sensitive = isset($api) && $api->GetConfig() !== null && $api->GetConfig()->GetDebugLogLevel() >= Config::LOG_SENSITIVE;
        
        if ($extended)
        {
            $data['objects'] = (isset($api) && $api->GetDatabase() !== null) ? $api->GetDatabase()->getLoadedObjects() : "";
            $data['queries'] = (isset($api) && $api->GetDatabase() !== null) ? $api->GetDatabase()->getAllQueries() : "";
            
            if ($asJson)
            {
                $data['objects'] = Utilities::JSONEncode($data['objects']);
                $data['queries'] = Utilities::JSONEncode($data['queries']);
            }
        }
        
        if ($sensitive)
        {
            $data['params'] =  (isset($api) && $api->GetContext() !== null) ? $api->GetContext()->GetParams()->GetClientObject() : "";
            
            if ($asJson)
            {
                $data['params'] = Utilities::JSONEncode($data['params']);
            }
        }
        
        $data['trace_basic'] = $e->getTraceAsString();
        if (!$asJson) $data['trace_basic'] = explode("\n",$data['trace_basic']);
          
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
            
            if ($asJson)
            {               
                try { $data['trace_full'] = Utilities::JSONEncode($data['trace_full']); }
                catch (JSONEncodingException $e) { $data['trace_full'] = "TRACE_JSON_ENCODING_FAILURE"; }
            }
        }
        
        return $data;
    }   
}