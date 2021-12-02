<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Database/Database.php"); use Andromeda\Core\Database\DatabaseException;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\Database\QueryBuilder;

/**
 * Stores an entry of statistics for a Timed limit
 *
 * Identified by the parent timed limit and the start of the time period.
 * Also can be thought of as limited object + time period + time start.
 * Only one stats object per limit is marked as current, other are history.
 * 
 * The counters stored are deltas over the applicable 
 * time period, not totals (they begin at zero)
 */
class TimedStats extends StandardObject
{    
    public static function GetDBClass() : string { return self::class; }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'limitobj' => new FieldTypes\ObjectPoly(Timed::class, 'stats'),
            'dates__timestart' => null,
            'iscurrent' => null,
            'counters__size' => new FieldTypes\Counter(),
            'counters__items' => new FieldTypes\Counter(),
            'counters__shares' => new FieldTypes\Counter(),
            'counters__pubdownloads' => new FieldTypes\Counter(),
            'counters__bandwidth' => new FieldTypes\Counter(true)
        ));
    }
    
    /** Returns the timed limit that owns this stats entry */
    protected function GetLimiter() : Timed { return $this->GetObject('limitobj'); }
    
    /** Returns the beginning of the time period for these stats */
    protected function GetTimeStart() : int { return $this->GetDate('timestart'); }
    
    /** Returns the time period length for the timed stats */
    protected function GetTimePeriod() : int { return $this->GetLimiter()->GetTimePeriod(); }   
    
    protected function isCurrent() : bool { return $this->TryGetScalar('iscurrent') ?? false; }

    /** array<limit ID, self> */
    private static $cache = array();
    
    /** Returns the current stats for the given limit, if it exists */
    private static function TryLoadCurrent(ObjectDatabase $database, Timed $limit) : ?self
    {
        $q = new QueryBuilder(); 
        
        $w = $q->And($q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit)),$q->IsTrue('iscurrent'));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /**
     * Returns (and creates) the current stats object for the given limit
     * @param ObjectDatabase $database object database
     * @param Timed $limit the limit object
     * @throws DatabaseException if a conflict creating a new stats object occurs
     * @return self current limit object
     */
    public static function LoadCurrentByLimit(ObjectDatabase $database, Timed $limit) : self
    {
        if (array_key_exists($limit->ID(), self::$cache))
            return self::$cache[$limit->ID()];

        static::PruneStatsByLimit($database, $limit);
        
        $obj = self::TryLoadCurrent($database, $limit);
        
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
                $obj = parent::BaseCreate($database)->SetObject('limitobj',$limit)
                    ->SetScalar('iscurrent',true)->SetDate('timestart',$start);
            }
            catch (DatabaseException $e) // someone may have already inserted a new time period, try loading again
            {
                if (($obj = self::TryLoadCurrent($database, $limit)) === null) throw $e;
            }
                
        }
            
        self::$cache[$limit->ID()] = $obj; return $obj;
    }
    
    /**
     * Prunes all stats history for the given limit that have expired
     * @param ObjectDatabase $database database reference
     * @param Timed $limit the limit object
     */
    public static function PruneStatsByLimit(ObjectDatabase $database, Timed $limit) : void
    {
        if ($limit->GetMaxStatsAge() < 0) return;
        
        $q = new QueryBuilder(); $minstart = Main::GetInstance()->GetTime() - $limit->GetMaxStatsAge();
        
        $w = $q->And($q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit)), $q->LessThan('dates__timestart',$minstart));
        
        static::DeleteByQuery($database, $q->Where($w));
    }
    
    /**
     * Loads all stats for the given limit object
     * @param ObjectDatabase $database databsse reference
     * @param Timed $limit limit object
     * @param int $count max number of stats rows
     * @param int $offset offset to load from
     * @return array<string, TimedStats> indexed by ID
     */
    public static function LoadAllByLimit(ObjectDatabase $database, Timed $limit, int $count = null, int $offset = null) : array
    {
        $q = new QueryBuilder();
        
        $w = $q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit));

        return static::LoadByQuery($database, $q->Where($w)->Limit($count)->Offset($offset));
    }
    
    /**
     * Loads the TimedStats for the given limit that overlaps the given time
     * @param ObjectDatabase $database database reference
     * @param Timed $limit limit to load stats for
     * @param int $time the time at which the stats were current
     * @return self|NULL load stats or null if none exist
     */
    public static function LoadByLimitAtTime(ObjectDatabase $database, Timed $limit, int $time) : ?self
    {
        $q = new QueryBuilder();
        
        $w = $q->Equals('limitobj',FieldTypes\ObjectPoly::GetObjectDBValue($limit));
        $w = $q->And($w, $q->LessThan('dates__timestart', $time + 1), 
                         $q->GreaterThan('dates__timestart', $time - $limit->GetTimePeriod()));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    // pull limits from the master Limits\Timed object
    protected function GetCounterLimit(string $name) : int     { return $this->GetLimiter()->GetCounterLimit($name); }
    protected function TryGetCounterLimit(string $name) : ?int { return $this->GetLimiter()->TryGetCounterLimit($name); }
    
    /**
     * Returns a printable client object of the stats
     * @return array `{iscurrent:bool, dates:{created:float, timestart:int}, 
        counters:{size:int, items:int, shares:int, pubdownloads:int, bandwidth:int}}`
     */
    public function GetClientObject() : array
    {
        $retval = array(
            'iscurrent' => $this->isCurrent(),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'timestart' => $this->GetTimeStart()
            ),
            'counters' => Utilities::array_map_keys(function($p){ return $this->GetCounter($p); },
                array('size','items','shares','bandwidth','pubdownloads'))
        );
        
        return $retval;
    }
}
