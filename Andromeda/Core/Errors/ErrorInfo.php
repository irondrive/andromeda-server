<?php declare(strict_types=1); namespace Andromeda\Core\Errors; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseRunner, Config, Utilities};
use Andromeda\Core\IOFormat\IOInterface;
use Andromeda\Core\Database\ObjectDatabase;

/** Represents an error log entry in the database */
class ErrorInfo
{
    private int $level;
    
    /** time of the error */
    private float $time;
    /** user address for the request */
    private string $addr;
    /** user agent for the request */
    private string $agent;
    /** command app */
    private ?string $app = null;
    /** command action */
    private ?string $action = null;
    /** error code string */
    private int $code;
    /** the file with the error */
    private string $file;
    /** the error message */
    private string $message;
    /** 
     * a basic backtrace 
     * @var array<int,string>
     */
    private array $trace_basic;
    /** 
     * full backtrace including all arguments 
     * @var ?array<int,array<string,mixed>>
     */
    private ?array $trace_full = null;
    /** 
     * objects in memory in the database 
     * @var ?array<mixed>
     */
    private ?array $objects = null;
    /** 
     * db queries that were performed 
     * @var ?array<mixed>
     */
    private ?array $queries = null;
    /** 
     * all client input parameters 
     * @var ?array<mixed>
     */
    private ?array $params = null;
    /** 
     * the custom API log 
     * @var ?array<mixed>
     */
    private ?array $hints = null;
    
    /** Return the time of the error */
    public function GetTime() : float           { return $this->time; }
    /** Return the user address for the request */
    public function GetAddr() : string          { return $this->addr; }
    /** Return the user agent for the request */
    public function GetAgent() : string         { return $this->agent; }
    /** Return the command app if active */
    public function TryGetApp() : ?string       { return $this->app; }
    /** Return the command action if active */
    public function TryGetAction() : ?string    { return $this->action; }
    /** Return the error code */
    public function GetCode() : int             { return $this->code; }
    /** Return the file with the error */
    public function GetFile() : string          { return $this->file; }
    /** Return the error message */
    public function GetMessage() : string       { return $this->message; }
    /** 
     * Return the basic backtrace
     * @return array<int,string>
     */
    public function GetTraceBasic() : array     { return $this->trace_basic; }
    /** 
     * Return the full backtrace including arguments if logged
     * @return array<int,array<string,mixed>>
     */
    public function TryGetTraceFull() : ?array  { return $this->trace_full; }
    /** 
     * Return the objects in memory in the database if logged 
     * @return array<mixed>
     */
    public function TryGetObjects() : ?array    { return $this->objects; }
    /** 
     * Return the db queries that were performed, if logged 
     * @return array<mixed>
     */
    public function TryGetQueries() : ?array    { return $this->queries; }
    /** 
     * Return the client input parameters, if logged 
     * @return array<mixed>
     */
    public function TryGetParams() : ?array     { return $this->params; }
    /** 
     * Return the custom API log hints, if logged 
     * @return array<mixed>
     */
    public function TryGetHints() : ?array      { return $this->hints; }
    
    /** Reload the debug hints from the error manager */
    public function ReloadHints(ErrorManager $errman) : self 
    { 
        if ($this->level < Config::ERRLOG_DETAILS) return $this;
        
        $this->hints = $errman->GetDebugHints(); return $this; 
    }
    
    /**
     * Creates an ErrorInfo object from the given exception
     *
     * @param int $level the log level to use (what to log)
     * @param \Throwable $e the exception being logged
     * @param IOInterface $iface the interface of the request
     * @param ?BaseRunner $runner active app runner or null
     * @param ?ObjectDatabase $db object database if available
     * @param ?array<mixed> $debuglog extra log info to log if wanted
     */
    public function __construct(int $level, \Throwable $e, IOInterface $iface,
        ?BaseRunner $runner, ?ObjectDatabase $db, ?array $debuglog)
    {
        $this->level = $level;
        $this->time = microtime(true);
        
        $this->addr = $iface->GetAddress();
        $this->agent = $iface->GetUserAgent();
        
        $this->code = $e->getCode();
        $this->message = $e->getMessage();
        
        $this->file = $e->getFile()."(".$e->getLine().")";
        
        $input = null; if ($runner !== null)
        {
            $context = $runner->GetContext();
            if ($context !== null) 
                $input = $context->GetInput();
        }

        if ($input !== null)
        {
            $this->app = $input->GetApp();
            $this->action = $input->GetAction();
        }
        
        $details = $level >= Config::ERRLOG_DETAILS;
        $sensitive = $level >= Config::ERRLOG_SENSITIVE;
        
        if ($details && $db !== null)
        {
            $this->objects = $db->getLoadedObjects();
            $this->queries = $db->GetInternal()->getAllQueries();
        }
        
        if ($sensitive && $input !== null)
        {
            $this->params = $input->GetParams()->GetClientObject();
        }
        
        $this->trace_basic = explode("\n",$e->getTraceAsString());
        
        if ($details)
        {
            $trace_full = $e->getTrace();
            
            foreach ($trace_full as &$val)
            {
                if (!$sensitive) unset($val['args']);
                
                else if (array_key_exists('args', $val))
                    Utilities::arrayStrings($val['args']);
            }
            
            $this->trace_full = $trace_full;
        }
        
        if ($details && $debuglog !== null)
            $this->hints = $debuglog;
    }

    /**
     * Returns the printable client object of this error info
     * @param ?int $level max debug level for output, null for unfiltered, also depends on the level this was created with
     * @return array<mixed> `{time:float,addr:string,agent:string,code:int,file:string,message:string,app:?string,action:?string,trace_basic:array}`
        if details or null level, add `{trace_full:array,objects:?array,queries:?array,hints:?array}`
        if sensitive or null level, add `{params:?array}`
     */
    public function GetClientObject(?int $level = null) : array
    {
        $retval = array(
            'time' => $this->time,
            'addr' => $this->addr,
            'agent' => $this->agent,
            'app' => $this->app,
            'action' => $this->action,
            'code' => $this->code,
            'file' => $this->file,
            'message' => $this->message,
            'trace_basic' => $this->trace_basic
        );
        
        $details = $level === null || $level >= Config::ERRLOG_DETAILS;
        $sensitive = $level === null || $level >= Config::ERRLOG_SENSITIVE;
        
        if ($details)
        {
            $retval['trace_full'] = $this->trace_full;
            
            if ($this->trace_full !== null)
            {
                $trace_full = $this->trace_full; // copy
                if (!$sensitive) foreach ($trace_full as &$val)
                    unset($val['args']);
                $retval['trace_full'] = $trace_full;
            }
        
            $retval['objects'] = $this->objects;
            $retval['queries'] = $this->queries;
            $retval['hints'] = $this->hints;
        }
        
        if ($sensitive)
        {
            $retval['params'] = $this->params;
        }
        
        return $retval;
    }
}
