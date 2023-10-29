<?php declare(strict_types=1); namespace Andromeda\Core\Errors; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, BaseRunner, Config, Utilities};
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\{IOInterface, Output};

/** 
 * The main error handler/manager 
 * 
 * This class handles uncaught exceptions, logging them and converting to a client Output object.
 * Application code can use LogException() when an exception is caught but still needs to be logged.
 * @phpstan-import-type ScalarArray from Utilities
 */
class ErrorManager
{
    private IOInterface $interface;
    private ?ObjectDatabase $database = null;
    private ?Config $config = null;
    private ?BaseRunner $runner = null;
    private ?ApiPackage $apipack = null;
    
    /** true if the global php error handlers are set */
    private bool $isGlobal = false;
    
    /** 
     * Creates a new ErrorManager instance
     * @param bool $global if true, registers PHP error and exception handlers 
     */
    public function __construct(IOInterface $interface, bool $global)
    {
        $this->interface = $interface;
        
        if ($global)
        {
            $this->isGlobal = true;
            
            set_error_handler( function(int $code, string $msg, string $file, int $line) {
                throw new BaseExceptions\PHPError($code,$msg,$file,$line); });
            
            set_exception_handler(function(\Throwable $e)
            {
                if ($this->runner !== null) 
                    $this->runner->rollback($e);

                if ($e instanceof BaseExceptions\ClientException)
                    $output = $this->HandleClientException($e);
                else $output = $this->HandleThrowable($e);
                
                $this->interface->FinalOutput($output);
            });
        }
    }

    /** Unregister the global handlers if applicable */
    public function __destruct()
    {
        if ($this->isGlobal)
        {
            set_error_handler(null, E_ALL);
            set_exception_handler(null);
        }
    }
    
    /** Sets the database reference to use */
    public function SetDatabase(ObjectDatabase $db) : self { $this->database = $db; return $this; }
    /** Sets the core config reference to use */
    public function SetConfig(Config $config) : self { $this->config = $config; return $this; }
    /** Sets the base runner reference to use */
    public function SetRunner(BaseRunner $runner) : self { $this->runner = $runner; return $this; }
    /** Sets the API package reference to use (must be complete!) */
    public function SetApiPackage(ApiPackage $apipack) : self { $this->apipack = $apipack; return $this; }

    /** Returns the debug level for internal logging */
    private function GetDebugLogLevel() : int
    {
        return $this->config !== null ? $this->config->GetDebugLevel()
            : max(Config::ERRLOG_ERRORS, $this->interface->GetDebugLevel());
    }
    
    /** Returns the debug level to be show in output */
    private function GetDebugOutputLevel() : int
    {
        return $this->config !== null ? $this->config->GetDebugLevel($this->interface)
            : $this->interface->GetDebugLevel();
    }
    
    /** Creates a new error info from the given exception */
    private function CreateErrorInfo(\Throwable $e) : ErrorInfo
    {
        return new ErrorInfo($this->GetDebugLogLevel(), $e, $this->interface,
            $this->runner, $this->database, $this->debughints);
    }

    /** Handles a client exception, displaying debug data and returning an Output */
    private function HandleClientException(BaseExceptions\ClientException $e) : Output
    {
        try
        {
            $debug = null; if ($this->GetDebugOutputLevel() >= Config::ERRLOG_DETAILS) 
            {
                $debug = $this->CreateErrorInfo($e)
                    ->GetClientObject($this->GetDebugOutputLevel());
            }
    
            $output = Output::ClientException($e, $debug);
            
            if ($this->apipack !== null)
                $this->apipack->GetMetricsHandler()
                    ->SaveMetrics($this->apipack, $output, true);
            
            return $output;
        }
        catch (\Throwable $e2)
        {
            return $this->HandleThrowable($e2);
        }
    }
    
    /** Handles a non-client exception, logging debug data and returning an Output */
    private function HandleThrowable(\Throwable $e) : Output
    {
        $debug = null; try 
        {
            if (($errinfo = $this->LogException($e, false)) !== null)
            {
                $debug = $errinfo->GetClientObject($this->GetDebugOutputLevel());
            }
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
        
        return Output::ServerException($debug);
    }

    /** if false, the file-based log encountered an error on the last entry */
    private bool $filelogok = true;
    
    /** if false, the DB-based log encountered an error on the last entry */
    private bool $dblogok = true;
    
    /**
     * Log an exception to file (json) and database
     * 
     * A new database connection is used for the log entry!
     * @param \Throwable $e the exception to log
     * @param bool $hintlog if true, display this in the hint log
     * @return ?ErrorInfo error info object or null if not logged
     */
    public function LogException(\Throwable $e, bool $hintlog = true) : ?ErrorInfo
    {
        $loglevel = $this->GetDebugLogLevel(); 
        if ($loglevel < Config::ERRLOG_ERRORS) return null;

        $errinfo = $this->CreateErrorInfo($e);
        
        $debug = $errinfo->GetClientObject($loglevel);
        if ($hintlog) $this->LogDebugHint($debug);

        try // save to file
        {
            if ($this->filelogok && $this->config !== null && $this->config->GetDebugLog2File())
            {
                if (($logdir = $this->config->GetDataDir()) !== null)
                {
                    file_put_contents("$logdir/error.log", 
                        Utilities::JSONEncode($debug)."\r\n", FILE_APPEND); 
                }
            }
        }
        catch (\Throwable $e2) 
        { 
            $this->filelogok = false; 
            $this->LogException($e2);
            $errinfo->ReloadHints($this); // update from e2
        }
        
        try // save to database with a separate connection
        {
            if ($this->dblogok && $this->config !== null && $this->config->GetDebugLog2DB()) 
            {
                $db2 = ApiPackage::InitDatabase($this->interface);
                $errlog = ErrorLog::Create($db2, $errinfo);
                $errlog->Save(); $db2->commit();
            }
        }
        catch (\Throwable $e2)
        {
            $this->dblogok = false; 
            $this->LogException($e2);
            $errinfo->ReloadHints($this); // update from e2
        }

        if (($e = $e->getPrevious()) instanceof \Throwable) $this->LogException($e);
        
        return $errinfo;
    }
    
    /** @var ScalarArray */
    private array $debughints = array();
    
    /** 
     * Returns the internal supplemental debug log 
     * @return ScalarArray
     */
    public function GetDebugHints() : array { return $this->debughints; }
    
    /** 
     * Adds an entry to the custom debug log, saved with exceptions 
     * @param NULL|scalar|ScalarArray $data
     */
    public function LogDebugHint($data) : self { $this->debughints[] = $data; return $this; }
    
    /** Creates an exception and logs it to the main error log (to get a backtrace) */
    public function LogBreakpoint() : self 
    {
        $e = new BaseExceptions\BreakpointException();
        $trace = $e->getTraceAsString();
        return $this->LogDebugHint($trace); 
    }
    
    /** 
     * Runs the given $func in try/catch and logs if exception 
     * @template T
     * @param callable():T $func
     * @return T|void
     */
    public function LoggedTry(callable $func)
    {
        try { return $func(); } catch (\Throwable $e) { $this->LogException($e); }
    }
}
