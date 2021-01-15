<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

if (!class_exists('PDO')) die("PHP PDO Extension Required\n"); use \PDO;

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, Transactions};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

abstract class DatabaseException extends Exceptions\ServerException { }
class DatabaseConfigException extends DatabaseException { public $message = "DATABASE_NOT_CONFIGURED"; }
class InvalidDriverException extends DatabaseException { public $message = "PDO_UNKNOWN_DRIVER"; }
class DatabaseErrorException extends DatabaseException { public $message = "DATABASE_ERROR"; }

class DatabaseReadOnlyException extends Exceptions\ClientErrorException { public $message = "READ_ONLY_DATABASE"; }

class Database implements Transactions {

    private $connection; 
    protected array $config;
    protected int $driver;
    private bool $read_only = false;
    
    private array $stats_stack = array();
    private array $queries = array();
    
    private const CONFIG_FILE = ROOT."/core/database/Config.php";    
    
    public const DRIVER_MYSQL = 1; public const DRIVER_SQLITE = 2; public const DRIVER_POSTGRESQL = 3;
    
    private const DRIVERS = array('mysql'=>self::DRIVER_MYSQL,'sqlite'=>self::DRIVER_SQLITE,'pgsql'=>self::DRIVER_POSTGRESQL);
    
    public function getDriver() : int { return $this->driver; }
    
    public function __construct(?string $config = null)
    {
        $config ??= self::CONFIG_FILE;
        
        if (file_exists($config)) $config = require($config);
        else throw new DatabaseConfigException();
        
        $this->config = $config;
        
        if (!array_key_exists($config['DRIVER'], self::DRIVERS))
            throw new InvalidDriverException();
        
        $this->driver = self::DRIVERS[$config['DRIVER']];
        
        $connect = $config['DRIVER'].':'.$config['CONNECT'];
        
        $this->connection = new PDO($connect, 
            $config['USERNAME'] ?? null, 
            $config['PASSWORD'] ?? null, 
            array(
                PDO::ATTR_PERSISTENT => $config['PERSISTENT'] ?? false, 
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ));
        
        if ($this->connection->inTransaction())
            $this->connection->rollBack();
    }

    public static function GetInstallUsage() : string { return "--driver mysql|pgsql|sqlite [--outfile text]"; }
    
    public static function GetInstallUsages() : array
    {
        return array(
            "\t --driver mysql --dbname alphanum (--unix_socket text | (--host text [--port int])) [--dbuser name] [--dbpass raw] [--persistent bool]",
            "\t --driver pgsql --dbname alphanum --host text [--port int] [--dbuser name] [--dbpass raw] [--persistent bool]",
            "\t --driver sqlite --dbpath text"
        );
    }
    
    public static function Install(Input $input)
    {
        $driver = $input->GetParam('driver',SafeParam::TYPE_ALPHANUM,
            function($arg){ return array_key_exists($arg, self::DRIVERS); });
        
        $params = array('DRIVER'=>$driver);
        
        if ($driver === 'mysql' || $driver === 'pgsql')
        {
            $connect = "dbname=".$input->GetParam('dbname',SafeParam::TYPE_ALPHANUM);
            
            if ($driver === 'mysql' && $input->HasParam('unix_socket'))
            {
                $connect .= ";unix_socket=".$input->GetParam('unix_socket',SafeParam::TYPE_FSPATH);
            }
            else 
            {
                $connect .= ";host=".$input->GetParam('host',SafeParam::TYPE_HOSTNAME);
                
                $port = $input->TryGetParam('port',SafeParam::TYPE_INT);
                if ($port !== null) $connect .= ";port=$port";
            }
            
            if ($driver === 'mysql') $connect .= ";charset=utf8mb4";
            
            $params['CONNECT'] = $connect;
            
            $params['USERNAME'] = $input->TryGetParam('dbuser',SafeParam::TYPE_NAME);
            $params['PASSWORD'] = $input->TryGetParam('dbpass',SafeParam::TYPE_RAW);
            $params['PERSISTENT'] = $input->TryGetParam('persistent',SafeParam::TYPE_BOOL);
        }
        else if ($driver === 'sqlite')
        {
            $params['CONNECT'] = $input->GetParam('dbpath',SafeParam::TYPE_FSPATH);    
        }
        
        $params = var_export($params,true);
        
        $output = "<?php if (!defined('Andromeda')) die(); return $params;";
        
        $outnam = $input->TryGetParam('outfile',SafeParam::TYPE_TEXT) ?? self::CONFIG_FILE;
        
        $tmpnam = "$outnam.tmp.php";
        file_put_contents($tmpnam, $output);
        
        try { new Database($tmpnam); } catch (\Throwable $e) {
            unlink($tmpnam); throw $e; }
        
        rename($tmpnam, $outnam);
    }
    
