<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

class DBStats
{
    private $reads = 0; private $writes = 0; private $read_time = 0; private $write_time = 0; private $queries = array();
    
    public function __construct(){ $this->start_time = microtime(true); }

    public function startQuery() : void { $this->temp = microtime(true); }
    public function endQuery(string $sql, int $type) : void
    { 
        $el = microtime(true) - $this->temp;
        
        $isRead = $type & Database::QUERY_READ;
        $isWrite = $type & Database::QUERY_WRITE;
        
        if ($isRead && $isWrite) $el /= 2;
        
        if ($isRead) { $this->read_time += $el; $this->reads++; }
        if ($isWrite) { $this->write_time += $el; $this->writes++; }
     
        array_push($this->queries, array('query'=>$sql, 'time'=>$el));
    }
    
    public function endCommit() : void
    {
        $el = microtime(true) - $this->temp;
        $this->write_time += $el;
    }
    
    public function getQueries() : array { return $this->queries; }
    
    public function getStats() : array
    {
        $totaltime = microtime(true) - $this->start_time;
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
    
    public function Add(self $stats) : void
    {
        $this->reads += $stats->reads;
        $this->read_time += $stats->read_time;
        $this->writes += $stats->writes;
        $this->write_time += $stats->write_time;
    }
}
