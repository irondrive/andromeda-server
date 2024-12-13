<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Exceptions as CoreExceptions;
use Andromeda\Core\Errors\BaseExceptions;
use Andromeda\Core\IOFormat\SafeParams;

if (!class_exists('PDO'))
    throw new CoreExceptions\MissingExtensionException('PDO');

use PDO; use PDOStatement; use PDOException;

/**
 * This class implements the PDO database abstraction.
 * 
 * Manages connecting to the database, installing config, abstracting some driver 
 * differences, and logging queries and performance statistics.  Queries are always
 * made as part of a transaction, and always used as prepared statements. Performance 
 * statistics and queries are tracked as a stack, but transactions cannot be nested.
 * Queries made must always be compatible with all supported drivers.
 * @phpstan-type PDODatabaseInfoJ array{driver:string, cversion:string, sversion:string, sinfo?:string}
 * @phpstan-type PDODatabaseConfigJ array{DRIVER:string, CONNECT:string, PERSISTENT:bool, USERNAME?:string, PASSWORD?:true}
 */
class PDODatabase
{
    /** the PDO database connection */
    private PDO $connection; 
    
    /** 
     * $config associative array of config for connecting PDO 
     * @var array{DRIVER:string, CONNECT:string, PERSISTENT:bool, USERNAME?:string, PASSWORD?:string}
     */
    private array $config;
    
    /** The enum value of the driver being used */
    private int $driver;
    
    /** if true, don't allow writes */
    private bool $read_only = false;
    
    /** The current DBStats measure context */
    private ?DBStats $stats = null;
    
    /** 
     * global history of SQL queries sent to the DB (not a stack), possibly with errors 
     * @var array<string|array{query:string,error:string}>
     */
    private array $queries = array();
    
    /** 
     * the default path for storing the config file
     * @var list<?string>
     */
    private const CONFIG_PATHS = array(
        ROOT."/DBConfig.php",
        null, // ~/.config/andromeda-server/DBConfig.php
        '/usr/local/etc/andromeda-server/DBConfig.php',
        '/etc/andromeda-server/DBConfig.php'
    );

    public const DRIVER_MYSQL = 1; 
    public const DRIVER_SQLITE = 2; 
    public const DRIVER_POSTGRESQL = 3;
    
    private const DRIVERS = array(
        'mysql'=>self::DRIVER_MYSQL,
        'sqlite'=>self::DRIVER_SQLITE,
        'pgsql'=>self::DRIVER_POSTGRESQL);
    
    /**
     * Loads a config file path into an array of config generated by Install()
     * @param ?string $path the path to the config file to use, null for defaults
     * @throws Exceptions\DatabaseMissingException if the given path does not exist
     * @throws Exceptions\DatabaseConfigException if the given file does not return an array
     * @return array<mixed>
     */
    public static function LoadConfig(?string $path = null) : array
    {
        if ($path !== null)
        {
            if (!is_file($path))
                throw new Exceptions\DatabaseMissingException();
        }
        else foreach (self::CONFIG_PATHS as $ipath) // search
        {
            if ($ipath === null)
            {
                if (isset($_ENV['HOME'])) $home = $_ENV['HOME']; // Linux
                else if (isset($_SERVER['HOMEDRIVE']) && isset($_SERVER['HOMEPATH']))
                    $home = $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH']; // Windows

                if (isset($home) && is_string($home)) 
                    $ipath = "$home/.config/andromeda-server/DBConfig.php";
            }
            
            if ($ipath !== null && is_file($ipath)) {
                $path = $ipath; break; }
        }
        
        if ($path === null)
            throw new Exceptions\DatabaseMissingException();

        $retval = require($path);
        if (is_array($retval)) return $retval;
        else throw new Exceptions\DatabaseConfigException('not array');
    }

    /** Returns a string with the primary CLI usage for Install() */
    public static function GetInstallUsage() : string { return "--driver mysql|pgsql|sqlite --outfile [fspath|-]"; }
    
    /** 
     * Returns the CLI usages specific to each driver 
     * @return list<string>
     */
    public static function GetInstallUsages() : array
    {
        return array(
            "--driver mysql --dbname alphanum (--unix_socket fspath | (--host hostname [--port uint16])) [--dbuser name] [--dbpass raw] [--persistent bool]",
            "--driver pgsql --dbname alphanum --host hostname [--port ?uint16] [--dbuser ?name] [--dbpass ?raw] [--persistent ?bool]",
            "--driver sqlite --dbpath fspath"
        );
    }
    
