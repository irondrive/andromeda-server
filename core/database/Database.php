<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

abstract class DatabaseException extends Exceptions\ServerException { }
class DatabaseConfigException extends DatabaseException { public $message = "DATABASE_NOT_CONFIGURED"; }

interface Transactions { public function rollBack(); public function commit(); }

class Database implements Transactions {

    private $connection; 
    private bool $read_only = false;
    
    private array $stats_stack = array();
    private array $queries = array();
    
    private const CONFIG_FILE = ROOT."/core/database/Config.php";
    
    public function __construct()
    {
        if (file_exists(self::CONFIG_FILE)) require_once(self::CONFIG_FILE);
        else throw new DatabaseConfigException();        
        
        $this->connection = new PDO(Config::CONNECT, Config::USERNAME, Config::PASSWORD,
            array(PDO::ATTR_PERSISTENT => Config::PERSISTENT, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    
    public static function Install(Input $input)
    {
        if (file_exists(self::CONFIG_FILE)) throw new DatabaseConfigException();
        
        $connect = $input->GetParam('connect',SafeParam::TYPE_TEXT);
        $username = $input->TryGetParam('dbuser',SafeParam::TYPE_NAME);
        $password = $input->TryGetParam('dbpass',SafeParam::TYPE_RAW);        
        $prefix = $input->TryGetParam('prefix',SafeParam::TYPE_ALPHANUM) ?? 'a2_';
        $persist = $input->TryGetParam('persistent',SafeParam::TYPE_BOOL) ?? false;
        
        $output = "<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }\n\n";
        
        $output .= "class Config {\n";
        
        $output .= "\tconst CONNECT = ".var_export($connect,true).";\n";
        $output .= "\tconst USERNAME = ".var_export($username,true).";\n";
        $output .= "\tconst PASSWORD = ".var_export($password,true).";\n";
        $output .= "\tconst PREFIX = ".var_export($prefix,true).";\n";
        $output .= "\tconst PERSISTENT = ".var_export($persist,true).";\n";
        
        $output .= "}";
        
        file_put_contents(self::CONFIG_FILE, $output);
        
        try { new Database(); } catch (\Throwable $e) { unlink(self::CONFIG_FILE); throw $e; }
    }
    
    public function setReadOnly(bool $ro = true) : self { $this->read_only = $ro; return $this; }
    
    public function importFile(string $path) : void
    {
        $lines = array_filter(file($path),function($line){ return substr($line,0,2) != "--"; });
        $queries = array_filter(explode(";",preg_replace( "/\r|\n/", "", implode("", $lines))));
        foreach ($queries as $query) $this->query(trim($query), 0);
    }
    
    const QUERY_READ = 1; const QUERY_WRITE = 2;

    public function query(string $sql, int $type, ?array $data = null) 
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
        
        if ($sql === 'COMMIT') { $this->commit(); $this->connection->beginTransaction(); }
        if ($sql === 'ROLLBACK') { $this->rollback(); $this->connection->beginTransaction(); }
        
        $this->stopTimingQuery($sql, $type);

        unset($query); return $result;    
    }       

    public function rollBack() : void
    { 
        if ($this->connection->inTransaction())
        {
            array_push($this->queries, "rollBack()");
            $this->startTimingQuery();            
            $this->connection->rollback();
            $this->stopTimingCommit();
        }
    }
    
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            array_push($this->queries, "commit()");
            $this->startTimingQuery();            
            $this->connection->commit();             
            $this->stopTimingCommit();
        }
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

