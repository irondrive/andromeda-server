<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php");
require_once(ROOT."/core/Utilities.php");

require_once(ROOT."/core/database/DBStats.php");
require_once(ROOT."/core/database/ObjectDatabase.php");
use Andromeda\Core\Database\{ObjectDatabase, DBStats, DatabaseException};

require_once(ROOT."/core/exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/interfaces/AJAX.php");
use Andromeda\Core\IOFormat\{Input,Output,IOInterface};
use Andromeda\Core\IOFormat\Interfaces\AJAX;

/** Exception indicating that the requested app is invalid */
class UnknownAppException extends Exceptions\ClientErrorException   { public $message = "UNKNOWN_APP"; }

/** Exception indicating that the server is configured as disabled */
class MaintenanceException extends Exceptions\ClientDeniedException { public $message = "SERVER_DISABLED"; }

/** Exception indicating that the server must be disabled for this request */
class DisableRequiredException extends Exceptions\ClientErrorException { public $message = "DISABLE_REQUIRED"; }

/** Exception indicating that the server failed to load a configured app */
class FailedAppLoadException extends Exceptions\ServerException  { public $message = "FAILED_LOAD_APP"; }

/** Exception indicating that the configured data directory is invalid */
class InvalidDataDirException extends Exceptions\ServerException { public $message = "INVALID_DATA_DIRECTORY"; }

/** Exception indicating that writing to the data file failed */
class DataWriteFailedException extends Exceptions\ServerException { public $message = "DATA_WRITE_FAILED"; }

/** Andromeda cannot rollback and then commit since database/objects state is not sufficiently reset */
class CommitAfterRollbackException extends Exceptions\ServerException { public $message = "COMMIT_AFTER_ROLLBACK"; }

class Context { public Input $input; public function __construct(Input $input){ $this->input = $input; }}

/**
 * The main container class managing API singletons and running apps
 * 
 * Main is also a Singleton so it can be fetched anywhere with GetInstance().
 * A large portion of the code is dedicated to gathering performance metrics.
 */
class Main extends Singleton
{ 
    private array $construct_stats; 
    private array $run_stats = array(); 
    private array $commit_stats = array();
    private DBStats $sum_stats;
    
    private float $time;
    private array $apps = array(); 
    
    /** @var Context[] stack frames for nested Run() calls */
    private array $stack = array(); 
    
    private ?Config $config = null; 
    private ?ObjectDatabase $database = null; 
    private ErrorManager $error_manager;
    private IOInterface $interface;
    
    /** true if Run() has been called since the last commit or rollback */
    private bool $dirty = false;
    
    /** Gets the timestamp of when the request was started */
    public function GetTime() : float { return $this->time; }
    
    /**
     * Gets an array of instantiated apps
     * @return array<string, BaseApp>
     */
    public function GetApps() : array { return $this->apps; }
    
    /** Returns the global config object */
    public function GetConfig() : ?Config { return $this->config; }
    
    /** Returns the global ObjectDatabase instance */
    public function GetDatabase() : ?ObjectDatabase { return $this->database; }
    
    /** Returns the interface used for the current request */
    public function GetInterface() : IOInterface { return $this->interface; }
    
    /** Returns the Input that is currently being executed */
    public function GetInput() : ?Input 
    { 
        $context = Utilities::array_last($this->stack); 
        return ($context !== null) ? $context->input : null;
    }

