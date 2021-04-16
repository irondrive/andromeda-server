<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/core/logging/BaseLog.php");
require_once(ROOT."/core/logging/ActionLog.php");

/**
 * Base class for extended app action logs
 * 
 * Performs a join with ActionLog when loading so that the user can
 * filter by app-specific and common action parameters simulataneously.
 */
abstract class BaseAppLog extends BaseLog
{    
    /**
     * Creates a new empty applog object and binds it to the current action log
     * @param ObjectDatabase $database database reference
     * @return self|NULL new entry or NULL if there's no current action log
     */
    public static function BaseRunCreate(ObjectDatabase $database) : ?self
    {
        if (($actlog = Main::GetInstance()->GetContext()->GetActionLog()) !== null)
        {
            $obj = parent::BaseCreate($database);
            
            $actlog->SetApplog($obj); return $obj;
        }
        else return null;
    }
    
    public function Save(bool $onlyMandatory = false) : self
    {
        if (Main::GetInstance()->GetConfig()->GetEnableRequestLogDB())
        {
            parent::Save($onlyMandatory);
        }
        
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
        
        $action = ActionLog::LoadByApplog($this->database, $this);
        $retval['action'] = $action->GetReqClientObject($expand);
        
        return $retval;
    }
}
