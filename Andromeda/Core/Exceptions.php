<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

/** An exception indicating that the requested action is invalid for this app */
class UnknownActionException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ACTION", $details);
    }
}

/** Exception indicating that the configured data directory is not valid */
class UnwriteableDatadirException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("DATADIR_NOT_WRITEABLE", $details);
    }
}

/** Exception indicating an invalid app name was given */
class InvalidAppException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_APPNAME", $details);
    }
}

/** Exception indicating that an app dependency was not met */
class AppDependencyException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_DEPENDENCY_FAILURE", $details);
    }
}

/** Exception indicating that the app is not compatible with this framework version */
class AppVersionException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_VERSION_MISMATCH", $details);
    }
}

/** Exception indicating that a mailer was requested but it is disabled */
class EmailDisabledException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("EMAIL_DISABLED", $details);
    }
}

/** Exception indicating that a mailer was requested but none are configured */
class EmailerUnavailableException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("EMAILER_UNAVAILABLE", $details);
    }
}

/** Exception indicating that the requested app is invalid */
class UnknownAppException extends Exceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_APP", $details);
    }
}

/** Exception indicating that the server is configured as disabled */
class MaintenanceException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("SERVER_DISABLED", $details);
    }
}

/** An exception indicating that the app is not installed and needs to be */
class InstallRequiredException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_INSTALL_REQUIRED", $details);
    }
}

/** Exception indicating that the database upgrade scripts must be run */
class UpgradeRequiredException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_UPGRADE_REQUIRED", $details);
    }
}

/** An exception indicating that the metadata file is missing */
class MissingMetadataException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("APP_METADATA_MISSING", $details);
    }
}

/** Exception indicating that decryption failed */
class DecryptionFailedException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("DECRYPTION_FAILED", $details);
    }
}

/** Exception indicating that sending mail failed */
abstract class MailSendException extends Exceptions\ServerException
{
    public function __construct(string $message = "MAIL_SEND_FAILURE", ?string $details = null) {
        parent::__construct($message, $details);
    }
}

/** Exception indicating PHPMailer sending returned false */
class PHPMailerException1 extends MailSendException
{
    public function __construct(string $details) {
        parent::__construct("MAIL_SEND_FAILURE", $details);
    }
}

/** Exception thrown by the PHPMailer library when sending */
class PHPMailerException2 extends MailSendException
{
    public function __construct(\PHPMailer\PHPMailer\Exception $e) {
        parent::__construct(); $this->AppendException($e);
    }
}

/** Exception indicating that no recipients were given */
class EmptyRecipientsException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("NO_RECIPIENTS_GIVEN", $details);
    }
}

/** Exception indicating that the configured mailer driver is invalid */
class InvalidMailTypeException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_MAILER_TYPE", $details);
    }
}

/** Exception indicating that the server failed to load a configured app */
class FailedAppLoadException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FAILED_LOAD_APP", $details);
    }
}

/** SaveMetrics requires the database to not already be undergoing a transaction */
class FinalizeTransactionException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("OUTPUT_IN_TRANSACTION", $details);
    }
}

/** Exception indicating that a duplicate singleton was constructed */
class DuplicateSingletonException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("DUPLICATE_SINGLETON", $details);
    }
}

/** Exception indicating that GetInstance() was called on a singleton that has not been constructed */
class MissingSingletonException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("SINGLETON_NOT_CONSTRUCTED", $details);
    }
}

/** Exception indicating that the given version string is invalid */
class InvalidVersionException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("VERSION_STRING_INVALID", $details);
    }
}

/** Converts a JSON failure into an exception */
class JSONException extends Exceptions\ServerException
{
    public function __construct()
    {
        parent::__construct("JSON_FAIL",
            json_last_error_msg(), json_last_error());
    }
}
