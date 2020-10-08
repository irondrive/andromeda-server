<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class DatabaseReadOnlyException extends Exceptions\Client400Exception { public $message = "READ_ONLY_DATABASE"; }

interface Transactions { public function rollBack(); public function commit(); }

class DBStats
{
    private $reads = 0; private $writes = 0; private $read_time = 0; private $write_time = 0; private $queries = array();
    
    public function __construct(){ $this->start_time = microtime(true); }

    public function startQuery() : void { $this->temp = microtime(true); }
    public function endQuery(string $sql, bool $read) : void
    { 
        $el = microtime(true) - $this->temp;
        if ($read) $this->read_time += $el; else $this->write_time += $el;
        if ($read) $this->reads++; else $this->writes++;
     
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

class Database implements Transactions {

    private $connection; 
    private $read_only = false;    
    private $stats_stack = array();
    private $queries = array();
    
    public function __construct()
    {
        $this->connection = new PDO(Config::CONNECT, Config::USERNAME, Config::PASSWORD,
            array(PDO::ATTR_PERSISTENT => Config::PERSISTENT, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    
    public function setReadOnly(bool $ro = true) : self { $this->read_only = $ro; return $this; }

    public function query(string $sql, ?array $data = null, bool $read = true) 
    {
        if (!$read && $this->read_only) throw new DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        if (!$this->connection->inTransaction()) $this->connection->beginTransaction();
        
        $query = $this->connection->prepare($sql); $query->execute($data ?? array());
        
        if ($read) { $result = $query->fetchAll(PDO::FETCH_ASSOC); } 
        else { $result = $query->rowCount(); }  
        
        array_push($this->queries, $sql); $this->stopTimingQuery($sql, $read);

        unset($query); return $result;    
    }       

    public function rollBack() : void
    { 
        if ($this->connection->inTransaction())
        {
            $this->startTimingQuery();            
            $this->connection->rollback();
            $this->stopTimingCommit();
        }
        $this->inTransaction = false;
    }
    
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            $this->startTimingQuery();            
            $this->connection->commit();             
            $this->stopTimingCommit();
        }            
        $this->inTransaction = false;
    }
    
    private function startTimingQuery() : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->startQuery();
    }
    
    private function stopTimingQuery(string $sql, bool $read) : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->endQuery($sql, $read);
    }
    
    private function stopTimingCommit() : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->endCommit();
    }
    
    public function pushStatsContext() : self
    {
        array_push($this->stats_stack, new DBStats()); return $this;
    }

    public function popStatsContext() : ?DBStats
    {
        return array_pop($this->stats_stack);
    }
    
    public function getAllQueries() : array
    {
        return $this->queries;
    }
}

