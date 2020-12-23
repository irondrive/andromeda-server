<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, Transactions};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

abstract class DatabaseException extends Exceptions\ServerException { }
class DatabaseConfigException extends DatabaseException { public $message = "DATABASE_NOT_CONFIGURED"; }
class DatabaseErrorException extends DatabaseException { public $message = "DATABASE_ERROR"; }

class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

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
        
        if (file_exists($config)) $config = require($config);
        else throw new DatabaseConfigException();
        
        $this->config = $config;
        
        $this->connection = new PDO($config['CONNECT'], $config['USERNAME'], $config['PASSWORD'], array(
            PDO::ATTR_PERSISTENT => $config['PERSISTENT'] ?? false, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_AUTOCOMMIT => false
        ));
        
        if ($this->connection->inTransaction())
            $this->connection->rollBack();
    }

    public static function GetInstallUsage() : string { return "--connect text [--dbuser name] [--dbpass raw] [--persistent bool]"; }
    
    public static function Install(Input $input)
    {
        $params = var_export(array(
            'CONNECT' => $input->GetParam('connect',SafeParam::TYPE_TEXT),
            'USERNAME' => $input->TryGetParam('dbuser',SafeParam::TYPE_NAME),
            'PASSWORD' => $input->TryGetParam('dbpass',SafeParam::TYPE_RAW),
            'PERSISTENT' => $input->TryGetParam('persistent',SafeParam::TYPE_BOOL)
        ),true);
        
        $output = "<?php if (!defined('Andromeda')) die(); return $params;";
        
        $tmpnam = self::CONFIG_FILE.".tmp.php";
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

        if (!$this->connection->inTransaction()) 
            $this->beginTransaction();
        
        $this->startTimingQuery();

        array_push($this->queries, $sql); 
        
        try 
        {    
            if      ($sql === 'COMMIT') { $this->commit(); $result = null; }
            else if ($sql === 'ROLLBACK') { $this->rollback(); $result = null; }
            else
            {
                $query = $this->connection->prepare($sql); 
                $query->execute($data ?? array());
                
                if ($type & self::QUERY_READ) 
                    $result = $query->fetchAll(PDO::FETCH_ASSOC);
                else $result = $query->rowCount();
            }
        }
        catch (\PDOException $e)
        { 
            array_push($this->queries, $e->getMessage());
            throw DatabaseErrorException::Copy($e); 
        }
        
        $this->stopTimingQuery($sql, $type);

        return $result;    
    }
    
    public function beginTransaction() : void
    {
        if (!$this->connection->inTransaction())
        {
            $sql = "PDO->beginTransaction()";
            array_push($this->queries, $sql);
            $this->startTimingQuery();
            $this->connection->beginTransaction();
            $this->stopTimingQuery($sql, Database::QUERY_READ, false);
        }
    }

    public function rollBack() : void
    { 
        if ($this->connection->inTransaction())
        {
            $sql = "PDO->rollback()";
            array_push($this->queries, $sql);
            $this->startTimingQuery();            
            $this->connection->rollback();
            $this->stopTimingQuery($sql, Database::QUERY_WRITE, false);
        }
    }
    
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            $sql = "PDO->commit()";
            array_push($this->queries, $sql);
            $this->startTimingQuery();            
            $this->connection->commit();             
            $this->stopTimingQuery($sql, Database::QUERY_WRITE, false);
        }
    }
    
    private function startTimingQuery() : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->startQuery();
    }
    
    private function stopTimingQuery(string $sql, int $type, bool $count = true) : void
    {
        $s = Utilities::array_last($this->stats_stack);
        if ($s !== null) $s->endQuery($sql, $type, $count);
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

