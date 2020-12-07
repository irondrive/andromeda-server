<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, Transactions};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

abstract class DatabaseException extends Exceptions\ServerException { }

class DatabaseConfigException extends DatabaseException { public $message = "DATABASE_NOT_CONFIGURED"; }

class Database implements Transactions {

    private $connection; 
    protected array $config;
    private bool $read_only = false;
    
    private array $stats_stack = array();
    private array $queries = array();
    
    private const CONFIG_FILE = ROOT."/core/database/Config.php";
    
    public function __construct(?string $config = null)
    {
        $config ??= self::CONFIG_FILE;
        
        if (file_exists(self::CONFIG_FILE)) $config = require($config);
        else throw new DatabaseConfigException();        
        
        $this->config = $config;
        
        $this->connection = new PDO($config['CONNECT'], $config['USERNAME'], $config['PASSWORD'],
            array(PDO::ATTR_PERSISTENT => $config['PERSISTENT'], PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    }
    
    public static function Install(Input $input)
    {
        $connect = $input->GetParam('connect',SafeParam::TYPE_TEXT);
        $username = $input->TryGetParam('dbuser',SafeParam::TYPE_NAME);
        $password = $input->TryGetParam('dbpass',SafeParam::TYPE_RAW);
        $persist = $input->TryGetParam('persistent',SafeParam::TYPE_BOOL) ?? false;
        
        $output = "<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }\n\n";
        
        $output .= "return array(\n";
        
        $params = array(
            "\t'CONNECT' => ".var_export($connect,true),
            "\t'USERNAME' => ".var_export($username,true),
            "\t'PASSWORD' => ".var_export($password,true),
            "\t'PERSISTENT' => ".var_export($persist,true)
        );
        
        $output .= implode(",\n",$params)."\n);";
        
        $tmpnam = self::CONFIG_FILE.".tmp";
        file_put_contents($tmpnam, $output);
        
        try { new Database($tmpnam); } catch (\Throwable $e) { 
            unlink($tmpnam); throw $e; }
        
        rename($tmpnam, self::CONFIG_FILE);
    }
    
    public function getConfig() : array
    {
        $config = $this->config;
        $config['PASSWORD'] = boolval($config['PASSWORD']);
        return $config;
    }
    
    public function getInfo() : array
    {
        return array(
            'driver' => $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME),
            'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO)
        );
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

