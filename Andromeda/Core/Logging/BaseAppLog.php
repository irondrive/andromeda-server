<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Core/Logging/BaseLog.php");
require_once(ROOT."/Core/Logging/ActionLog.php");

/**
 * Base class for extended app action logs, supplementing ActionLog
 * 
 * Performs a join with ActionLog when loading so that the user can
 * filter by app-specific and common action parameters simulataneously.
 */
abstract class BaseAppLog extends BaseLog
{
    protected ActionLog $actionlog; // cached link to the parent log
    
    /** Returns the ActionLog parent for this entry */
    protected function GetActionLog() : ActionLog
    {
        return ($this->actionlog ??= ActionLog::LoadByApplog($this->database, $this));
    }
    
    /**
     * Returns the configured details log detail level
     *
     * If 0, details logs will be discarded, else see Config enum
     * @see \Andromeda\Core\Config::GetRequestLogDetails()
     */
    public static function GetDetailsLevel() : int { return Main::GetInstance()->GetConfig()->GetRequestLogDetails(); }
    
    /**
     * Returns true if the configured details log detail level is >= full
     * @see \Andromeda\Core\Config::GetRequestLogDetails()
     */
    public static function isFullDetails() : bool { return static::GetDetailsLevel() >= Config::RQLOG_DETAILS_FULL; }
    
    /** cached array of details log so we can set it once only in Save() */
    protected array $details;
    
    /**
     * Adds the given arbitrary data to the log's "details" field
     *
     * @param string $key the array key name to log with
     * @param mixed $data the data value to log
     * @see ActionLog::SetDetails()
     */
    public function LogDetails(string $key, $data) : self
    {
        $this->details[$key] = $data; return $this;
    }    
    
    /** Returns a direct reference to the "details" log */
    public function &GetDetailsRef() : array { return $this->details; }
    
    /**
     * Creates a new empty applog object and binds it to the current action log
     * @param ObjectDatabase $database database reference
     * @return static|NULL new entry or NULL if there's no current action log
     */
    public static function BaseRunCreate(ObjectDatabase $database) : ?self
    {
        if (($actlog = Main::GetInstance()->GetContext()->GetActionLog()) !== null)
        {
            $obj = parent::BaseCreate($database);
            
            $obj->actionlog = $actlog->SetApplog($obj);
            $obj->details = $actlog->GetDetails();
            
            return $obj;
        }
        else return null;
    }
    
    /** Send the cached details log to the action log */
    public function SaveDetails() : self
    {
        $k = Input::LoggerKey; if (isset($this->details[$k]) && empty($this->details[$k])) unset($this->details[$k]);
        
        if (!empty($this->details) && static::GetDetailsLevel())
            $this->GetActionLog()->SetDetails($this->details);
        
        return $this;
    }
    
    public function Save(bool $onlyMandatory = false) : self
    {        
        if (Main::GetInstance()->GetConfig()->GetEnableRequestLogDB()) 
            parent::Save(); // ignore $onlyMandatory
        
        return $this;
    }
    
    /** Returns the common CLI property usage for all app logs (joined to ActionLog) */
    public static function GetBasePropUsage() : string { return ActionLog::GetPropUsage(false); }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array
    {
        $q->Join($database, ActionLog::class, 'applog', static::class, 'id', null, static::class);
        
        return ActionLog::GetPropCriteria($database, $q, $input, false);
    }   
    
    /**
     * Returns the app-specific client object for this applog
     * @param bool $expand true if the user wants linked objects expanded, else just IDs
     */
    abstract public function GetClientObject(bool $expand = false) : array;    
    
    /**
     * Returns the full printable client object for this app log
     * @see BaseAppLog::GetClientObject()     
     * @return array BaseAppLog, add `{action:ActionLog}`
     * @see ActionLog::GetReqClientObject()
     */
    public function GetFullClientObject(bool $expand = false) : array
    {
        $retval = $this->GetClientObject($expand);
        
        $retval['action'] = $this->GetActionLog()->GetReqClientObject();
        
        return $retval;
    }
}
