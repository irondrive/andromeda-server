<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items\Exceptions; if (!defined('Andromeda')) die();

use Andromeda\Core\Errors\BaseExceptions;

/** Exception indicating that files cannot be moved across filessytems */
class CrossFilesystemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILESYSTEM_MISMATCH", $details);
    }
}

/** Exception indicating that the item was deleted when refreshed from storage */
class DeletedByStorageException extends BaseExceptions\ClientNotFoundException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_DELETED_BY_STORAGE", $details);
    }
}

/** Exception indicating that the item target name already exists */
class DuplicateItemException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_ALREADY_EXISTS", $details);
    }
}

/** Exception indicating that the folder destination is invalid */
class InvalidFolderParentException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("INVALID_FOLDER_DESTINATION", $details);
    }
}

/** Exception indicating that the operation is not valid on a root folder */
class InvalidRootOpException extends BaseExceptions\ClientErrorException
{
    public function __construct(?string $details = null) {
        parent::__construct("ROOT_FOLDER_OP_INVALID", $details);
    }
}