    /**
     * Initializes the Main API
     * 
     * Creates the error manager, initializes the interface, initializes the
     * database, loads global config, loads and constructs registered apps 
     * @param IOInterface $interface the interface that began the request
     * @throws MaintenanceException if the server is not enabled
     * @throws FailedAppLoadException if a registered app fails to load
     */
    public function __construct(IOInterface $interface)
    {
        $this->error_manager = (new ErrorManager($interface))->SetAPI($this);
        
        parent::__construct();
        
        $this->time = microtime(true);
        $this->interface = $interface;
        $this->sum_stats = new DBStats();
        
        $interface->Initialize();
        
        try 
        {
            $dbconf = $interface->GetDBConfigFile();
            $this->database = new ObjectDatabase($dbconf);        
            $this->database->pushStatsContext();

            $this->config = Config::GetInstance($this->database);

            if (!$this->isEnabled()) throw new MaintenanceException();
            
            if ($this->config->isReadOnly()) 
                $this->database->setReadOnly();    

            $apps = $this->config->GetApps();
        }
        catch (DatabaseException $e) { $apps = array('server'); }
        
        foreach ($apps as $app) $this->LoadApp($app);

        if ($this->database)
        {
            $construct_stats = $this->database->popStatsContext();
            $this->sum_stats->Add($construct_stats);
            $this->construct_stats = $construct_stats->getStats();
        }
        
        register_shutdown_function(function(){
            if ($this->dirty) $this->rollback(false); });
    }
    
    /** Loads the main include file for an app */
    public function LoadApp(string $app) : self
    {
        $path = ROOT."/apps/$app/$app"."App.php";
        $app_class = "Andromeda\\Apps\\$app\\$app".'App';
        
        if (is_file($path)) require_once($path);
        else throw new FailedAppLoadException();
        
        if (!class_exists($app_class)) throw new FailedAppLoadException();
        
        if (!array_key_exists($app, $this->apps))
            $this->apps[$app] = new $app_class($this);
        
        return $this;
    }

    /**
     * Calls into an app to run the given Input command
     * 
     * Calls Run() on the requested app and then saves (but does not commit) 
     * any modified objects. These calls can be nested - apps can call Run for 
     * other apps but should always do so via the API, not directly to the app
     * @param Input $input the command to run
     * @throws UnknownAppException if the requested app is invalid
     */
    public function Run(Input $input)
    {        
        $app = $input->GetApp();
        
        if (!array_key_exists($app, $this->apps)) throw new UnknownAppException();
        
        $this->stack[] = new Context($input);
        
        $dbstats = $this->GetDebugLevel() >= Config::LOG_DEVELOPMENT;
        
        if ($dbstats && $this->database) 
            $this->database->pushStatsContext();

        $this->dirty = true;
        
        $data = $this->apps[$app]->Run($input);

        if ($this->database) $this->database->saveObjects();
        
        array_pop($this->stack);
        
        if ($dbstats && $this->database)
        {
            $stats = $this->database->popStatsContext();
            $this->sum_stats->add($stats);
            $this->run_stats[] = $stats->getStats();
        }
        
        return $data;
    }     

    /**
     * Calls into a remote API to run the given Input command
     * 
     * Note that this breaks transactions - the remote API will 
     * commit before we get the response to this remote call.
     * @param string $url the base URL of the remote API
     * @see Main::Run()
     */
    public function RunRemote(string $url, Input $input)
    {
        $start = hrtime(true); 

        $data = AJAX::RemoteRequest($url, $input);

        if ($this->GetDebugLevel() >= Config::LOG_DEVELOPMENT)
        {
            $this->run_stats[] = array(
                'remote_time' => (hrtime(true)-$start)/1e9,
            );
        }  

        return Output::ParseArray($data)->GetData();
    }
    
    private array $writes = array();
    
    /**
     * Helper function for apps to write data to the global data directory
     * @param string $path the path (within the data directory) to write to
     * @param string $data the data to place in the file
     * @throws InvalidDataDirException if the datadir is invalid or not set
     * @return $this
     */
    public function WriteDataFile(string $path, string $data) : self
    {
        $datadir = $this->GetConfig()->GetDataDir();
        if (!$datadir || !is_writeable($datadir))
            throw new InvalidDataDirException();
        
        $this->writes[] = $path;
        
        if (file_put_contents($path, $data) === false)
            throw new DataWriteFailedException();
    }
    
