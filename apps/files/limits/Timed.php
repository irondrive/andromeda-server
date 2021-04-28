<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/files/limits/Base.php");
require_once(ROOT."/apps/files/limits/TimedStats.php");

/**
 * Stores limits whose statistics are specific to a given time period.
 * 
 * A timed limit is thus composed of both the limited object and the time period.
 *
 * This allows limiting things that cannot decrease unless reset, e.g. bandwidth.
 * Limited objects can have multiple limits here for different time periods.
 * For example a filesystem could have both an hourly and monthly bandwidth limit.
 * 
 * Keeps a history of statistics rather than just resetting them at the end
 * of the applicable time period - each entry is a TimedStats.
 */
abstract class Timed extends Base
{
    /** array<limited object ID, self[]> */
    protected static $cache = array();
    
    public static function GetDBClass() : string { return self::class; }

    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'stats' => (new FieldTypes\ObjectRefs(TimedStats::class, 'limitobj', true))->autoDelete(),
            'timeperiod' => null, // in seconds
            'max_stats_age' => null,
            'counters_limits__pubdownloads' => null,
            'counters_limits__bandwidth' => null
        ));
    }
    
    /** Returns the time period for this timed limit */
    public function GetTimePeriod() : int { return $this->GetScalar('timeperiod'); }
    
    /** Returns the maximum stats history age (-1 for forever) */
    public function GetMaxStatsAge() : int { return $this->TryGetScalar('max_stats_age') ?? -1; }
    
    protected function Initialize() : self { return $this; }
    
    /**
     * Returns all Timed Limits with the given time period
     * @param ObjectDatabase $database database reference
     * @param int $period time period
     * @param int $count max objects to load
     * @param int $offset offset for loading
     * @return array<string, Timed> limits indexed by ID
     */
    public static function LoadAllForPeriod(ObjectDatabase $database, int $period, ?int $count = null, ?int $offset = null) : array
    {
        $q = new QueryBuilder(); $w = $q->Equals('timeperiod',$period);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($count)->Offset($offset));
    }
    
    /**
     * Returns all timed limits for the given limited object (all time periods)
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj the limited object
     * @return array<string, Timed> limits indexed by ID
     */
    public static function LoadAllForClient(ObjectDatabase $database, StandardObject $obj) : array
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::LoadByObject($database, 'object', $obj, true);
        }
        
        return static::$cache[$obj->ID()];
    }
    
    /**
     * Loads the limit object for the given object and time period
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj the limited object
     * @param int $period the time period
     * @return self|NULL limit object or null if none
     */
    public static function LoadByClientAndPeriod(ObjectDatabase $database, StandardObject $obj, int $period) : ?self
    {
        foreach (static::LoadAllForClient($database, $obj) as $lim)
        {
            if ($lim->GetTimePeriod() === $period) return $lim;
        }
        return null;
    }    
    
    /** Deletes all limit objects corresponding to the given limited object */
    public static function DeleteByClient(ObjectDatabase $database, StandardObject $obj) : void
    {
        if (array_key_exists($obj->ID(), static::$cache)) static::$cache[$obj->ID()] = array();
        
        static::DeleteByObject($database, 'object', $obj, true);
    }
    
    /** Deletes all limit objects corresponding to the given limited object and time period */
    public static function DeleteByClientAndPeriod(ObjectDatabase $database, StandardObject $obj, int $period) : void
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('object',FieldTypes\ObjectPoly::GetObjectDBValue($obj)),$q->Equals('timeperiod',$period));
        
        static::DeleteByQuery($database, $q->Where($w));
    }
    
    /**
     * Creates and caches a new timed limit for the given object
     * @param ObjectDatabase $database database reference
     * @param StandardObject $obj object to limit
     * @param int $timeperiod time period for limit
     * @return self new limit object
     */
    protected static function CreateTimed(ObjectDatabase $database, StandardObject $obj, int $timeperiod) : self
    {
        $newobj = parent::BaseCreate($database)->SetObject('object',$obj)->SetScalar('timeperiod',$timeperiod);
        
        if (array_key_exists($obj->ID(),static::$cache))
        {
            static::$cache[$obj->ID()][] = $newobj;
        }
        else static::$cache[$obj->ID()] = array($newobj);
        
        return $newobj->Save();
    }

    /** Loads and returns the current stats for this limit */
    protected function GetCurrentStats() : TimedStats { return TimedStats::LoadCurrentByLimit($this->database, $this); }
    
    // pull counters from the current stats object
    protected function GetCounter(string $name) : int     { return $this->GetCurrentStats()->GetCounter($name); }
    protected function TryGetCounter(string $name) : ?int { return $this->GetCurrentStats()->TryGetCounter($name); }
    
    protected function DeltaCounter(string $name, int $delta = 1, bool $ignoreLimit = false) : self
    {
        $this->GetCurrentStats()->DeltaCounter($name, $delta, $ignoreLimit); return $this;        
    }
    
    /** Returns the command usage for SetTimedLimits() */
    public abstract static function GetTimedUsage() : string;
    
    /** Sets config for a timed limit */
    protected abstract function SetTimedLimits(Input $input) : void;
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage()." ".static::GetTimedUsage(); }
    
    public static function BaseConfigUsage() : string { return "--timeperiod int [--max_pubdownloads ?int] [--max_bandwidth ?int]"; }
    
    protected static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_UINT);
        
        $lim = static::LoadByClientAndPeriod($database, $obj, $period) ?? static::CreateTimed($database, $obj, $period);
        
        $lim->SetBaseLimits($input); $lim->SetTimedLimits($input);

        if ($input->HasParam('max_pubdownloads')) $lim->SetCounterLimit('pubdownloads', $input->GetNullParam('max_pubdownloads', SafeParam::TYPE_UINT));
        if ($input->HasParam('max_bandwidth')) $lim->SetCounterLimit('bandwidth', $input->GetNullParam('max_bandwidth', SafeParam::TYPE_UINT));
        
        if ($lim->isCreated()) $lim->Initialize();
        else TimedStats::PruneStatsByLimit($database, $lim);
        
        return $lim;
    }
    
    /**
     * Returns a printable client object of this timed limit
     * @return array `{timeperiod:int, dates:{created:float}, 
        features:{track_items:bool,track_dlstats:bool}, limits:{pubdownloads:?int, bandwidth:?int}`
     * @see TimedStats::GetClientObject()        
     */
    public function GetClientObject() : array
    {
        $data = parent::GetClientObject();
        
        $data['timeperiod'] = $this->GetTimePeriod();
        
        return $data;
    }
}
