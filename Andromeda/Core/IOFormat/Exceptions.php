<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** Base exception indicating a problem with a client parameter */
abstract class SafeParamException extends Exceptions\ClientErrorException { }

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

/** Exception indicating that an app action does not allow batches */
class BatchNotAllowedException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ACTION_DISALLOWS_BATCH", $details);
    }
}

/** Exception indicating the given Output to parse is invalid */
class InvalidParseException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("PARSE_OUTPUT_INVALID", $details);
    }
}
