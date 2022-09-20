<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Base exception indicating a problem with a client parameter */
abstract class SafeParamException extends BaseExceptions\ClientErrorException { }

/** An exception indicating that the requested parameter has a null value */
class SafeParamNullValueException extends SafeParamException
{
    public function __construct(string $key) {
        parent::__construct("SAFEPARAM_VALUE_NULL", $key);
    }
}

/** Exception indicating that the parameter failed sanitization or validation */
class SafeParamInvalidException extends SafeParamException
{
    public function __construct(string $key, ?string $type = null)
    {
        $details = "$key".($type !== null ? ": must be $type" : "");
        parent::__construct("SAFEPARAM_INVALID_TYPE", $details);
    }
}

/** An exception indicating that the requested parameter name does not exist */
class SafeParamKeyMissingException extends SafeParamException
{
    public function __construct(string $key) {
        parent::__construct("SAFEPARAM_KEY_MISSING", $key);
    }
}

class InputFileMissingException extends SafeParamException
{
    public function __construct(string $key) {
        parent::__construct("INPUT_FILE_MISSING", $key);
    }
}

/** Exception indicating that a batch with zero actions was given */
class EmptyBatchException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("EMPTY_BATCH_GIVEN", $details);
    }
}

/** Exception indicating that an app action does not allow batches */
class BatchNotAllowedException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACTION_DISALLOWS_BATCH", $details);
    }
}

/** Exception indicating the given Output to parse is invalid */
class InvalidParseException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("PARSE_OUTPUT_INVALID", $details);
    }
}

/** Exception indicating that reading the input file failed */
class FileReadFailedException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_READ_FAILED", $details);
    }
}

/** Exception indicating that the output mode cannot be set in multi-output mode */
class MultiOutputJSONException extends BaseExceptions\ServerException
{
    public function __construct(?int $mode = null) {
        parent::__construct("MULTI_OUTPUT_JSON", ($mode !== null) ? (string)$mode : null);
    }
}
