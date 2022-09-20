<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\MailSendException;

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_MAILER", $details);
    }
}

/** Client error indicating that the mailer config failed */
class MailSendFailException extends BaseExceptions\ClientErrorException
{
    public function __construct(MailSendException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Exception indicating that admin-level access is required */
class AdminRequiredException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ADMIN_REQUIRED", $details);
    }
}
