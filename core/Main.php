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

class UnknownAppException extends Exceptions\ClientErrorException   { public $message = "UNKNOWN_APP"; }
class MaintenanceException extends Exceptions\ClientDeniedException { public $message = "SERVER_DISABLED"; }

class FailedAppLoadException extends Exceptions\ServerException  { public $message = "FAILED_LOAD_APP"; }
class InvalidDataDirException extends Exceptions\ServerException { public $message = "INVALID_DATA_DIRECTORY"; }

class Main extends Singleton
{ 
    private array $construct_stats; 
    private array $commit_stats; 
    private array $run_stats = array(); 
    private DBStats $sum_stats;
    
    private array $apps = array(); 
    private array $contexts = array(); 
    
    private ?Config $config = null; 
    private ?ObjectDatabase $database = null; 
    private ErrorManager $error_manager;
    private IOInterface $interface;
    
    public function GetApps() : array { return $this->apps; }
    public function GetConfig() : ?Config { return $this->config; }
    public function GetDatabase() : ?ObjectDatabase { return $this->database; }
    public function GetInterface() : IOInterface { return $this->interface; }
    
    public function GetContext() : ?Input { return Utilities::array_last($this->contexts); }

    public function __construct(ErrorManager $error_manager, IOInterface $interface)
    { 
        parent::__construct();
        
        $this->interface = $interface;
        $this->sum_stats = new DBStats();
        
        $this->error_manager = $error_manager;
        $error_manager->SetAPI($this);
        
        try 
        {
            $this->database = new ObjectDatabase();        
            $this->database->pushStatsContext();

            $this->config = Config::Load($this->database);

            if (!$this->isEnabled()) throw new MaintenanceException();
            
            if ($this->config->isReadOnly() == Config::RUN_READONLY) 
                $this->database->setReadOnly();    

            $apps = $this->config->GetApps();
        }
        catch (\PDOException | DatabaseException $e) { $apps = array('server'); }
        
        foreach ($apps as $app)
        {
            $path = ROOT."/apps/$app/$app"."App.php";
            $app_class = "Andromeda\\Apps\\$app\\$app".'App';
            
            if (is_file($path)) require_once($path); else throw new FailedAppLoadException();
            if (!class_exists($app_class)) throw new FailedAppLoadException();
            
            $this->apps[$app] = new $app_class($this);
        }

        if ($this->database)
        {
            $construct_stats = $this->database->popStatsContext();
            $this->sum_stats->Add($construct_stats);
            $this->construct_stats = $construct_stats->getStats();
        }
    }
    
    public function Run(Input $input)
    {        
        $app = $input->GetApp();         
        if (!array_key_exists($app, $this->apps)) throw new UnknownAppException();

        if ($this->GetDebugState() >= Config::LOG_DEVELOPMENT && $this->database)
        { 
            $this->database->pushStatsContext();
            $oldstats = &$this->run_stats; 
            $idx = array_push($oldstats,array()); 
            $this->run_stats = &$oldstats[$idx-1];
        }

        array_push($this->contexts, $input);
        $data = $this->apps[$app]->Run($input);
        if ($this->database) $this->database->saveObjects();
        array_pop($this->contexts);        
             
        if ($this->GetDebugState() >= Config::LOG_DEVELOPMENT && $this->database)
        {
            $newstats = $this->database->popStatsContext();
            $this->sum_stats->add($newstats);
            $this->run_stats = array_merge($this->run_stats, $newstats->getStats());
            $oldstats[$idx-1] = &$this->run_stats; $this->run_stats = &$oldstats;
        }
        
        return $data;
    }     

    public function RunRemote(string $url, Input $input)
    {
        $start = microtime(true); 

        $data = AJAX::RemoteRequest($url, $input);

        if ($this->GetDebugState() >= Config::LOG_DEVELOPMENT)
        {
            array_push($this->run_stats, array(
                'remote_time' => microtime(true) - $start,
            ));
        }  

        return Output::ParseArray($data)->GetData();
    }
    
    private array $writes = array();
    public function WriteDataFile(string $path, string $data) : self
    {
        $datadir = $this->GetConfig()->GetDataDir();
        if (!$datadir || !is_writeable($datadir))
            throw new InvalidDataDirException();
        
        array_push($this->writes, $path);
        file_put_contents($path, $data);
    }
    
    public function rollBack(bool $serverError)
    {
        set_time_limit(0);
        
        foreach ($this->apps as $app) try { $app->rollback(); }
        catch (\Throwable $e) { $this->error_manager->Log($e); }
        
        if ($this->database) $this->database->rollback(!$serverError);   
        
        foreach ($this->writes as $path) try { unlink($path); } 
        catch (\Throwable $e) { $this->error_manager->Log($e); }
    }
    
    public function commit() : ?array
    {
        set_time_limit(0); 
        
        if ($this->database)
        {          
            if ($this->GetDebugState() >= Config::LOG_DEVELOPMENT) 
                $this->database->pushStatsContext();
            
            $rollback = $this->config && $this->config->isReadOnly();
            
            $this->database->saveObjects();
            
            foreach ($this->apps as $app) $rollback ? $app->rollback() : $app->commit();
            
            if ($rollback) $this->database->rollback(); else $this->database->commit();
                    
            if ($this->GetDebugState() >= Config::LOG_DEVELOPMENT) 
            {
                $commit_stats = $this->database->popStatsContext();
                $this->sum_stats->Add($commit_stats);
                $this->commit_stats = $commit_stats->getStats();
                return $this->GetMetrics(); 
            }
        }
        else foreach ($this->apps as $app) $app->commit();
        
        return null;
    }
    
    public function isLocalCLI() : bool
    {
        return $this->interface->getMode() === IOInterface::MODE_CLI;
    }
    
    public function isEnabled() : bool
    {
        if ($this->config === null) return true;
        
        return $this->config->isEnabled() || $this->isLocalCLI();
    }
    
    public function GetDebugState() : int
    {
        if ($this->config === null || !isset($this->construct_stats)) return Config::LOG_ERRORS;

        $debug = $this->config->GetDebugLogLevel();
        
        if (!$this->config->GetDebugOverHTTP() && !$this->isLocalCLI()) $debug = 0;

        return $debug;
    }
    
    private static array $debuglog = array();    
    public static function PrintDebug(string $data){ array_push(self::$debuglog, $data); }
    
    public function GetDebugLog() : ?array 
    { 
        return $this->GetDebugState() >= Config::LOG_DEVELOPMENT && 
            count(self::$debuglog) ? self::$debuglog : null; 
    }
    
    private function GetMetrics() : array
    {
        $ret = array(
            'construct_stats' => $this->construct_stats,
            'run_stats' => $this->run_stats,
            'commit_stats' => $this->commit_stats,
            'stats_total' => $this->sum_stats->getStats(),
            'peak_memory' => memory_get_peak_usage(),
            'objects' => $this->database->getLoadedObjects(),
            'queries' => $this->database->getAllQueries()
        );
        return $ret;
    }
    
}
