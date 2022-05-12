<?php namespace Andromeda\Core\Exceptions; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\{Singleton,Utilities};
require_once(ROOT."/Core/Exceptions/ErrorLog.php");
require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;

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
    
    /** Registers PHP error and exception handlers */
    public function __construct(IOInterface $interface)
    {
        parent::__construct();
        
        $this->interface = $interface;
        
        set_error_handler( function(int $code, string $msg, string $file, int $line){
            throw new Exceptions\PHPError($code,$msg,$file,$line); }, E_ALL);
            
        set_exception_handler(function(\Throwable $e)
        {
            if ($e instanceof ClientException)
            {
                $output = $this->HandleClientException($e);
                
                $this->API->FinalMetrics($output, true);
            }
            else $output = $this->HandleThrowable($e);
            
            $this->interface->WriteOutput($output); die();
        });
        
        ini_set('assert.active','1');
        ini_set('assert.exception','1');
    }
    
    public function __destruct()
    {
        set_error_handler(null, E_ALL);
        set_exception_handler(null);
    }
    
    public function SetAPI(Main $api) : self { $this->API = $api; return $this; }
    
    /** Returns the debug level for internal logging */
    private function GetDebugLogLevel() : int
    {
        return $this->API ? $this->API->GetDebugLevel() : Config::ERRLOG_ERRORS;
    }
    
    /** Returns the debug level to be show in output */
    private function GetDebugOutputLevel() : int
    {
        return $this->API ? $this->API->GetDebugLevel(true) : $this->interface->GetDebugLevel();
    }

    /** Handles a client exception, rolling back the DB, 
     * displaying debug data and returning an Output */
    private function HandleClientException(ClientException $e) : Output
    {
        if ($this->API !== null) $this->API->rollback($e);
            
        $debug = null; if ($this->GetDebugOutputLevel() >= Config::ERRLOG_DETAILS) 
        {
            $debug = ErrorLog::Create($this->API, $e)
                ->SetDebugLog($this->GetDebugLogLevel(), $this->GetDebugLog())
                ->GetClientObject($this->GetDebugOutputLevel());
        }
            
        return Output::ClientException($e, $debug);
    }
    
    /** Handles a non-client exception, rolling back the DB, 
     * logging debug data and returning an Output */
    private function HandleThrowable(\Throwable $e) : Output
    {
        if ($this->API !== null) $this->API->rollback($e);

        $debug = null; try 
        {
            if (($errlog = $this->LogException($e, false)) !== null) 
                $debug = $errlog->GetClientObject($this->GetDebugOutputLevel());
        }
        catch (\Throwable $e2) 
        { 
            if ($this->GetDebugOutputLevel() >= Config::ERRLOG_ERRORS)
            {
                $debug = array('message'=>
                    'ErrorLog failed: '.$e2->getMessage().' in '.
                    $e2->getFile()."(".$e2->getLine().")"); 
            }
        }
        
        return Output::Exception($debug);
    }

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
     * @return ?ErrorLog error log object or null if not logged
     */
    public function LogException(\Throwable $e, bool $mainlog = true) : ?ErrorLog
    {
        $loglevel = $this->GetDebugLogLevel(); if ($loglevel < Config::ERRLOG_ERRORS) return null;

        $errlog = ErrorLog::Create($this->API, $e);
        $errlog->SetDebugLog($loglevel, $this->GetDebugLog());
        
        $debug = $errlog->GetClientObject($loglevel);
        if ($mainlog) $this->LogDebugInfo($debug);
        
        if ($e instanceof ClientException) return $errlog;
        
        $config = ($this->API !== null) ? $this->API->TryGetConfig() : null;
        
        try // save to file
        {
            if ($this->filelogok && $config !== null && $config->GetDebugLog2File())
            {
                if (($logdir = $config->GetDataDir()) !== null)
                {
                    file_put_contents("$logdir/error.log", 
                        Utilities::JSONEncode($debug)."\r\n", FILE_APPEND); 
                }
            }
        }
        catch (\Throwable $e2) 
        { 
            $this->filelogok = false; $this->LogException($e2);
            $errlog->SetDebugLog($loglevel, $this->GetDebugLog()); // update from e2
        }
        
        try // save to database
        {
            if ($this->dblogok && $config !== null && $config->GetDebugLog2DB()) 
            {
                $db2 = $this->API->InitDatabase();
                
                $errlog->SaveToDatabase($db2); $db2->commit();
            }
        }
        catch (\Throwable $e2)
        {
            $this->dblogok = false; $this->LogException($e2);
            $errlog->SetDebugLog($loglevel, $this->GetDebugLog()); // update from e2
        }

        if (($e = $e->getPrevious()) instanceof \Throwable) $this->LogException($e);
        
        return $errlog;
    }
    
    private array $debuglog = array();
    
    /** Adds an entry to the custom debug log, saved with exceptions */
    public function LogDebugInfo($data) : self { $this->debuglog[] = $data; return $this; }
    
    /** Returns the internal supplemental debug log */
    public function GetDebugLog() : array { return $this->debuglog; }
    
    /** Creates an exception and logs it to the main error log (to get a backtrace) */
    public function LogBreakpoint() : self { return $this->LogDebugInfo((new BreakpointException())->getTraceAsString()); }
    
    /** Runs the given $func in try/catch and logs if exception */
    public function LoggedTry(callable $func)
    {
        try { return $func(); } catch (\Throwable $e) { $this->LogException($e); }
    }
}
