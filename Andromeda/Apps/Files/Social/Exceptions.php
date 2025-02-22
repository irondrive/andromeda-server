<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Social\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the requested share already exists */
class ShareExistsException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARE_EXISTS", $details);
    }
}

/** Exception indicating that the requested share has expired */
class ShareExpiredException extends BaseExceptions\ClientDeniedException
{
    public function __construct(?string $details = null) {
        parent::__construct("SHARE_EXPIRED", $details);
    }
}

/** Exception indicating that a share was requested for a public item */
class SharePublicItemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("CANNOT_SHARE_PUBLIC_ITEM", $details);
    }
}
