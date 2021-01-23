<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/files/limits/Base.php");
require_once(ROOT."/apps/files/limits/TimedStats.php");

abstract class Timed extends Base
{
    protected static $cache = array(); /* [objectID => self] */
    
    public static function GetDBClass() : string { return self::class; }

    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'stats' => new FieldTypes\ObjectRefs(TimedStats::class, 'limitobj', true),
            'timeperiod' => null,
            'max_stats_age' => null,
            'counters_limits__downloads' => null,
            'counters_limits__bandwidth' => null
        ));
    }
    
    public function GetTimePeriod() : int { return $this->GetScalar('timeperiod'); }    
    public function GetMaxStatsAge() : int { return $this->TryGetScalar('max_stats_age') ?? -1; }
    
    protected function Initialize() : self { return $this; }
    
    public static function LoadAllForPeriod(ObjectDatabase $database, int $period, ?int $count = null, ?int $offset = null) : array
    {
        $q = new QueryBuilder(); $w = $q->Equals('timeperiod',$period);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($count)->Offset($offset));
    }
    
    public static function LoadAllForClient(ObjectDatabase $database, StandardObject $obj) : array
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::LoadByObject($database, 'object', $obj, true);
        }
        
        return static::$cache[$obj->ID()];
    }
    
    public static function LoadByClientAndPeriod(ObjectDatabase $database, StandardObject $obj, int $period) : ?self
    {
        foreach (static::LoadAllForClient($database, $obj) as $lim)
        {
            if ($lim->GetTimePeriod() === $period) return $lim;
        }
        return null;
    }
    
    protected static function CreateTimed(ObjectDatabase $database, StandardObject $obj, int $timeperiod) : self
    {
        $newobj = parent::BaseCreate($database)->SetObject('object',$obj)->SetScalar('timeperiod',$timeperiod);
        
        if (array_key_exists($obj->ID(),static::$cache))
        {
            array_push(static::$cache[$obj->ID()], $newobj);
        }
        else static::$cache[$obj->ID()] = array($newobj);
        
        return $newobj->Save();
    }

    protected function GetCurrentStats() : TimedStats { return TimedStats::LoadCurrentByLimit($this->database, $this); }
    
    // pull counters from the current stats object
    protected function GetCounter(string $name) : int     { return $this->GetCurrentStats()->GetCounter($name); }
    protected function TryGetCounter(string $name) : ?int { return $this->GetCurrentStats()->TryGetCounter($name); }
    protected function ExistsCounter(string $name) : bool { return $this->GetCurrentStats()->ExistsCounter($name); }
    
    protected function DeltaCounter(string $name, int $delta = 1, bool $ignoreLimit = false) : self
    {
        $this->GetCurrentStats()->DeltaCounter($name, $delta, $ignoreLimit); return $this;        
    }
    
    public abstract static function GetTimedUsage() : string;
    protected abstract function SetTimedLimits(Input $input) : void;
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage()." ".static::GetTimedUsage(); }
    
    public static function BaseConfigUsage() : string { return "--timeperiod int [--max_downloads int] [--max_bandwidth int]"; }
    
    protected static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_INT);
        
        $lim = static::LoadByClientAndPeriod($database, $obj, $period) ?? static::CreateTimed($database, $obj, $period);
        
        $lim->SetBaseLimits($input); $lim->SetTimedLimits($input);

        if ($input->HasParam('max_downloads')) $lim->SetCounterLimit('downloads', $input->TryGetParam('max_downloads', SafeParam::TYPE_INT));
        if ($input->HasParam('max_bandwidth')) $lim->SetCounterLimit('bandwidth', $input->TryGetParam('max_bandwidth', SafeParam::TYPE_INT));
        
        if ($lim->isCreated()) $lim->Initialize();
        else TimedStats::PruneStatsByLimit($database, $lim);
        
        return $lim;
    }
    
    public function GetClientObject() : array
    {
        $data = parent::GetClientObject();
        
        $data['timeperiod'] = $this->GetTimePeriod();
        
        return $data;
    }
    
    public function Delete() : void
    {
        $this->DeleteObjectRefs('stats');

        parent::Delete();
    }
}
