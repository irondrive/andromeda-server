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

    public function SetAPI(Main $api) : self { $this->API = $api; return $this; }
    
    private function GetDebugState(int $minlevel) : bool
    {
        if ($this->API !== null) 
            return $this->API->GetDebugLevel() >= $minlevel;
        else return CLI::isApplicable();
    }
    
    private function HandleClientException(ClientException $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(false);
            
        $debug = null; if ($this->GetDebugState(Config::LOG_DEVELOPMENT)) 
            $debug = ErrorLogEntry::GetDebugData($this->API, $e);
            
        return Output::ClientException($e, $debug);
    }
    
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(true);

        try { $debug = $this->Log($e, false); }
        catch (\Throwable $e) { $debug = null; }

        return Output::ServerException($debug);
    }
    
    public function __construct(IOInterface $interface)
    {
        parent::__construct();        
        
        $this->interface = $interface;
        
        set_error_handler( function($code,$string,$file,$line){
           throw new Exceptions\PHPError($code,$string,$file,$line); }, E_ALL); 

        set_exception_handler(function(\Throwable $e)
        {
            if ($e instanceof Exceptions\ClientException) 
                $output = $this->HandleClientException($e);
            else $output = $this->HandleThrowable($e);
            
            $this->interface->FinalOutput($output); die();  
        });
    }
    
    public function __destruct() { set_error_handler(function($a,$b,$c,$d){ }, E_ALL); }
    
    private bool $filelogok = true;
    private bool $dblogok = true;
    
    public function Log(\Throwable $e, bool $mainlog = true) : ?array
    {
        if (!$this->GetDebugState(Config::LOG_ERRORS)) return null;
        
        $debug = ErrorLogEntry::GetDebugData($this->API, $e);
        
        if ($this->API !== null && $mainlog) $this->API->PrintDebug($debug);
        
        try
        {
            if ($this->filelogok && $this->API !== null && $this->API->GetConfig() !== null)
            {
                $logdir = $this->API->GetConfig()->GetDataDir();
                if ($logdir && $this->API->GetConfig()->GetDebugLog2File())
                {
                    $data = Utilities::JSONEncode($debug);
                    file_put_contents("$logdir/error.log", $data."\r\n", FILE_APPEND); 
                }
            }
        }
        catch (\Throwable $e2) { $this->filelogok = false; $this->Log($e2); }
        
        try
        {
            if ($this->dblogok) 
            {
                $dblog = $this->API === null || $this->API->GetConfig() === null ||
                    $this->API->GetConfig()->GetDebugLog2DB();
                
                if ($dblog)
                {
                    $db = new ObjectDatabase();
                    ErrorLogEntry::Create($this->API, $db, $e);
                    $db->saveObjects()->commit();
                }
            }
        }
        catch (\Throwable $e2) { $this->dblogok = false; $this->Log($e2); }

        if (($e = $e->getPrevious()) instanceof \Throwable) $this->Log($e);
        
        return $debug;
    }   
}