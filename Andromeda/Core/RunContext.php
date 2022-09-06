<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

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
    private ?DBStats $metrics = null;
    
    public function __construct(Input $input, ?ActionLog $actionlog)
    {
        $this->input = $input;
        $this->actionlog = $actionlog;
    }
    
    /** Returns the input object being run */
    public function GetInput() : Input { return $this->input; }
    
    /** Returns the action log created for this run */
    public function GetActionLog() : ?ActionLog { return $this->actionlog; }
    
    /** Returns true if metrics were set */
    public function HasMetrics() : bool { return $this->metrics !== null; }

    /** 
     * Returns the metrics created for this run
     * @throws MissingMetricsException if it wasn't set
     */
    public function GetMetrics() : DBStats 
    { 
        if ($this->metrics === null) 
            throw new MissingMetricsException();
        else return $this->metrics;
    }
    
    /** Sets the metrics created for this fun */
    public function SetMetrics(DBStats $metrics) : void { $this->metrics = $metrics; }
}
