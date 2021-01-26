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

/** 
 * The main error handler/manager 
 * 
 * This class handles uncaught exceptions, logging them and converting to a client Output object.
 * This class should only be used internally by the framework.
 */
class ErrorManager extends Singleton
{
    private ?Main $API = null; 
    private IOInterface $interface;

    public function SetAPI(Main $api) : self { $this->API = $api; return $this; }
    
    /** Returns true if the configured debug state is >= the requested level */
    private function GetDebugState(int $minlevel) : bool
    {
        if ($this->API !== null) 
            return $this->API->GetDebugLevel() >= $minlevel;
        else return $this->interface->isPrivileged();
    }
    
    /** Handles a client exception, rolling back the DB, displaying debug data and returning an Output */
    private function HandleClientException(ClientException $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(false);
            
        $debug = null; if ($this->GetDebugState(Config::LOG_DEVELOPMENT)) 
            $debug = ErrorLogEntry::GetDebugData($this->API, $e);
            
        return Output::ClientException($e, $debug);
    }
    
    /** Handles a non-client exception, rolling back the DB, logging debug data and returning an Output */
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollBack(true);

        try { $debug = $this->Log($e, false); }
        catch (\Throwable $e) { $debug = null; }

        return Output::ServerException($debug);
    }
    
    /** Registers PHP error and exception handlers */
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
    
    /** if false, the file-based log encountered an error on the last entry */
    private bool $filelogok = true;
    
    /** if false, the DB-based log encountered an error on the last entry */
    private bool $dblogok = true;
    
    /**
     * Log an exception to file (json) and database
     * 
     * A new database connection is used for the log entry
     * @param \Throwable $e the exception to log
     * @param bool $mainlog if true, display this in the API's message log
     * @return array<string, mixed>|NULL array of debug data or null if not logged
     */
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