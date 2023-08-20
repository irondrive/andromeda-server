<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that the raw (non-hashed) key does not exist in memory */
class RawKeyNotAvailableException extends BaseExceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("AUTHOBJECT_KEY_NOT_AVAILABLE", $details);
    }
}
