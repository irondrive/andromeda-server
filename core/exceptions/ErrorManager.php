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
    private $API; private $interface; private $error_handlers = array();
    
    const DEBUG_DEFAULT = true; const DEBUG_FULL_DEFAULT = true;
    
    public function SetAPI(Main $api) : void { $this->API = $api; }
    
    public function GetDebug() : bool
    {
        if (isset($this->API) && $this->API->GetServer() !== null) return $this->API->GetDebug();
        else return CLI::isApplicable() || self::DEBUG_DEFAULT;
    }
    
    public function HandleClientException(ClientException $e) : Output
    {
        $this->RunErrorHandlers();
            
        $debug = null; if ($this->GetDebug()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);
            
        return Output::ClientException($e, $debug);
    }
    
    public function HandleThrowable(\Throwable $e) : Output
    {
        $this->RunErrorHandlers();
        
        if (isset($this->API) && $this->API->GetServer() !== null && $this->API->GetServer()->GetDebugLogLevel()) $this->Log($e);
        
        $debug = null; if ($this->GetDebug()) $debug = ErrorLogEntry::GetDebugData($this->API, $e);
        
        return Output::ServerException($debug);
    }
    
    public function __construct(IOInterface $interface)
    {
        $this->interface = $interface;
        
        set_error_handler( function($code,$string,$file,$line){ 
            throw new Exceptions\PHPException($code,$string,$file,$line); }, E_ALL); 
        
        set_exception_handler( function(\Throwable $e)
        {
            if ($e instanceof Exceptions\ClientException)
            {
                $output = $this->HandleClientException($e);
                
                if (isset($this->API) && $this->API->GetDebug()) 
                    $output->SetMetrics($this->API->GetMetrics(false));
            }
            else
            {
                $output = $this->HandleThrowable($e);
            }
            
            $this->interface->WriteOutput($output);
        });
    }
    
    private function Log(\Throwable $e) : void
    {         
        $logged = false; $logdir = null;
        if (isset($this->API) && $this->API->GetServer() !== null)
        {
            $logdir = $this->API->GetServer()->GetDataDir();
            if ($this->API->GetServer()->GetDebugLog2File()) 
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
        if (isset($this->API) && $this->API->GetDatabase() !== null) 
            $this->API->GetDatabase()->rollBack();

        foreach (array_keys($this->error_handlers) as $name)
        {
            try { $this->error_handlers[$name]($this->API); } 
            catch (\Throwable $e) { $this->Log($e); }

            unset($this->error_handlers[$name]);
        }
    }
}