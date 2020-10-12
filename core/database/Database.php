<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/DBStats.php"); use Andromeda\Core\Database\DBStats;

class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

interface Transactions { public function rollBack(); public function commit(); }

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
    
    const QUERY_READ = 1; const QUERY_WRITE = 2;

    public function query(string $sql, ?array $data = null, int $type) 
    {
        if ($type & self::QUERY_WRITE && $this->read_only) throw new DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        if (!$this->connection->inTransaction()) 
        {
            array_push($this->queries, "beginTransaction()");
            $this->connection->beginTransaction();
        }
        
        array_push($this->queries, $sql); 
        
        $query = $this->connection->prepare($sql); 
        $query->execute($data ?? array());
        
        if ($type & self::QUERY_READ) 
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        else $result = $query->rowCount();
        
        $this->stopTimingQuery($sql, $type);

        unset($query); return $result;    
    }       

    public function rollBack() : void
    { 
        array_push($this->queries, "rollBack()");
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
        array_push($this->queries, "commit()");
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
    
    private function stopTimingQuery(string $sql, int $type) : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->endQuery($sql, $type);
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

