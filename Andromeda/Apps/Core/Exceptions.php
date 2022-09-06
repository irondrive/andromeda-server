<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Exceptions/BaseExceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Core/Exceptions.php"); use Andromeda\Core\MailSendException;

/** Exception indicating that the specified mailer object does not exist */
class UnknownMailerException extends Exceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_MAILER", $details);
    }
}

/** Client error indicating that the mailer config failed */
class MailSendFailException extends Exceptions\ClientErrorException
{
    public function __construct(MailSendException $e) {
        parent::__construct(""); $this->CopyException($e);
    }
}

/** Exception indicating that admin-level access is required */
class AdminRequiredException extends Exceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("ADMIN_REQUIRED", $details);
    }
}
