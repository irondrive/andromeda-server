<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Singleton,Utilities};
require_once(ROOT."/core/exceptions/ErrorLog.php");
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

/** Internal-only exception used for getting a backtrace */
class BreakpointException extends Exceptions\ServerException { public $message = "BREAKPOINT_EXCEPTION"; }

/** 
 * The main error handler/manager 
 * 
 * This class handles uncaught exceptions, logging them and converting to a client Output object.
 * Application code can use LogException() when an exception is caught but still needs to be logged.
 */
class ErrorManager extends Singleton
{
    private ?Main $API = null; 
    
    private IOInterface $interface;

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
        if ($this->API !== null) $this->API->rollback($e);
            
        $debug = null; if ($this->GetDebugState(Config::ERRLOG_DEVELOPMENT)) 
            $debug = ErrorLog::GetDebugData($this->API, $e, $this->GetDebugLog());
            
        return Output::ClientException($e, $debug);
    }
    
    /** Handles a non-client exception, rolling back the DB, logging debug data and returning an Output */
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollback($e);

        try { $debug = $this->LogException($e, false); }
        catch (\Throwable $e) { $debug = null; }

        return Output::ServerException($debug);
    }
    
    /** Registers PHP error and exception handlers */
    public function __construct(Main $api, IOInterface $interface)
    {
        parent::__construct();        
        
        $this->API = $api; $this->interface = $interface;
        
        set_error_handler( function($code,$string,$file,$line){
           throw new Exceptions\PHPError($code,$string,$file,$line); }, E_ALL); 

        set_exception_handler(function(\Throwable $e)
        {
            if ($e instanceof Exceptions\ClientException) 
            {
                $output = $this->HandleClientException($e);
                
                $this->API->FinalizeOutput($output, true);
            }
            else $output = $this->HandleThrowable($e);           
            
            $this->interface->WriteOutput($output); die();  
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
    public function LogException(\Throwable $e, bool $mainlog = true) : ?array
    {
        if (!$this->GetDebugState(Config::ERRLOG_ERRORS)) return null;

        $debug = ErrorLog::GetDebugData($this->API, $e, !$mainlog ? $this->GetDebugLog() : null);
        
        if ($this->API !== null && $mainlog) $this->LogDebug($debug);
        
        if ($e instanceof ClientException) return $debug;
        
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
        catch (\Throwable $e2) { $this->filelogok = false; $this->LogException($e2); }
        
        try
        {
            if ($this->dblogok) 
            {
                $dblog = $this->API === null || $this->API->GetConfig() === null ||
                    $this->API->GetConfig()->GetDebugLog2DB();
                
                if ($dblog)
                {
                    $db = new ObjectDatabase();

                    $log = ErrorLog::LogDebugData($db, $debug);
                    
                    $log->Save(); $db->commit();
                }
            }
        }
        catch (\Throwable $e2) { $this->dblogok = false; $this->LogException($e2); }

        if (($e = $e->getPrevious()) instanceof \Throwable) $this->LogException($e);
        
        return $debug;
    }
    
    private array $debuglog = array();
    
    /** Adds an entry to the custom debug log, saved with exceptions */
    public function LogDebug($data) : self { $this->debuglog[] = $data; return $this; }
    
    /** Returns the debug log if allowed by the debug state, else null */
    public function GetDebugLog() : ?array { return $this->GetDebugState(Config::ERRLOG_DEVELOPMENT) ? $this->debuglog : null; }
    
    /** Creates an exception and logs it to the main error log (to get a backtrace) */
    public function LogBreakpoint() : self { return $this->LogDebug((new BreakpointException())->getTraceAsString()); }
    
}