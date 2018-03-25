<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Server.php"); use Andromeda\Core\Server;

require_once(ROOT."/core/database/Config.php");
require_once(ROOT."/core/database/ObjectDatabase.php");
use Andromeda\Core\Database\{Config, ObjectDatabase, ObjectNotFoundException};

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

class Main
{ 
    private $construct_time; private $app_time;
    
    private $runs = array(); private $context; private $server; private $database; private $error_manager;  private $interface;
    
    public function GetContext() : ?Input { return $this->context; }
    public function GetServer() : ?Server { return $this->server; }
    public function GetDatabase() : ?ObjectDatabase { return $this->database; }
    public function GetInterface() : IOInterface { return $this->interface; }    
    public function GetErrorManager() : ErrorManager { return $this->error_manager; }  
    
    public function __construct(ErrorManager $error_manager, IOInterface $interface)
    {
        $this->construct_time = microtime(true);
        
        $this->error_manager = $error_manager; $this->error_manager->SetAPI($this);  
        
        $this->interface = $interface; $this->database = new ObjectDatabase();
        
        try { $this->server = Server::Load($this->database); } 
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }

        if (!$this->server->isEnabled()) throw new MaintenanceException();
        if ($this->server->isReadOnly()) $this->database->setReadOnly();    
    }
    
    public function Run(Input $input)
    { 
        $prevContext = $this->context; $this->context = $input;

        $app = $input->GetApp(); 
        
        if (!in_array($app, $this->server->GetApps())) throw new UnknownAppException();
        
        $path = ROOT."/apps/$app/$app"."App.php"; 
        $app_class = "Andromeda\\Apps\\$app\\$app".'App';
        
        if (is_file($path)) require_once($path); else throw new FailedAppLoadException();
        if (!class_exists($app_class)) throw new FailedAppLoadException();
        
        $this->app_time = microtime(true);
        
        $app_object = new $app_class($this);        
        $data = $app_object->Run($input);
        array_push($this->runs, $app_object);
        
        $this->app_time = microtime(true) - $this->app_time;
        
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
    
    public function Commit()
    {
        $this->database->commit();
        
        foreach($this->runs as $app) $app->Commit();
        
        $this->error_manager->ResetErrorHandlers();
    }
    
    public function GetDebug() : bool
    {
        return $this->server->GetDebugOverHTTP() ||
            $this->interface->getMode() == IOInterface::MODE_CLI;
    }
    
    public function GetMetrics(bool $apptime = true) : array
    {        
        $metrics = array(
            'total_time' => microtime(true) - $this->construct_time,
            'app_time' => $this->app_time,
            'peak_memory' => memory_get_peak_usage(),        
            'db_reads' => $this->database->getReads(),
            'db_writes' => $this->database->getWrites(),
            'queries' => $this->database->getHistory(),
        );
        if (!$apptime) unset($metrics['app_time']);
        return $metrics;
    }
    
}
