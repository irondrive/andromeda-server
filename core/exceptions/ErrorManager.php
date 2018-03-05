<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/interfaces/CLI.php"); use Andromeda\Core\IOFormat\Interfaces\CLI;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Database;
require_once(ROOT."/core/database/_Config.php"); use Andromeda\Core\Database\_Config;


class DuplicateHandlerException extends Exceptions\ServerException  { public $message = "DUPLICATE_ERROR_HANDLER_NAME"; }

use \Throwable; use Andromeda\Core\JSONEncodingException;

class ErrorManager
{
    private $API; private $error_handlers = array();
    
    const DEBUG_DEFAULT = true;
    
    public function SetAPI(Main $api) : void { $this->API = $api; }
    
    public function GetDebug() : bool
    {
        if (isset($this->API) && $this->API->GetServer() !== null) return $this->API->GetDebug();
        else return CLI::isApplicable() || self::DEBUG_DEFAULT;
    }
    
    public function HandleClientException(ClientException $e) : Output
    {
        $this->RunErrorHandlers();
            
        $debug = null; if ($this->GetDebug()) $debug = ErrorManager::GetDebugData($e);
            
        return Output::ClientException($e, $debug);
    }
    
    public function HandleServerException(ServerException $e) : Output
    {
        $this->RunErrorHandlers();
            
        if (isset($this->API) && $this->API->GetServer() !== null && $this->API->GetServer()->GetDebugLogLevel()) $this->Log($e, $e->getDetails());
            
        $debug = null; if ($this->GetDebug()) $debug = ErrorManager::GetDebugData($e, $e->getDetails());
            
        return Output::ServerException($debug);
    }
    
    public function HandleThrowable(Throwable $e) : Output
    {
        $this->RunErrorHandlers();
        
        if (isset($this->API) && $this->API->GetServer() !== null && $this->API->GetServer()->GetDebugLogLevel()) $this->Log($e);
        
        $debug = null; if ($this->GetDebug()) $debug = $this->GetDebugData($e);
        
        return Output::ServerException($debug);
    }
    
    const LOG_BASIC = 1; const LOG_SENSITIVE = 2;
    
    public function GetDebugData(Throwable $e, string $details = "", bool $asJson = false) : array
    {
        $data = array(
            
            'time'=>    time(),
            
            'ipaddr'=>  $_SERVER['REMOTE_ADDR'] ?? 'CLI: '.($_SERVER['COMPUTERNAME']??'').':'.($_SERVER['USERNAME']??''),
            
            'code'=>    $e->getCode(), 
            'file'=>    $e->getFile()."(".$e->getLine().")",
            'message'=> $e->getMessage().($details?": ".$details:""),            
            
            'app'=>     (isset($this->API) && $this->API->GetContext() !== null) ? $this->API->GetContext()->GetApp() : "",
            'action'=>  (isset($this->API) && $this->API->GetContext() !== null) ? $this->API->GetContext()->GetAction() : "",
            
            'trace_basic'=>"",'trace_full'=>"",'objects'=>"",'queries'=>"",'account'=>"",'client'=>"",
        );     
        
        if ($details) $data['details'] = $details;
        
        if ((isset($this->API) && $this->API->GetServer() !== null && $this->API->GetServer()->GetDebugLogLevel() >= self::LOG_SENSITIVE) || self::DEBUG_DEFAULT)
        {
            $data['trace_basic'] = explode("\n",$e->getTraceAsString());   
            
            $data['objects'] = (isset($this->API) && $this->API->GetDatabase() !== null) ? $this->API->GetDatabase()->getLoadedObjects() : "";
            $data['queries'] = (isset($this->API) && $this->API->GetDatabase() !== null) ? $this->API->GetDatabase()->getHistory() : "";

            $data['account'] = ""; $data['client'] = "";
            
            $data['trace_full'] = $e->getTrace();
            
            foreach (array_keys($data['trace_full']) as $key)
            {
                try { Utilities::JSONEncode($data['trace_full'][$key]['args']); }
                catch (JSONEncodingException $e) {
                    if (function_exists('mb_convert_encoding'))
                        $data['trace_full'][$key]['args'] = mb_convert_encoding(print_r($data['trace_full'][$key]['args'],true),'UTF-8');
                        else unset($data['trace_full'][$key]['args']); }
            }   
        }        
        
        if ($asJson)
        {
            $data['objects'] = Utilities::JSONEncode($data['objects']);
            $data['queries'] = Utilities::JSONEncode($data['queries']);
            
            try { $data['trace_full'] = Utilities::JSONEncode($data['trace_full']); } 
            catch (JSONEncodingException $e) { $data['trace_full'] = "TRACE_JSON_ENCODING_FAILURE"; }
        }
        
        return $data;
    }   
     
    public function __construct()
    {
        set_error_handler( function($code,$string,$file,$line){            
            throw new Exceptions\PHPException($code,$string,$file,$line); }, E_ALL); 
    }
    
    private function Log(Throwable $e, string $details = "") : void
    { 
        try 
        {        
            $data = $this->GetDebugData($e, $details, true);
            
            $logdir = $this->API->GetServer() !== null ? $this->API->GetServer()->GetDataDir() : null;
            
            if (isset($this->API) && $logdir !== null && $this->API->GetServer()->GetDebugLog2File()) 
                $this->Log2File($logdir, Utilities::JSONEncode($data));
                
            $fields = ""; $columns = "";            
            foreach (array_keys($data) as $key) { $fields .= ":$key,"; $columns .= "$key,"; }
            $fields = substr($fields, 0, -1); $columns = substr($columns, 0, -1);
            
            try 
            {
                $db = new Database(false);
                
                $db->query("INSERT INTO "._CONFIG::PREFIX."core_log_error ($columns) VALUES ($fields)", $data, false);
                
                $db->commit();
            }
            catch (Throwable $e) { try { if ($logdir !== null) 
                $this->Log2File($logdir, Utilities::JSONEncode($data)); } catch (Throwable $e) { } }
            
            while (($e = $e->getPrevious()) !== null) { $this->Log($e); }
        } 
        catch (Throwable $e) { } 
    }   
    
    private function Log2File(string $datadir, string $data) : void
    {
        file_put_contents("$datadir/error.log", $data."\r\n", FILE_APPEND);
    }
    
    public function AddErrorHandler(string $name, callable $handler) : void
    {
        if (isset($this->error_handlers[$name])) { throw new DuplicateHandlerException(); }
        else { $this->error_handlers[$name] = $handler; }
    }
    
    public function RemoveErrorHandler(string $name) : void
    {
        unset($this->error_handlers[$name]);
    }
    
    public function ResetErrorHandlers() : void
    {
        $this->error_handlers = array();
    }
    
    private function RunErrorHandlers() : void
    {
        if (isset($this->API) && $this->API->GetDatabase() !== null) $this->API->GetDatabase()->rollBack();

        foreach (array_keys($this->error_handlers) as $name)
        {
            try { $this->error_handlers[$name]($this->API); } 
            catch (Throwable $e) { $this->Log($e); }

            unset($this->error_handlers[$name]);
        }
    }
}