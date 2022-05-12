<?php namespace Andromeda\Core\IOFormat\Interfaces; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Config.php"); use Andromeda\Core\Config;
require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that the command line usage is incorrect */
class IncorrectCLIUsageException extends Exceptions\ClientErrorException
{
    public function __construct()
    {
        parent::__construct(implode(PHP_EOL,array(
            "general usage: php index.php [global flags+] app action [action params+]",
            null,
            "global flags: [--json|--printr] [--dryrun] [--dbconf fspath] ".
            "[--debug ".implode('|',array_keys(Config::DEBUG_TYPES))."] ".
            "[--metrics ".implode('|',array_keys(Config::METRICS_TYPES))."]",
            null,
            "action params: [--\$param value] [--\$param@ file] [--\$param!] [--\$param% file [name]] [--\$param-]",
            "\t param@ puts the content of the file in the parameter",
            "\t param! will prompt interactively or read stdin for the parameter value",
            "\t param% gives the file path as a direct file input (optionally with a new name)",
            "\t param- will attach the stdin stream as a direct file input",
            null,
            "batch usage:   php index.php batch myfile.txt",
            "get version:   php index.php version",
            "get actions:   php index.php core usage"
        )));
    }
}

/** Exception indicating that the given batch file is not valid */
class UnknownBatchFileException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_BATCH_FILE", $details);
    }
}

/** Exception indicating that the given batch file's syntax is not valid */
class BatchFileParseException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("BATCH_FILE_PARSE_ERROR", $details);
    }
}

/** Exception indicating that the given file is not valid */
class InvalidFileException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INACCESSIBLE_FILE", $details);
    }
}

/** Exception indicating that the app or action parameters are missing */
class NoAppActionException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_OR_ACTION_MISSING", $details);
    }
}

/** Exception indicating that the parameter cannot be part of $_GET */
class IllegalGetFieldException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ILLEGAL_GET_FIELD", $details);
    }
}

/** Exception indicating the given batch sequence has too many actions */
class LargeBatchException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("BATCH_TOO_LARGE", $details);
    }
}

/** Exception indicating the HTTP method used is not allowed */
class MethodNotAllowedException extends Exceptions\ClientException
{
    public function __construct(?string $details = null) {
        parent::__construct("METHOD_NOT_ALLOWED", 405, $details);
    }
}

/** Exception indicating that the remote response is invalid */
class RemoteInvalidException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_REMOTE_RESPONSE", $details);
    }
}