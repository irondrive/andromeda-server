<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that it was requested to modify the log after it was written to disk */
class LogAfterWriteException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("REQLOG_AFTER_WRITE", $details);
    }
}

/** Exception indicating the log file cannot be written more than once */
class MultiFileWriteException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LOG_FILE_MULTI_WRITE", $details);
    }
}