    /**
     * Creates and tests a new database config from the given user input
     * @param SafeParams $params input parameters
     * @see self::GetInstallUsage()
     * @throws Exceptions\DatabaseInstallException if the database config is not valid and PDO fails
     * @return ?string the database config file contents if no outfile
     */
    public static function Install(SafeParams $params) : ?string
    {
        $driver = $params->GetParam('driver')->FromAllowlist(array_keys(self::DRIVERS));
        
        $config = array('DRIVER'=>$driver);
        
        if ($driver === 'mysql' || $driver === 'pgsql')
        {
            $connect = "dbname=".$params->GetParam('dbname')->GetAlphanum();
            
            if ($driver === 'mysql' && $params->HasParam('unix_socket'))
            {
                $connect .= ";unix_socket=".$params->GetParam('unix_socket')->GetFSPath();
            }
            else 
            {
                $connect .= ";host=".$params->GetParam('host')->GetHostname();
                
                if (($port = $params->GetOptParam('port',null)->GetNullUint16()) !== null)
                {
                    $connect .= ";port=$port";
                }
            }
            
            if ($driver === 'mysql') $connect .= ";charset=utf8mb4";
            
            $config['CONNECT'] = $connect;
            $config['PERSISTENT'] = $params->GetOptParam('persistent',null)->GetNullBool();
            $config['USERNAME'] = $params->GetOptParam('dbuser',null)->GetNullName();
            $config['PASSWORD'] = $params->GetOptParam('dbpass',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString();
        }
        else if ($driver === 'sqlite') // @phpstan-ignore-line harmless always true (for now)
        {
            $fullpath = $params->GetParam('dbpath')->GetFSPath();
            if (is_dir($fullpath)) $fullpath .= "/";
            if (mb_substr($fullpath,-1) === "/") 
                $fullpath .= "andromeda-server.s3db"; // default

            if (($dirname = realpath(dirname($fullpath))) === false)
                throw new Exceptions\DatabasePathException($fullpath);

            $config['CONNECT'] = $dirname.'/'.basename($fullpath);
        }
        
        // test config
        try { new self($config, true); }
        catch (Exceptions\PDODatabaseConnectException $e) {
            throw new Exceptions\DatabaseInstallException($e); 
        }
        
        $output = "<?php if (!defined('Andromeda')) die(); return ".var_export($config,true).";";
        $outnam = $params->GetParam('outfile')->GetNullFSPath() ?? self::CONFIG_PATHS[0];

        if ($outnam === "-") // return directly
            return $output;
        else 
        {
            if (is_dir($outnam)) $outnam .= "/";
            if (mb_substr($outnam,-1) === "/")
                $outnam .= "DBConfig.php"; // default

            try { file_put_contents($outnam, $output); }
            catch (BaseExceptions\PHPError $e) {
                throw new Exceptions\DatabasePathException($outnam); }
            return null;
        }
    }
    
    /**
     * Constructs the database and initializes the PDO connection, and adds a stats context
     * @param array<mixed> $config the associative array of config generated by Install()
     * @param bool $verbose if true, show the PDO error if the connection fails
     * @param ?DBStats $init_stats optional stats tracking for init
     * @throws Exceptions\DatabaseConfigException if the driver in the config is invalid
     * @throws Exceptions\PDODatabaseConnectException if the connection fails
     */
    public function __construct(array $config, bool $verbose = false, ?DBStats $init_stats = null)
    {
        if ($init_stats !== null)
            $this->stats = $init_stats;
        
        if (!array_key_exists('DRIVER',$config) || !is_string($config['DRIVER']))
            throw new Exceptions\DatabaseConfigException('missing DRIVER');
        if (!array_key_exists('CONNECT',$config) || !is_string($config['CONNECT']))
            throw new Exceptions\DatabaseConfigException('missing CONNECT');
        
        $this->config = array(
            'DRIVER' => $config['DRIVER'], 
            'CONNECT' => $config['CONNECT'],
            'PERSISTENT' => array_key_exists('PERSISTENT',$config) ? (bool)$config['PERSISTENT'] : false
        );
        if (array_key_exists('USERNAME',$config) && is_string($config['USERNAME'])) 
            $this->config['USERNAME'] = $config['USERNAME'];
        if (array_key_exists('PASSWORD',$config) && is_string($config['PASSWORD'])) 
            $this->config['PASSWORD'] = $config['PASSWORD'];

        $driver = $this->config['DRIVER'];
        if (!array_key_exists($driver, self::DRIVERS))
            throw new Exceptions\DatabaseConfigException("driver $driver");
        
        $this->driver = self::DRIVERS[$driver];
        $connect = $driver.':'.$this->config['CONNECT'];
        
        try
        {
            $options = array(
                PDO::ATTR_PERSISTENT => $this->config['PERSISTENT'],
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false
            );
            
            // match rowCount behavior of postgres and sqlite
            if ($this->driver === self::DRIVER_MYSQL)
                $options[PDO::MYSQL_ATTR_FOUND_ROWS] = true;
                
            $username = $this->config['USERNAME'] ?? null;
            $password = $this->config['PASSWORD'] ?? null;

            $this->connection = new PDO($connect, $username, $password, $options);
        }
        catch (PDOException $e){ throw new Exceptions\PDODatabaseConnectException($verbose ? $e : null); }
        
        if ($this->connection->inTransaction())
            $this->connection->rollBack();
            
        if ($this->driver === self::DRIVER_SQLITE)
            $this->connection->exec(
                "PRAGMA foreign_keys = true;".
                "PRAGMA trusted_schema = false;".
                "PRAGMA journal_mode = WAL;"); // https://www.sqlite.org/wal.html
    }
    
    /** @see self::$driver */
    public function getDriver() : int { return $this->driver; }
    
    /** 
     * returns the array of config that was loaded from the config file 
     * @return PDODatabaseConfigJ
     */
    public function GetConfig() : array
    {
        $config = $this->config;
        
        if (array_key_exists('PASSWORD',$config))
            $config['PASSWORD'] = true;
        
        return $config;
    }
    
    /**
     * returns an array with some PDO attributes for debugging 
     * @return PDODatabaseInfoJ
     */
    public function getInfo() : array
    {
        $retval = array(
            'driver' => $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME),
            'cversion' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'sversion' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION)
        );
        
