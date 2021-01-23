<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\DatabaseException;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\Database\QueryBuilder;

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
    
    protected function isCurrent() : bool { return $this->TryGetScalar('iscurrent') ?? false; }

    private static $cache = array();
    
    private static function TryLoadCurrent(ObjectDatabase $database, Timed $limit) : ?self
    {
        $q = new QueryBuilder(); 
        
        $w = $q->And($q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit)),$q->IsTrue('iscurrent'));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    public static function LoadCurrentByLimit(ObjectDatabase $database, Timed $limit) : self
    {
        if (array_key_exists($limit->ID(), static::$cache))
            return static::$cache[$limit->ID()];

        $obj = static::TryLoadCurrent($database, $limit);
        
        static::PruneStatsByLimit($database, $limit);
        
        $time = Main::GetInstance()->GetTime(); 
        
        if ($obj !== null)
        {            
            $start = $obj->GetTimeStart(); 
            $period = $obj->GetTimePeriod();
            
            $offset = intdiv($time-$start,$period)*$period;
            
            if ($offset !== 0) // need to create a new timeperiod
            {
                $obj->SetScalar('iscurrent', null);
                
                $start += $offset; $obj = null;
            }
        }
        else $start = $time;
        
        if ($obj === null)
        {
            try 
            { 
                $obj = parent::BaseCreate($database)->SetObject('limitobj',$limit)->SetScalar('iscurrent',true)
                    ->SetDate('timestart',$start)->SetScalar('timeperiod',$limit->GetTimePeriod());
            }
            catch (DatabaseException $e) // someone may have already inserted a new time period, try loading again
            {
                if (($obj = static::TryLoad($database, $limit)) === null) throw $e;
            }
                
        }
            
        static::$cache[$limit->ID()] = $obj; return $obj;
    }
    
    public static function PruneStatsByLimit(ObjectDatabase $database, Timed $limit) : void
    {
        if ($limit->GetMaxStatsAge() < 0) return;
        
        $q = new QueryBuilder(); $minstart = Main::GetInstance()->GetTime() - $limit->GetMaxStatsAge();
        
        $w = $q->And($q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit)), $q->LessThan('dates__timestart',$minstart));
        
        static::DeleteByQuery($database, $q->Where($w));
    }
    
    public static function LoadAllByLimit(ObjectDatabase $database, Timed $limit, int $count = null, int $offset = null) : array
    {
        $q = new QueryBuilder();
        
        $w = $q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit));

        return static::LoadByQuery($database, $q->Where($w)->Limit($count)->Offset($offset));
    }
    
    public static function LoadByLimitAtTime(ObjectDatabase $database, Timed $limit, int $time) : ?self
    {
        $q = new QueryBuilder();
        
        $w = $q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit));
        $w = $q->And($w, $q->LessThan('dates__timestart',$time+1), $q->GreaterThan('dates__timestart', $time-$limit->GetTimePeriod()));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    // pull limits from the master Limits\Timed object
    protected function GetCounterLimit(string $name) : int     { return $this->GetLimiter()->GetCounterLimit($name); }
    protected function TryGetCounterLimit(string $name) : ?int { return $this->GetLimiter()->TryGetCounterLimit($name); }
    protected function ExistsCounterLimit(string $name) : bool { return $this->GetLimiter()->ExistsCounterLimit($name); }
    
    public function GetClientObject() : array
    {
        return array(
            'iscurrent' => $this->isCurrent(),
            'timeperiod' => $this->GetTimePeriod(),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters()
        );
    }
}
