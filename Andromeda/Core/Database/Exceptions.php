<?php declare(strict_types=1); namespace Andromeda\Core\Database; if (!defined('Andromeda')) die();

use \PDOException;

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the a write was requested to a read-only database */
class DatabaseReadOnlyException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("READ_ONLY_DATABASE", $details);
    }
}

/** Exception indicating that database install config failed */
class DatabaseInstallException extends Exceptions\ClientErrorException
{
    public function __construct(DatabaseConfigException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Exception indicating that the class cannot select from child classes */
class NotMultiTableException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("TABLE_NOT_MULTI_CLASS", $details);
    }
}

/** Exception indicating that no fields were previously registered with a table */
class NoChildTableException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_CHILD_TABLE", $details);
    }
}

/** Exception indicating a base table is not given for this class */
class NoBaseTableException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_BASE_TABLE", $details);
    }
}

/** Exception indicating the API package is not yet set */
class ApiPackageException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("API_PACKAGE_NOT_SET", $details);
    }
}

/** Base class for database initialization exceptions */
abstract class DatabaseConfigException extends Exceptions\ServiceUnavailableException { }

/** Exception indicating that the database configuration is not found */
class DatabaseMissingException extends DatabaseConfigException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_CONFIG_MISSING", $details);
    }
}

abstract class DatabaseConnectException extends DatabaseConfigException { }

/** Exception indicating that the database connection failed to initialize */
class PDODatabaseConnectException extends DatabaseConnectException
{
    public function __construct(?PDOException $e = null) {
        parent::__construct("DATABASE_CONNECT_FAILED");
        if ($e) $this->AppendException($e);
    }
}

/** Exception indicating that the database was requested to use an unknkown driver */
class InvalidDriverException extends DatabaseConnectException
{
    public function __construct(?string $details = null) {
        parent::__construct("PDO_UNKNOWN_DRIVER", $details);
    }
}

/** Base exception indicating that something went wrong due to concurrency, try again */
abstract class ConcurrencyException extends Exceptions\ServiceUnavailableException { }

/** Exception indicating that the update failed to match any objects */
class UpdateFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_UPDATE_FAILED", $details);
    }
}

/** Exception indicating that the row insert failed */
class InsertFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_INSERT_FAILED", $details);
    }
}

/** Exception indicating that the row delete failed */
class DeleteFailedException extends ConcurrencyException
{
    public function __construct(?string $details = null) {
        parent::__construct("DB_OBJECT_DELETE_FAILED", $details);
    }
}

/** Exception indicating the file could not be imported because it's missing */
class ImportFileMissingException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMPORT_FILE_MISSING", $details);
    }
}

/** Base class representing a run-time database error */
abstract class DatabaseException extends Exceptions\ServerException
{
    public function __construct(string $message = "DATABASE_ERROR", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating that PDO failed to execute the given query */
class DatabaseQueryException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_QUERY_ERROR");
        $this->AppendException($e);
    }
}

/** Exception indicating that fetching results from the query failed */
class DatabaseFetchException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATABASE_FETCH_FAILED", $details);
    }
}

/** Exception indicating the database had an integrity violation */
class DatabaseIntegrityException extends DatabaseException
{
    public function __construct(PDOException $e) {
        parent::__construct("DATABASE_INTEGRITY_VIOLATION");
        $this->AppendException($e);
    }
}

/** Andromeda cannot rollback and then commit/save since database/objects state is not sufficiently reset */
class SaveAfterRollbackException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SAVE_AFTER_ROLLBACK", $details);
    }
}

/** Exception indicating that the requested class does not match the loaded object */
class ObjectTypeException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("DBOBJECT_TYPE_MISMATCH", $details);
    }
}

/** Exception indicating that multiple objects were loaded for by-unique query */
class MultipleUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("MULTIPLE_UNIQUE_OBJECTS", $details);
    }
}

/** Exception indicating the given unique key is not registered for this class */
class UnknownUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_UNIQUE_KEY", $details);
    }
}

/** Exception indicating that null was given as a unique key value */
class NullUniqueKeyException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("NULL_UNIQUE_VALUE", $details);
    }
}

/** Exception indicating that the requested object is null */
class SingletonNotFoundException extends DatabaseException
{
    public function __construct(?string $details = null) {
        parent::__construct("SINGLETON_NOT_FOUND", $details);
    }
}

/** Exception indicating the requested class is not a child */
class BadPolyClassException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("BAD_POLY_CLASS", $details);
    }
}

/** Exception indicating the given row has a bad type value */
class BadPolyTypeException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("BAD_POLY_TYPE", $details);
    }
}