        if ($this->getDriver() !== self::DRIVER_SQLITE)
            $retval['sinfo'] = $this->connection->getAttribute(PDO::ATTR_SERVER_INFO);
        
        return $retval; // @phpstan-ignore-line getAttribute returns scalar not mixed
    }
    
    /**
     * Sets the database as writeable or readonly
     * @param bool $ro if true, set as readonly
     * @return $this
     */
    public function SetReadOnly(bool $ro = true) : self { $this->read_only = $ro; return $this; }
    
    /** Returns true if the database is read-only */
    public function isReadOnly() : bool { return $this->read_only; }
    
    /**
     * Imports the appropriate SQL template file for an app
     * @param string $path the base path containing the templates
     */
    public function importTemplate(string $path) : self { 
        return $this->importFile($path."/andromeda.".$this->config['DRIVER'].".sql"); }
    
    /**
     * Parses and imports an SQL file into the database
     * @param string $path the path of the SQL file
     * @throws Exceptions\ImportFileMissingException if path does not exist
     */
    public function importFile(string $path) : self
    {
        if (($data = file($path)) === false) 
            throw new Exceptions\ImportFileMissingException($path);
        
        // get rid of comment lines
        $lines = array_filter($data,function(string $line){ 
            return mb_substr($line,0,2) !== "--"; });
        
        // separate queries by ; (end of query)
        $queries = explode(";", implode($lines));
        
        // trim queries and empty lines
        $queries = array_filter(array_map(function(string $query){ 
            return trim($query); }, $queries));
        
        foreach ($queries as $query) $this->query($query); return $this;
    }
    
    /** Whether or not the DB aborts transactions after an error and requires use of SAVEPOINTs */
    private function RequiresSAVEPOINT() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB fetches binary/blob fields as streams rather than scalars */
    private function BinaryAsStreams() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB requires binary input to be escaped */
    private function BinaryEscapeInput() : bool { return $this->getDriver() === self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the DB expects using public. as a prefix for table names */
    public function UsePublicSchema() : bool   { return $this->getDriver() === self::DRIVER_POSTGRESQL; }

    /** Returns true if the DB quotes column names with ` instead of " */
    public function UseBacktickQuotes() : bool { return $this->getDriver() === self::DRIVER_MYSQL; }

    /** Returns true if "SELECT COUNT(test) returns a column called COUNT(test) rather than "count" */
    public function FullSelectFields() : bool { return $this->getDriver() !== self::DRIVER_POSTGRESQL; }
    
    /** Whether or not the returned data rows are always string values (false if they are proper types) */
    public function DataAlwaysStrings() : bool { return $this->getDriver() !== self::DRIVER_MYSQL; }
    
    /** Returns the given arguments concatenated in SQL */
    public function SQLConcat(string ...$args) : string
    {
        if ($this->getDriver() === self::DRIVER_MYSQL)
        {
            return "CONCAT(".implode(',',$args).")";
        }
        else return implode(' || ',$args);
    }

    /** Returns true if the DB is currently in a transaction */
    public function inTransaction() : bool { return $this->connection->inTransaction(); }

    /**
     * Sends an SQL read query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, scalar> $data associative array of data replacements for the prepared statement
     * @return array<array<string, ?scalar>> an associative array of the query results - results MAY be all strings!
     * @throws Exceptions\DatabaseFetchException if the row fetch fails
     * @see self::query()
     */
    public function read(string $sql, ?array $data = null) : array
    {
        $this->startTimingQuery();
        
        if ($this->UseBacktickQuotes())
            $sql = str_replace('"','`',$sql);
        
        $query = $this->query($sql, $data);

        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) // @phpstan-ignore-line PHP8 never returns false
            throw new Exceptions\DatabaseFetchException();

        if ($this->BinaryAsStreams()) $this->fetchStreams($result);
        
        $this->stopTimingQuery($sql, DBStats::QUERY_READ);
        
        return $result; // @phpstan-ignore-line fetchAll is missing detailed type
    }
    
    /**
     * Sends an SQL write query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, scalar> $data associative array of data replacements for the prepared statement
     * @return int count of matched objects (not count of modified!)
     * @throws Exceptions\DatabaseReadOnlyException if the DB is read-only
     * @see self::query()
     */
    public function write(string $sql, ?array $data = null) : int
    {        
        if ($this->read_only) throw new Exceptions\DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        if ($this->UseBacktickQuotes())
            $sql = str_replace('"','`',$sql);
        
        $query = $this->query($sql, $data);
        
        $result = $query->rowCount();
        
        $this->stopTimingQuery($sql, DBStats::QUERY_WRITE);
        
        return $result;
    }
    
    /**
     * Sends an SQL read+write query down to the database
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, scalar> $data associative array of data replacements for the prepared statement
     * @return array<array<string, ?scalar>> an associative array of the query results - results MAY be all strings!
     * @throws Exceptions\DatabaseReadOnlyException if the DB is read-only
     * @throws Exceptions\DatabaseFetchException if the row fetch fails
     * @see self::query()
     */
    public function readwrite(string $sql, ?array $data = null) : array
    {
        if ($this->read_only) throw new Exceptions\DatabaseReadOnlyException();
        
        $this->startTimingQuery();
        
        if ($this->UseBacktickQuotes())
            $sql = str_replace('"','`',$sql);
        
        $query = $this->query($sql, $data);
        
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) // @phpstan-ignore-line PHP8 never returns false
            throw new Exceptions\DatabaseFetchException();
        
        if ($this->BinaryAsStreams()) $this->fetchStreams($result);
        
        $this->stopTimingQuery($sql, DBStats::QUERY_READ | DBStats::QUERY_WRITE);
        
        return $result; // @phpstan-ignore-line fetchAll is missing detailed type
    }

    /**
     * Sends an SQL query down to the database, possibly beginning a transaction
     * @param string $sql the SQL query string, with placeholder data values
     * @param ?array<string, scalar> $params param replacements for the prepared statement
     * @throws Exceptions\DatabaseQueryException if the database query throws a PDOException
     * @return PDOStatement the finished PDO statement object
     */
    protected function query(string $sql, ?array $params = null) : PDOStatement
    {
        if (!$this->connection->inTransaction())
            $this->beginTransaction();
            
        $logged = $this->logQuery($sql, $params);
        
        $doSavepoint = $this->RequiresSAVEPOINT();

        if ($this->BinaryEscapeInput() && $params !== null)
        {
            foreach ($params as &$value)
            {
                if (is_string($value) && !Utilities::isUTF8($value))
                    $value = pg_escape_bytea($value);
            }
        }

        try
        {
            if ($doSavepoint)
                $this->connection->exec("SAVEPOINT a2save");

            $query = $this->connection->prepare($sql);
            assert($query !== false); // phpstan (we are in exception mode)

            $query->execute($params ?? array());
            // ignore return value as we are in exception mode

            if ($doSavepoint)
                $this->connection->exec("RELEASE SAVEPOINT a2save");

            return $query;
        }
        catch (PDOException $e)
        {
            if ($doSavepoint)
                $this->connection->exec("ROLLBACK TO SAVEPOINT a2save");
            
            $this->queries[count($this->queries)-1] = 
                array('query'=>$logged, 'error'=>$e->getMessage());

            $eclass = substr((string)$e->getCode(),0,2);
            
            if ($eclass === '23') // SQL 23XXX
                throw new Exceptions\DatabaseIntegrityException($e);
            else throw new Exceptions\DatabaseQueryException($e); 
        }
    }
    
    private bool $logValues = false;

    /** Sets logValues - if true, log DB query input values (not just placeholders) */
    public function SetLogValues(bool $logValues) : self
    {
        $this->logValues = $logValues; return $this;
    }
    
    /** 
     * Logs a query to the internal query history, logging the actual data values if debug allows 
     * @param ?array<string, scalar> $params
     */
    private function logQuery(string $sql, ?array $params) : string
    {
        if ($params !== null && $this->logValues)
        {
            foreach ($params as $key=>$val)
            {
                if (is_string($val))
                {
                    if (!Utilities::isUTF8($val))
                        $val = 'b64:('.base64_encode($val).')';
                    $val = str_replace('\\','\\\\',"'$val'");
                }

                $sql = Utilities::replace_first(":$key", (string)$val, $sql);
            }
        }
        
        return $this->queries[] = $sql;
    }
    
    /**
     * Loops through an array of row results and replaces streams with their values
     * @param array<array<string, NULL|scalar|resource>> $rows reference to an array of rows from the DB
     */
    private function fetchStreams(array &$rows) : void
    {
        foreach ($rows as &$row)
        {
            foreach ($row as &$value)
            {
                if (is_resource($value))
                    $value = stream_get_contents($value);
            }
        }
    }
    
    /** Begins a new database transaction if not already in one */
    public function beginTransaction() : void
    {
        if (!$this->connection->inTransaction())
        {
            $sql = "PDO->beginTransaction()";
            $this->queries[] = $sql;
            $this->startTimingQuery();
            
            if ($this->driver === self::DRIVER_MYSQL)
                $this->configTransaction();

            $this->connection->beginTransaction();
            
            if ($this->driver === self::DRIVER_POSTGRESQL)
                $this->configTransaction();

            $this->stopTimingQuery($sql, DBStats::QUERY_READ, false);
        }
    }
    
    /** Sends a query to configure the isolation level and access mode */
    private function configTransaction() : void
    {
        // https://www.postgresql.org/docs/current/transaction-iso.html
        // READ UNCOMMITTED, READ COMMITTED, REPEATEABLE READ, SERIALIZABLE
        // PostgreSQL default - READ COMMITTED, MySQL default - REPEATABLE READ
        $qstr = "SET TRANSACTION ISOLATION LEVEL REPEATABLE READ";
        if ($this->read_only) $qstr .= " READ ONLY";
        $this->connection->exec($qstr);
    }

    /** Rolls back the current database transaction */
    public function rollback() : void
    { 
        if ($this->connection->inTransaction())
        {
            $sql = "PDO->rollback()";
            $this->queries[] = $sql;
            $this->startTimingQuery();            
            $this->connection->rollBack();
            $this->stopTimingQuery($sql, DBStats::QUERY_WRITE, false);
        }
    }
    
    /** Commits the current database transaction */
    public function commit() : void
    {
        if ($this->connection->inTransaction()) 
        {
            $sql = "PDO->commit()";
            $this->queries[] = $sql;
            $this->startTimingQuery();
            $this->connection->commit();             
            $this->stopTimingQuery($sql, DBStats::QUERY_WRITE, false);
        }
    }
    
    /** Begins timing a query (performance metrics) */
    private function startTimingQuery() : void
    {
        if ($this->stats !== null) 
            $this->stats->startQuery();
    }
    
    /** Ends timing a query (performance metrics) */
    private function stopTimingQuery(string $sql, int $type, bool $count = true) : void
    {
        if ($this->stats !== null) 
            $this->stats->endQuery($sql, $type, $count);
    }
    
    /** Add a new performance metrics context on to the stack and returns it */
    public function startStatsContext() : DBStats
    {
        return $this->stats = new DBStats();
    }

    /** Pop the current performance metrics context off of the stack */
    public function stopStatsContext() : void
    {
        if ($this->stats !== null) 
            $this->stats->stopTiming();
        $this->stats = null;
    }
    
    /** 
     * Returns the array of query history 
     * @return array<string|array{query:string,error:string}> string array
     */
    public function getAllQueries() : array
    {
        return $this->queries;
    }
}

