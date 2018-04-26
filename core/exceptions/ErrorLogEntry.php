<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

use \Throwable; use Andromeda\Core\JSONEncodingException;

class ErrorLogEntry extends BaseObject
{    
    public static function Create(?Main $api, ObjectDatabase $database, Throwable $e) : ErrorLogEntry
    {
        $base = parent::BaseCreate($database);
        
        $data = self::GetDebugData($api, $e, true);
        
        array_walk($data, function($value, $key) use ($base) { 
            $base->SetScalar($key, $value); });
        
        return $base;
    }

    public static function GetDebugData(?Main $api, Throwable $e, bool $asJson = false) : array
    {
        $details = ($e instanceof ServerException) ? $e->getDetails() : false; 
        
        $data = array(
            
            'time'=>    time(),
            
            'ipaddr'=>  isset($api) ? $api->GetInterface()->getAddress() : "",
            'agent'=>   isset($api) ? $api->GetInterface()->getUserAgent() : "",
            
            'code'=>    $e->getCode(),
            'file'=>    $e->getFile()."(".$e->getLine().")",
            'message'=> $e->getMessage().($details?": ".$details:""),
            
            'app'=>     (isset($api) && $api->GetContext() !== null) ? $api->GetContext()->GetApp() : "",
            'action'=>  (isset($api) && $api->GetContext() !== null) ? $api->GetContext()->GetAction() : "",
        );
        
        if ((isset($api) && $api->GetConfig() !== null) ? ($api->GetConfig()->GetDebugLogLevel() >= Config::LOG_SENSITIVE) : ErrorManager::DEBUG_FULL_DEFAULT)
        {
            $data['trace_basic'] = $e->getTraceAsString();
            if (!$asJson) $data['trace_basic'] = explode("\n",$data['trace_basic']);
            
            $data['objects'] = (isset($api) && $api->GetDatabase() !== null) ? $api->GetDatabase()->getLoadedObjects() : "";
            $data['queries'] = (isset($api) && $api->GetDatabase() !== null) ? $api->GetDatabase()->getHistory() : "";
            
            $data['trace_full'] = $e->getTrace();
            
            foreach (array_keys($data['trace_full']) as $key)
            {
                if (!array_key_exists('args', $data['trace_full'][$key])) continue;
                try { Utilities::JSONEncode($data['trace_full'][$key]['args']); }
                catch (JSONEncodingException $e) {
                    if (function_exists('mb_convert_encoding'))
                        $data['trace_full'][$key]['args'] = mb_convert_encoding(print_r($data['trace_full'][$key]['args'],true),'UTF-8');
                    else unset($data['trace_full'][$key]['args']); }
            }
            
            if ($asJson)
            {
                $data['objects'] = Utilities::JSONEncode($data['objects']);
                $data['queries'] = Utilities::JSONEncode($data['queries']);
                
                try { $data['trace_full'] = Utilities::JSONEncode($data['trace_full']); }
                catch (JSONEncodingException $e) { $data['trace_full'] = "TRACE_JSON_ENCODING_FAILURE"; }
            }
        }
        
        return $data;
    }   
}