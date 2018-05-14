<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/ErrorLogEntry.php");
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/interfaces/CLI.php"); use Andromeda\Core\IOFormat\Interfaces\CLI;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/Config.php"); use Andromeda\Core\Database\Config;

class DuplicateHandlerException extends Exceptions\ServerException  { public $message = "DUPLICATE_ERROR_HANDLER_NAME"; }

class ErrorManager
{
    private $API; private $interface;
    
    public function SetAPI(Main $api) : void { $this->API = $api; }
    
    public function GetDebug() : bool
    {
        if (isset($this->API) && $this->API->GetConfig() !== null) 
            return $this->API->GetDebug();
        else return CLI::isApplicable();
    }
    
    public function HandleClientException(ClientException $e) : Output
    {
        if (isset($this->API)) $this->API->rollBack();
            
        $debug = null; if ($this->GetDebug()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);
            
        return Output::ClientException($e, $debug);
    }
    
    public function HandleThrowable(\Throwable $e) : Output
    {
        if (isset($this->API)) $this->API->rollBack();
        
        if (isset($this->API) && $this->API->GetConfig() !== null && $this->API->GetConfig()->GetDebugLogLevel()) $this->Log($e);
        
        $debug = null; if ($this->GetDebug()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);
        
        return Output::ServerException($debug);
    }
    
    public function __construct(IOInterface $interface)
    {
        $this->interface = $interface;
        
        set_error_handler( function($code,$string,$file,$line){
           throw new Exceptions\PHPException($code,$string,$file,$line); }, E_ALL); 
        
        set_exception_handler(function(\Throwable $e)
        {
            if ($e instanceof Exceptions\ClientException)
                $output = $this->HandleClientException($e);
            else $output = $this->HandleThrowable($e);
            
            $this->interface->WriteOutput($output); die();  
        });
    }
    
    public function __destruct() { set_error_handler(function($a,$b,$c,$d){ }, E_ALL); }
    
    private function Log(\Throwable $e) : void
    {         
        $logged = false; $logdir = null;
        if (isset($this->API) && $this->API->GetConfig() !== null)
        {
            $logdir = $this->API->GetConfig()->GetDataDir();
            if ($this->API->GetConfig()->GetDebugLog2File()) 
            {
                $data = Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e));
                $this->Log2File($logdir, $data); $logged = true; 
            }
        }
        
        try 
        {
            $db = new ObjectDatabase(false); $entry = ErrorLogEntry::Create($this->API, $db, $e); $db->commit();
        }
        catch (\Throwable $e2) { 
            if ($logdir !== null) {                
                $this->Log2File($logdir, Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e2))); 
                if (!$logged) $this->Log2File($logdir, Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e))); 
            }
        }
        
        while (($e = $e->getPrevious()) !== null) { $this->Log($e); }
    }   
    
    private $logfileok = true;
    
    private function Log2File(string $datadir, string $data) : void
    {
        if (!$this->logfileok) return;
        try { file_put_contents("$datadir/error.log", $data."\r\n", FILE_APPEND); }
        catch (\Throwable $e) { $this->logfileok = false; $this->HandleThrowable($e); }
    }
}