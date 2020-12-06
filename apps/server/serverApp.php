<?php namespace Andromeda\Apps\Server; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/core/Utilities.php");use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\{Database, DatabaseConfigException};
require_once(ROOT."/core/ioformat/Input.php");use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php");use Andromeda\Core\IOFormat\SafeParam;

use Andromeda\Core\{UnknownActionException, UnknownConfigException};

class ServerApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public static function getUsage() : array
    {
        return array(
            'random [--length int]',
            'getapps', 'runtests',
            'getusage|usage|help', 'install',
            'initdb --connect text [--dbuser name] [--dbpass raw] [--prefix alphanum] [--persistent bool]'            
        );
    }
    
    public function Run(Input $input)
    {
        if (!$this->API->GetDatabase())
        {
            if ($input->GetAction() !== 'initdb')
                throw new DatabaseConfigException();          
        }
        else if (!$this->API->GetConfig() && $input->GetAction() !== 'install')
            throw new UnknownConfigException(static::class);
        
        switch($input->GetAction())
        {
            case 'random':  return $this->Random($input); break;
            case 'getapps': return $this->GetApps($input); break;
            case 'runtests': return $this->RunTests($input); break;
            
            case 'initdb':  return $this->InitDB($input); break;
            case 'install': return $this->Install($input); break;
            
            case 'getusage':
            case 'usage':
            case 'help':
                return $this->GetUsages($input); break;
            
            default: throw new UnknownActionException();
        }
    }
    
    protected function Random(Input $input)
    {
        $length = $input->TryGetParam("length", SafeParam::TYPE_INT);
        
        return Utilities::Random($length ?? 16);
    }
    
    protected function GetApps(Input $input) 
    {
        return array_map(function($app){ return $app::getVersion(); }, $this->API->GetApps());
    }
    
    protected function GetUsages(Input $input)
    {
        $output = array(); foreach ($this->API->GetApps() as $name=>$app)
        {
            array_push($output, ...array_map(function($line)use($name){ return "$name $line"; }, $app::getUsage())); 
        }
        return $output;
    }

    protected function RunTests(Input $input)
    {
        set_time_limit(0);
        
        if ($this->API->GetDebugState())
        {
            return array_map(function($app)use($input){ return $app->Test($input); }, $this->API->GetApps());
        }
        else throw new UnknownActionException();
    }
    
    protected function InitDB(Input $input)
    {
        if ($this->API->GetDatabase()) throw new UnknownActionException();
        
        Database::Install($input);
    }
    
    // TODO set enabled (from CLI only?)
    
    protected function Install(Input $input)
    {
        if ($this->API->GetConfig()) throw new UnknownActionException();
        
        $database = $this->API->GetDatabase();
        $database->importFile(ROOT."/andromeda2.sql");
        
        $config = Config::Create($database);
        
        $apps = array_filter(scandir(ROOT."/apps"),function($e){ return !in_array($e,array('.','..')); });
        foreach ($apps as $app) $config->enableApp($app);
        
        $config->setEnabled(!$this->API->isLocalCLI());
        
        return array('apps'=>array_filter($apps,function($e){ return $e !== 'server'; }));
    }
}

