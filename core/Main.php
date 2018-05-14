<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/core/database/Config.php");
require_once(ROOT."/core/database/ObjectDatabase.php");
use Andromeda\Core\Database\{Transactions, ObjectDatabase, ObjectNotFoundException};

require_once(ROOT."/core/exceptions/ErrorManager.php");
use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/core/ioformat/IOInterface.php");
require_once(ROOT."/core/ioformat/Input.php");
require_once(ROOT."/core/ioformat/Output.php");
require_once(ROOT."/core/ioformat/interfaces/AJAX.php");
use Andromeda\Core\IOFormat\{IOInterface,Input,Output};
use Andromeda\Core\IOFormat\Interfaces\AJAX;

class UnknownAppException extends Exceptions\Client400Exception     { public $message = "UNKNOWN_APP"; }
class MaintenanceException extends Exceptions\Client403Exception    { public $message = "SERVER_DISABLED"; }
class UnknownConfigException extends Exceptions\ServerException     { public $message = "MISSING_CONFIG_OBJECT"; }
class FailedAppLoadException extends Exceptions\ServerException     { public $message = "FAILED_LOAD_APP"; }

class Main implements Transactions
{ 
    private $construct_time_start; private $construct_time_elapsed;
    
    private $apps = array(); private $run_stats = array(); private $commit_stats;
    
    private $context; private $config; private $database; private $interface;
    
    public function GetApps() : array { return $this->apps; }
    public function GetContext() : ?Input { return $this->context; }
    public function GetConfig() : ?Config { return $this->config; }
    public function GetDatabase() : ?ObjectDatabase { return $this->database; }
    public function GetInterface() : IOInterface { return $this->interface; }        

    public function __construct(ErrorManager $error_manager, IOInterface $interface)
    {
        $this->construct_time = microtime(true);
        
        $error_manager->SetAPI($this);  
        
        $this->interface = $interface; $this->database = new ObjectDatabase();
        
        try { $this->config = Config::Load($this->database); } 
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }

        if (!$this->config->isEnabled()) throw new MaintenanceException();
        if ($this->config->isReadOnly()) $this->database->setReadOnly();    
        
        foreach($this->config->GetApps() as $app)
        {
            $path = ROOT."/apps/$app/$app"."App.php";
            $app_class = "Andromeda\\Apps\\$app\\$app".'App';
            
            if (is_file($path)) require_once($path); else throw new FailedAppLoadException();
            if (!class_exists($app_class)) throw new FailedAppLoadException();
            
            $this->apps[$app] = new $app_class($this);
        }
    }
    
    public function Run(Input $input)
    {
        if (!isset($this->construct_time_elapsed)) 
            $this->construct_time_elapsed = microtime(true) - $this->construct_time;
        
        $prevContext = $this->context; $this->context = $input;

        $app = $input->GetApp(); 
        
        if (!array_key_exists($app, $this->apps)) throw new UnknownAppException();

        if ($this->GetDebug()) { $start = microtime(true); $this->database->startStatsContext(); }

        $data = $this->apps[$app]->Run($input);
        
        if ($this->GetDebug()) 
        {
            $stats = $this->database->getStatsContext();
            $this->database->endStatsContext();
            $total_time = microtime(true) - $start;
            $code_time = $total_time - $stats->getReadTime();
            
            array_push($this->run_stats, array(
                'db_reads' => $stats->getReads(),
                'db_read_time' => $stats->getReadTime(),
                'db_writes' => $stats->getWrites(),
                'db_write_time' => $stats->getWriteTime(),
                'code_time' => $code_time,
                'total_time' => $total_time,
            ));
        }        
        
        $this->context = $prevContext;
        
        return $data;
    }     

    public function RunRemote(string $url, Input $input)
    {
        $start = microtime(true); 

        $data = AJAX::RemoteRequest($url, $input);

        if ($this->GetDebug())
        {
            array_push($this->run_stats, array(
                'remote_time' => microtime(true) - $start,
            ));
        }  

        return Output::ParseArray($data)->GetData();
    }
    
    public function rollBack()
    {
        $this->database->rollback();        
        foreach($this->apps as $app) $app->rollback();
    }
    
    public function commit() : ?array
    {
        $this->database->startStatsContext()->commit();        
        foreach($this->apps as $app) $app->commit();
        $this->commit_stats = $this->database->getStatsContext();
        $this->database->endStatsContext();
        if ($this->GetDebug()) return $this->GetMetrics(); else return null;
    }
    
    public function GetDebug() : bool
    {
        return ($this->config->GetDebugOverHTTP() || $this->interface->getMode() == IOInterface::MODE_CLI)
            && $this->config->GetDebugLogLevel() >= Config::LOG_BASIC;
    }
    
    private function GetMetrics() : array
    {        
        $run_stats_sums = array();
        if (isset($this->run_stats[0])) 
            foreach (array_keys($this->run_stats[0]) as $key) {
                $run_stats_sums[$key] = 0;                
                foreach($this->run_stats as $stats)
                    $run_stats_sums[$key] += $stats[$key]; }

        return array(
            'construct_time' => $this->construct_time_elapsed,
            'run_stats' => $this->run_stats,
            'run_stats_sums' => $run_stats_sums,
            'commit_writes' => $this->commit_stats->getWrites(),
            'commit_time' => $this->commit_stats->getWriteTime(),
            'total_time' => microtime(true) - $this->construct_time,
            'peak_memory' => memory_get_peak_usage(),
            'queries' => $this->database->getHistory(),
            'objects' => $this->database->getLoadedObjects(),
        );
    }
    
}
