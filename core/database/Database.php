<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class DatabaseReadOnlyException extends Exceptions\Client400Exception { public $message = "READ_ONLY_DATABASE"; }

interface Transactions { public function rollBack(); public function commit(); }

class Database implements Transactions {

    private $read_only = false;
    private $connection; 
    private $count_reads = 0;
    private $count_writes = 0;
    private $query_history = array();

    public function __construct()
    {
        $this->connection = new PDO(Config::CONNECT, Config::USERNAME, Config::PASSWORD,
            array(PDO::ATTR_PERSISTENT => Config::PERSISTENT, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    
    public function setReadOnly(bool $ro = true) : void { $this->read_only = $ro; }

    public function getReads() : int { return $this->count_reads; }
    public function getWrites() : int { return $this->count_writes; }
    public function getHistory(): array { return $this->query_history; }

    public function query(string $sql, ?array $data = null, bool $read = true) 
    {
        if (!$read && $this->read_only) throw new DatabaseReadOnlyException();
        
        array_push($this->query_history,$sql);
        
        if (!$this->connection->inTransaction()) { $this->connection->beginTransaction(); }
        
        $query = $this->connection->prepare($sql); $query->execute($data ?? array());

        if ($read) { $result = $query->fetchAll(PDO::FETCH_ASSOC); $this->count_reads++; } 
        else { $result = $query->rowCount(); $this->count_writes++; }       
        
        unset($query); return $result;    
    }       

    public function rollBack() 
    { 
        if ($this->connection->inTransaction()) 
            $this->connection->rollBack(); 
        $this->inTransaction = false; 
    }
    
    public function commit() 
    { 
        if ($this->connection->inTransaction()) 
            $this->connection->commit(); 
        $this->inTransaction = false; 
    }
}

?>