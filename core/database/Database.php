<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class DatabaseReadOnlyException extends Exceptions\Client400Exception { public $message = "READ_ONLY_DATABASE"; }

interface Transactions { public function rollBack(); public function commit(); }

class DBStats
{
    private $reads = 0; private $writes = 0; private $read_time = 0; private $write_time = 0; private $temp = 0;
    
    public function getReads() : int        { return $this->reads; }
    public function getWrites() : int       { return $this->writes; }
    public function getReadTime() : float   { return $this->read_time; }
    public function getWriteTime() : float  { return $this->write_time; }
    
    public function startTiming()                   { $this->temp = microtime(true); }
    public function endRead()                       { $this->read_time += microtime(true) - $this->temp; $this->reads++; }
    public function endWrite(bool $count = true)    { $this->write_time += microtime(true) - $this->temp; if ($count) $this->writes++; }
}

class Database implements Transactions {

    private $connection; 
    private $read_only = false;
    
    private $stats_stack = array();
    private $query_history = array();
    
    public function __construct()
    {
        $this->connection = new PDO(Config::CONNECT, Config::USERNAME, Config::PASSWORD,
            array(PDO::ATTR_PERSISTENT => Config::PERSISTENT, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    
    public function setReadOnly(bool $ro = true) : self { $this->read_only = $ro; return $this; }

    public function query(string $sql, ?array $data = null, bool $read = true) 
    {
        if (!$read && $this->read_only) throw new DatabaseReadOnlyException();
        
        $stats = $this->getStatsContext();
        if ($stats !== null) $stats->startTiming();

        array_push($this->query_history,$sql);
        
        if (!$this->connection->inTransaction()) { $this->connection->beginTransaction(); }
        
        $query = $this->connection->prepare($sql); $query->execute($data ?? array());

        if ($read) { $result = $query->fetchAll(PDO::FETCH_ASSOC); } 
        else { $result = $query->rowCount(); }  
        
        if ($stats !== null) { if ($read) $stats->endRead(); else $stats->endWrite(); }

        unset($query); return $result;    
    }       

    public function rollBack() : void
    { 
        if ($this->connection->inTransaction())
        {
            $stats = $this->getStatsContext();
            if ($stats !== null) $stats->startTiming();
            
            $this->connection->rollback();
            
            if ($stats !== null) $stats->endWrite(false);
        }
        $this->inTransaction = false;
    }
    
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            $stats = $this->getStatsContext();
            if ($stats !== null) $stats->startTiming();
            
            $this->connection->commit(); 
            
            if ($stats !== null) $stats->endWrite(false);
        }            
        $this->inTransaction = false;
    }
    
    public function startStatsContext() : self
    {
        array_push($this->stats_stack, new DBStats()); return $this;
    }

    public function getStatsContext() : ?DBStats
    {
        $size = count($this->stats_stack);
        return $size ? $this->stats_stack[$size-1] : null;
    }
    
    public function getHistory(): array { return $this->query_history; }
}

