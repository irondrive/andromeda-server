<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;

if (!file_exists(ROOT."/core/database/Config.php")) die("Missing core/database/Config.php\n");
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
    private $start_time; private $construct_stats; private $commit_stats; private $run_stats = array(); 
    
    private $apps = array(); private $contexts = array(); private $config; private $database;
    
    public function GetApps() : array { return $this->apps; }
    public function GetConfig() : ?Config { return $this->config; }
    public function GetDatabase() : ?ObjectDatabase { return $this->database; }

    public function GetContext() : ?Input { return Utilities::array_last($this->contexts) ?? $this->lastcontext; }
    
    public function __construct(ErrorManager $error_manager)
    { 
        $this->start_time = microtime(true);
        
        $error_manager->SetAPI($this);          
        $this->database = new ObjectDatabase();
        
        $this->database->pushStatsContext(true);

        try { $this->config = Config::Load($this->database); } 
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }

        if (!$this->config->isEnabled()) throw new MaintenanceException();
        if ($this->config->isReadOnly() == Config::RUN_READONLY) $this->database->setReadOnly();    
        
        foreach($this->config->GetApps() as $app)
        {
            $path = ROOT."/apps/$app/$app"."App.php";
            $app_class = "Andromeda\\Apps\\$app\\$app".'App';
            
            if (is_file($path)) require_once($path); else throw new FailedAppLoadException();
            if (!class_exists($app_class)) throw new FailedAppLoadException();
            
            $this->apps[$app] = new $app_class($this);
        }
        
        $this->construct_stats = $this->database->popStatsContext()->getStats();
    }
    
    public function Run(Input $input)
    {        
        $app = $input->GetApp();         
        if (!array_key_exists($app, $this->apps)) throw new UnknownAppException();

        $this->database->pushStatsContext($this->GetDebug());

        array_push($this->contexts, $input);
        $data = $this->apps[$app]->Run($input);
        array_pop($this->contexts);
        
        array_push($this->run_stats, $this->database->popStatsContext()->getStats());
        
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
        set_time_limit(0);
        if (isset($this->database)) $this->database->rollback();        
        foreach($this->apps as $app) $app->rollback();
    }
    
    public function commit() : ?array
    {
        set_time_limit(0); $this->database->pushStatsContext($this->GetDebug());
        
        $dryrun = ($this->config->isReadOnly() == Config::RUN_DRYRUN);
        
        $this->database->commit($dryrun);        
        
        foreach ($this->apps as $app) $dryrun ? $app->rollback() : $app->commit();
        
        $this->commit_stats = $this->database->popStatsContext()->getStats();
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
        {
            $prototype = $this->run_stats[0];
            foreach (array_keys($prototype) as $key)
            {
                if (!is_numeric($prototype[$key])) continue;
                $run_stats_sums[$key] = 0;                
                foreach($this->run_stats as $runstats)
                    $run_stats_sums[$key] += $runstats[$key];
            }
        }

        $ret = array(
            'construct_stats' => $this->construct_stats,
            'run_stats' => $this->run_stats,
            'run_stats_total' => $run_stats_sums,
            'commit_stats' => $this->commit_stats,
            'total_time' => microtime(true) - $this->start_time,
            'peak_memory' => memory_get_peak_usage(),
            'objects' => $this->database->getLoadedObjects(),
        );
        if (count($this->run_stats) < 2) unset($ret['run_stats_total']);
        return $ret;
    }
    
}
