<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Singleton,Utilities};
require_once(ROOT."/core/exceptions/ErrorLogEntry.php");
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/interfaces/CLI.php"); use Andromeda\Core\IOFormat\Interfaces\CLI;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

class DuplicateHandlerException extends Exceptions\ServerException  { public $message = "DUPLICATE_ERROR_HANDLER_NAME"; }

class ErrorManager extends Singleton
{
    private ?Main $API = null; 
    private IOInterface $interface;

    public function SetAPI(Main $api) : void { $this->API = $api; }
    
    private function GetDebugState() : bool
    {
        if ($this->API !== null && $this->API->GetConfig() !== null) 
            return $this->API->GetDebugState() >= Config::LOG_ERRORS;
        else return CLI::isApplicable();
    }
    
    private function HandleClientException(ClientException $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(false);
            
        $debug = null; if ($this->GetDebugState()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);
            
        return Output::ClientException($e, $debug);
    }
    
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(true);
        
        if ($this->API !== null && $this->API->GetConfig() !== null && $this->API->GetConfig()->GetDebugLogLevel()) $this->Log($e);

        $debug = null; if ($this->GetDebugState()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);

        return Output::ServerException($debug);
    }
    
    public function __construct(IOInterface $interface)
    {
        parent::__construct();        
        
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
    
    public function Log(\Throwable $e) : void
    {
        $logged = false; $logdir = null;
        if ($this->API !== null && $this->API->GetConfig() !== null)
        {
            $logdir = $this->API->GetConfig()->GetDataDir();
            if ($logdir && $this->API->GetConfig()->GetDebugLog2File()) 
            {
                $data = Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e));
                $this->Log2File($logdir, $data); $logged = true; 
            }
        }
        
        try 
        {
            $db = new ObjectDatabase(false); ErrorLogEntry::Create($this->API, $db, $e); $db->saveObjects()->commit(); 
        }
        catch (\Throwable $e2) 
        { 
            if ($logdir !== null) 
            {                
                $this->Log2File($logdir, Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e2, true))); 
                if (!$logged) $this->Log2File($logdir, Utilities::JSONEncode(ErrorLogEntry::GetDebugData($this->API, $e, true))); 
            }
        }
        
        if (($e = $e->getPrevious()) instanceof \Throwable) $this->Log($e);
    }   
    
    private bool $logfileok = true;
    
    private function Log2File(string $datadir, string $data) : void
    {
        if (!$this->logfileok) return;
        try { file_put_contents("$datadir/error.log", $data."\r\n", FILE_APPEND); }
        catch (\Throwable $e) { $this->logfileok = false; $this->HandleThrowable($e); }
    }
}