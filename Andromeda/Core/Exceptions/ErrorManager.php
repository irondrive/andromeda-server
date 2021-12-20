<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\{Singleton,Utilities};
require_once(ROOT."/Core/Exceptions/ErrorLog.php");
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

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
            
        $debug = null; if ($this->GetDebugState(Config::ERRLOG_DETAILS)) 
            $debug = ErrorLog::GetDebugData($this->API, $e, $this->GetDebugLog());
            
        return Output::ClientException($e, $debug);
    }
    
    /** Handles a non-client exception, rolling back the DB, logging debug data and returning an Output */
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollback($e);

        try { $debug = $this->LogException($e, false); }
        catch (\Throwable $e) { $debug = null; }

        return Output::Exception($debug);
    }
    
    /** Registers PHP error and exception handlers */
    public function __construct(IOInterface $interface)
    {
        parent::__construct();        
        
        $this->interface = $interface;
        
        set_error_handler( function(int $code, string $string, string $file, int $line){
           throw new Exceptions\PHPError($code,$string,$file,$line); }, E_ALL); 

        set_exception_handler(function(\Throwable $e)
        {
            if ($e instanceof ClientException) 
            {
                $output = $this->HandleClientException($e);
                
                $this->API->FinalizeOutput($output, true);
            }
            else $output = $this->HandleThrowable($e);
            
            $this->interface->WriteOutput($output); die();  
        });
    }
    
    public function __destruct() { set_error_handler(null, E_ALL); }
    
    public function SetAPI(Main $api) : self { $this->API = $api; return $this; }
    
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
        
        $config = ($this->API !== null) ? $this->API->TryGetConfig() : null;
        
        try
        {
            if ($this->filelogok && $config !== null && $config->GetDebugLog2File())
            {
                if (($logdir = $config->GetDataDir()) !== null)
                {
                    $data = Utilities::JSONEncode($debug);
                    file_put_contents("$logdir/error.log", $data."\r\n", FILE_APPEND); 
                }
            }
        }
        catch (\Throwable $e2) { $this->filelogok = false; $this->LogException($e2); }
        
        try
        {
            if ($this->dblogok && $config !== null && $config->GetDebugLog2DB()) 
            {
                $db = new ObjectDatabase();

                $log = ErrorLog::LogDebugData($db, $debug);
                
                $log->Save(); $db->commit();
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
    public function GetDebugLog() : ?array { return $this->GetDebugState(Config::ERRLOG_DETAILS) ? $this->debuglog : null; }
    
    /** Creates an exception and logs it to the main error log (to get a backtrace) */
    public function LogBreakpoint() : self { return $this->LogDebug((new BreakpointException())->getTraceAsString()); }
    
}