    public function getConfig() : array
    {
        $config = $this->config;
        
        if ($config['PASSWORD'] ?? null) $config['PASSWORD'] = true;
        
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
    
    public function importTemplate(string $path) : void { $this->importFile("$path/andromeda2.".$this->config['DRIVER'].".sql"); }
    
    public function importFile(string $path) : void
    {
        $lines = array_filter(file($path),function($line){ return mb_substr($line,0,2) != "--"; });
        $queries = array_filter(explode(";",preg_replace( "/\r|\n/", "", implode("", $lines))));
        foreach ($queries as $query) $this->query(trim($query), 0);
    }
    
    // some drivers require tweaked behavior
    protected function SupportsRETURNING() : bool { return in_array($this->getDriver(), array(self::DRIVER_MYSQL, self::DRIVER_POSTGRESQL)); }
    protected function RequiresSAVEPOINT() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    protected function BinaryAsStreams() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    protected function BinaryEscapeInput() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    protected function UsePublicSchema() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    const QUERY_READ = 1; const QUERY_WRITE = 2;

    public function query(string $sql, int $type, ?array $data = null) 
    {
        if ($type & self::QUERY_WRITE && $this->read_only) throw new DatabaseReadOnlyException();

        if (!$this->connection->inTransaction()) 
            $this->beginTransaction();
        
        $this->startTimingQuery();

        array_push($this->queries, $sql);
        
        $doSavepoint = false;
        
        try 
        {           
            if      ($sql === 'COMMIT') { $this->commit(); $result = null; }
            else if ($sql === 'ROLLBACK') { $this->rollback(); $result = null; }
            else
            {
                if ($this->RequiresSAVEPOINT())
                {
                    $doSavepoint = true;
                    $this->connection->query("SAVEPOINT mysave");
                }
                
                if ($this->BinaryEscapeInput() && $data !== null)
                {
                    foreach ($data as &$value)
                    {
                        if (!mb_check_encoding($value,'UTF-8'))
                        {
                            $value = pg_escape_bytea($value);
                        }
                    }
                }
                
                $query = $this->connection->prepare($sql);
                
                $query->execute($data ?? array());
                
                if ($type & self::QUERY_READ)
                {
                    $result = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($this->BinaryAsStreams()) $this->fetchStreams($result);
                }                    
                else $result = $query->rowCount();
                
                if ($doSavepoint)
                    $this->connection->query("RELEASE SAVEPOINT mysave");
            }
        }
        catch (\PDOException $e)
        {
            if ($doSavepoint)
                $this->connection->query("ROLLBACK TO SAVEPOINT mysave");
                
            $idx = count($this->queries)-1;
            $this->queries[$idx] = array($this->queries[$idx], $e->getMessage());
            throw DatabaseErrorException::Copy($e); 
        }
        
        $this->stopTimingQuery($sql, $type);

        return $result;    
    }
    
    private function fetchStreams(array &$rows) : array
    {
        foreach ($rows as &$row)
        {
            foreach ($row as &$value)
            {
                if (is_resource($value))
                    $value = stream_get_contents($value);
            }
        }
        return $rows;
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

