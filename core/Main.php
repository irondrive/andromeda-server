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
    private $construct_time;
    
    private $runs = array(); private $run_stats = array();
    
    private $context; private $config; private $database; private $interface;
    
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
    }
    
    public function Run(Input $input)
    { 
        $prevContext = $this->context; $this->context = $input;

        $app = $input->GetApp(); 
        
        if (!in_array($app, $this->config->GetApps())) throw new UnknownAppException();
        
        $path = ROOT."/apps/$app/$app"."App.php"; 
        $app_class = "Andromeda\\Apps\\$app\\$app".'App';
        
        if (is_file($path)) require_once($path); else throw new FailedAppLoadException();
        if (!class_exists($app_class)) throw new FailedAppLoadException();
        
        $start = microtime(true); $this->database->resetStats();
        
        $app_object = new $app_class($this);        
        array_push($this->runs, $app_object);
        
        $data = $app_object->Run($input);
        
        $total_time = microtime(true) - $start; 
        $code_time = $total_time - $this->database->getReadTime() - $this->database->getWriteTime();
        
        array_push($this->run_stats, array(
            'db_reads' => $this->database->getReads(),
            'db_read_time' => $this->database->getReadTime(),
            'code_time' => $code_time,
            'total_time' => $total_time,
        ));
        
        $this->context = $prevContext;

        return $data;
    }     

    public function RunRemote(string $url, Input $input)
    {
        $this->app_time = microtime(true);

        $data = AJAX::RemoteRequest($url, $input);

        $this->app_time = microtime(true) - $this->app_time;

        return Output::Parse($data);
    }
    
    public function rollBack()
    {
        $this->database->rollback();        
        foreach($this->runs as $app) $app->rollback();
    }
    
    public function commit()
    {
        $this->database->resetStats()->commit();        
        foreach($this->runs as $app) $app->commit();
    }
    
    public function GetDebug() : bool
    {
        return $this->config->GetDebugOverHTTP() ||
            $this->interface->getMode() == IOInterface::MODE_CLI;
    }
    
    public function GetMetrics(bool $apptime = true) : array
    {        
        $metrics = array(
            'run_stats' => $this->run_stats,
            'commit_writes' => $this->database->getWrites(),
            'commit_time' => $this->database->getWriteTime(),
            'total_time' => microtime(true) - $this->construct_time,
            'peak_memory' => memory_get_peak_usage(),
            'queries' => $this->database->getHistory(),
            'objects' => $this->database->getLoadedObjects(),
        );
        if (!$apptime) unset($metrics['app_time']);
        return $metrics;
    }
    
}