    /**
     * Rolls back the current transaction. Internal only, do not call via apps.
     * 
     * First rolls back each app, then the database, then saves mandatorySave objects if not a server error
     * @param bool $serverError true if this rollback is due to a server error
     */
    public function rollback(bool $serverError) : void
    {
        $this->rollback = true;
        
        foreach ($this->apps as $app) try { $app->rollback(); }
        catch (\Throwable $e) { $this->error_manager->Log($e); }
        
        foreach ($this->writes as $path) try { unlink($path); } 
        catch (\Throwable $e) { $this->error_manager->Log($e); }
        
        if ($this->database) 
        {
            $this->database->rollback();
            
            if (!$serverError)
            {
                try 
                { 
                    $this->database->saveObjects(true);
                    
                    $rollback = $this->config && $this->config->getReadOnly();
                    
                    if ($rollback) $this->database->rollback(); else $this->database->commit(); 
                }
                catch (\Throwable $e) { $this->error_manager->Log($e); }
            }
        }
        
        $this->dirty = false;
    }
    
    /**
     * Commits the current transaction. Internal only, do not call via apps.
     * 
     * First commits each app, then the database.  Does a rollback 
     * instead if the request was specified as a dry run.
     */
    public function commit() : void
    {
        if (isset($this->rollback)) throw new CommitAfterRollbackException();
        
        $tl = ini_get('max_execution_time'); set_time_limit(0);
        $ua = ignore_user_abort(); ignore_user_abort(true);
        
        if ($this->database)
        {          
            if ($this->GetDebugLevel() >= Config::LOG_DEVELOPMENT) 
                $this->database->pushStatsContext();
            
            $this->database->saveObjects();
                
            $rollback = $this->config && $this->config->getReadOnly();
            
            foreach ($this->apps as $app) $rollback ? $app->rollback() : $app->commit();
            
            if ($rollback) $this->database->rollback(); else $this->database->commit();
                    
            if ($this->GetDebugLevel() >= Config::LOG_DEVELOPMENT) 
            {
                $commit_stats = $this->database->popStatsContext();
                $this->commit_stats[] = $commit_stats->getStats();
                $this->sum_stats->Add($commit_stats);
            }
        }
        else foreach ($this->apps as $app) $app->commit();
        
        set_time_limit($tl); ignore_user_abort($ua);
        
        $this->dirty = false;
    }

    /** if false, requests are not allowed (always true for privileged interfaces) */
    protected function isEnabled() : bool
    {
        if ($this->config === null) return true;
        
        return $this->config->isEnabled() || $this->interface->isPrivileged();
    }
    
    /**
     * Asserts that the server is disabled via config
     * @throws DisableRequiredException if not
     */
    public function requireDisabled() : self
    {
        if ($this->config !== null && $this->config->isEnabled())
            throw new DisableRequiredException();
       
        return $this;
    }
    
    /** Returns the configured debug level, or the interface's default */
    public function GetDebugLevel() : int
    {
        try
        {
            $debug = $this->config->GetDebugLevel();
            if (!$this->config->GetDebugOverHTTP() && !$this->interface->isPrivileged()) $debug = 0;
            return $debug;
        }
        catch (\Throwable $e) { return $this->interface->GetDebugLevel(); }
    }
    
    private static array $debuglog = array();
    
    /** Adds an entry to the custom debug log */
    public static function PrintDebug($data){ self::$debuglog[] = $data; }
    
    /** Returns the custom debug log, if allowed by config */
    public function GetDebugLog() : ?array 
    {
        return $this->GetDebugLevel() >= Config::LOG_DEVELOPMENT && 
            count(self::$debuglog) ? self::$debuglog : null; 
    }

    /** Returns an array of performance metrics, if allowed by config */
    public function GetMetrics() : ?array
    {
        if ($this->GetDebugLevel() < Config::LOG_DEVELOPMENT) return null;
        
        $retval = array(
            'construct_stats' => $this->construct_stats,
            'run_stats' => $this->run_stats,
            'commit_stats' => $this->commit_stats,
            'stats_total' => $this->sum_stats->getStats(),
            'peak_memory' => memory_get_peak_usage(),
            'objects' => $this->database->getLoadedObjects(),
            'queries' => $this->database->getAllQueries()
        );

        $log = $this->GetDebugLog();
        if ($log !== null) $retval['log'] = $log;
        
        return $retval;
    }
    
}
