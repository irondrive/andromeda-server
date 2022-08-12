<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/DBStats.php");
use Andromeda\Core\Database\DBStats;

require_once(ROOT."/Core/IOFormat/Input.php");
use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Core/Logging/ActionLog.php");
use Andromeda\Core\Logging\ActionLog;

class RunContext 
{ 
    private Input $input;     
    private ?ActionLog $actionlog;
    private ?DBStats $metrics;
    
    public function __construct(Input $input, ?ActionLog $actionlog)
    {
        $this->input = $input;
        $this->actionlog = $actionlog;
    }
    
    public function GetInput() : Input { return $this->input; }
    
    public function GetActionLog() : ?ActionLog { return $this->actionlog; }

    public function GetMetrics() : ?DBStats { return $this->metrics; }
    
    public function SetMetrics(DBStats $metrics) : void { $this->metrics = $metrics; }
}
