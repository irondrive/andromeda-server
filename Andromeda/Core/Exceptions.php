<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

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

class InstallDisabledException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("HTTP_INSTALL_DISABLED", $details);
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
    public function __construct(string $appname) {
        parent::__construct("INSTALL_REQUIRED", $appname);
    }
}

/** Exception indicating that the app database upgrade scripts must be run */
class UpgradeRequiredException extends Exceptions\ServiceUnavailableException
{
    private string $oldVersion;
    public function getOldVersion() : string { return $this->oldVersion; }
    public function __construct(string $appname, string $oldVersion) 
    {
        $this->oldVersion = $oldVersion;
        parent::__construct("UPGRADE_REQUIRED", $appname);
    }
}

/** An exception indicating that the app is already installed */
class InstalledAlreadyException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("INSTALLED_ALREADY", $details);
    }
}

/** Exception indicating that the app is already upgraded to current */
class UpgradedAlreadyException extends Exceptions\ServiceUnavailableException
{
    public function __construct(?string $details = null) {
        parent::__construct("UPGRADED_ALREADY", $details);
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

/** Exception indicating the Emailer is SMTP but has no hosts */
class MissingHostsException extends MailSendException
{
    public function __construct(?string $details = null) {
        parent::__construct("MAILER_MISSING_HOSTS", $details);
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

/** Exception indicating that scanning apps failed */
class FailedScanAppsException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FAILED_SCAN_APPS", $details);
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

/** Exception indicating the given context is missing metrics */
class MissingMetricsException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("MISSING_METRICS", $details);
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

/** Exception indicating the output buffer failed */
class OutputBufferException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("OUTPUT_BUFFER_FAIL", $details);
    }
}
