<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/files/limits/Total.php");

abstract class Timed extends Base
{
    protected static $cache = array(); /* [objectID => self] */
    
    public static function GetDBClass() : string { return self::class; }

    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'stats' => new FieldTypes\ObjectRefs(TimedStats::class, 'limit', true),
            'timeperiod' => null,
            'features__history' => null,
            'counters_limits__downloads' => null,
            'counters_limits__bandwidth' => null
        ));
    }
    
    public function GetTimePeriod() : int { return $this->GetScalar('timeperiod'); }    
    public function GetKeepHistory() : bool { return $this->GetFeature('history'); }    
    protected function SetKeepHistory(bool $keep) : self { return $this->SetFeature('history',$keep); }
    
    protected static function LoadAllForClient(ObjectDatabase $database, StandardObject $obj) : array
    {
        return static::BaseLoad($database, $obj);
    }
    
    protected static function LoadByClientAndPeriod(ObjectDatabase $database, StandardObject $obj, int $period) : ?self
    {
        foreach (static::LoadAllForClient($database, $obj) as $lim)
        {
            if ($lim->GetTimePeriod() === $period) return $lim;
        }
        return null;
    }
    
    protected static function BaseLoadFromDB(ObjectDatabase $database, StandardObject $obj) : array
    {
        return static::LoadByObject($database, 'object', $obj, true);
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

    protected function GetStats() : TimedStats { return TimedStats::LoadByLimit($this->database, $this); }
    
    // pull counters from the current stats object
    protected function GetCounter(string $name) : ?int    { return $this->GetStats()->GetCounter($name); }
    protected function TryGetCounter(string $name) : ?int { return $this->GetStats()->TryGetCounter($name); }
    protected function ExistsCounter(string $name) : ?int { return $this->GetStats()->ExistsCounter($name); }
    
    protected function DeltaCounter(string $name, int $delta = 1, bool $ignoreLimit = false) : self
    {
        $this->GetStats()->DeltaCounter($name, $delta, $ignoreLimit); return $this;        
    }
}

class TimedStats extends StandardObject
{    
    public static function GetDBClass() : string { return self::class; }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'limitobj' => new FieldTypes\ObjectPoly(Timed::class, 'stats'),
            'dates__timestart' => null,
            'timeperiod' => null,
            'iscurrent' => null,
            // these counters are deltas over the timeperiod, not totals
            'counters__size' => new FieldTypes\Counter(),
            'counters__items' => new FieldTypes\Counter(),
            'counters__shares' => new FieldTypes\Counter(),
            'counters__downloads' => new FieldTypes\Counter(),
            'counters__bandwidth' => new FieldTypes\Counter(true)
        ));
    }
    
    protected function GetLimiter() : Timed { return $this->GetObject('limitobj'); }    
    protected function GetTimeStart() : int { return $this->GetDate('timestart'); }
    protected function GetTimePeriod() : int { return $this->GetScalar('timeperiod'); }   

    private static $cache = array();
    
    public static function LoadByLimit(ObjectDatabase $database, Timed $limit) : self
    {
        if (array_key_exists($limit->ID(), static::$cache))
            return static::$cache[$limit->ID()];

        $q = new QueryBuilder(); $w = $q->And($q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit)),$q->IsTrue('iscurrent'));
        
        $obj = static::TryLoadUniqueByQuery($database, $q->Where($w));
        
        $time = Main::GetInstance()->GetTime(); 
        
        if ($obj !== null)
        {            
            $start = $obj->GetTimeStart(); 
            $period = $obj->GetTimePeriod();
            
            $offset = intdiv($time-$start,$period)*$period;
            
            if ($offset !== 0)
            {
                if (!$limit->GetKeepHistory()) $obj->Delete();
                else $obj->SetScalar('iscurrent',false);
                
                $start += $offset; $obj = null;
            }
        }
        else $start = $time;
        
        if ($obj === null)
        {
            $obj = parent::BaseCreate($database)->SetObject('limitobj',$limit)->SetScalar('iscurrent',true)
                ->SetDate('timestart',$start)->SetScalar('timeperiod',$limit->GetTimePeriod());
        }
            
        static::$cache[$limit->ID()] = $obj; return $obj;
    }
    
    // pull limits from the master Limits\Timed object
    protected function GetCounterLimit(string $name) : ?int    { return $this->GetLimiter()->GetCounterLimit($name); }
    protected function TryGetCounterLimit(string $name) : ?int { return $this->GetLimiter()->TryGetCounterLimit($name); }
    protected function ExistsCounterLimit(string $name) : ?int { return $this->GetLimiter()->ExistsCounterLimit($name); }
}
