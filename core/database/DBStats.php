<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

/**
 * This class keeps track of performance metrics for the database.
 * 
 * Such metrics include read/write query count, and read/write time.
 */
class DBStats
{
    private int $reads = 0; 
    private int $writes = 0; 
    private float $read_time = 0; 
    private float $write_time = 0; 
    private array $queries = array();
    
    /** Constructs a new stats context and logs the current time */
    public function __construct(){ $this->start_time = hrtime(true); }

    /** Begins tracking a query by logging the current time */
    public function startQuery() : void { $this->tempt = hrtime(true); }

    /**
     * Ends tracking a query and updates the relevant stats
     * @param string $sql the query sent to the DB for history
     * @param int $type whether the query was a read or write (or both - bitset)
     * @param bool $count if false, log only the time spent and don't increment the query counters
     */
    public function endQuery(string $sql, int $type, bool $count = true) : void
    { 
        $el = (hrtime(true)-$this->tempt)/1e9;
        
        $isRead = $type & Database::QUERY_READ;
        $isWrite = $type & Database::QUERY_WRITE;
        
        if ($isRead && $isWrite) $el /= 2;
        
        if ($isRead) { $this->read_time += $el; if ($count) $this->reads++; }
        if ($isWrite) { $this->write_time += $el; if ($count) $this->writes++; }
     
        $this->queries[] = array('query'=>$sql, 'time'=>$el);
    }
    
    /** 
     * Returns the array of queries issued to the database 
     * @return string[]
     */
    public function getQueries() : array { return $this->queries; }
    
    /** Returns an array of statistics collected */
    public function getStats() : array
    {
        $totaltime = (hrtime(true)-$this->start_time)/1e9;
        $codetime = $totaltime - $this->read_time - $this->write_time;
        return array(
            'db_reads' => $this->reads,
            'db_read_time' => $this->read_time,
            'db_writes' => $this->writes,
            'db_write_time' => $this->write_time,
            'code_time' => $codetime,
            'total_time' => $totaltime,
            'queries' => $this->queries
        );
    }
    
    /** Adds another DBStats' stats to this one */
    public function Add(self $stats) : void
    {
        $this->reads += $stats->reads;
        $this->read_time += $stats->read_time;
        $this->writes += $stats->writes;
        $this->write_time += $stats->write_time;
    }
}
