<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Base exception indicating a storage failure */
abstract class StorageException extends Exceptions\ServerException { }

/** Exception indicating that reading folder contents failed */
class FolderReadFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_READ_FAILED", $details);
    }
}

/** Exception indicating that creating the folder failed */
class FolderCreateFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_CREATE_FAILED", $details);
    }
}

/** Exception indicating that deleting the folder failed */
class FolderDeleteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_DELETE_FAILED", $details);
    }
}

/** Exception indicating that moving the folder failed */
class FolderMoveFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_MOVE_FAILED", $details);
    }
}

/** Exception indicating that renaming the folder failed */
class FolderRenameFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_RENAME_FAILED", $details);
    }
}

/** Exception indicating that copying the folder failed */
class FolderCopyFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FOLDER_COPY_FAILED", $details);
    }
}

/** Exception indicating that creating the file failed */
class FileCreateFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_CREATE_FAILED", $details);
    }
}

/** Exception indicating that deleting the file failed */
class FileDeleteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_DELETE_FAILED", $details);
    }
}

/** Exception indicating that moving the file failed */
class FileMoveFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_MOVE_FAILED", $details);
    }
}

/** Exception indicating that renaming the file failed */
class FileRenameFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_RENAME_FAILED", $details);
    }
}

/** Exception indicating that reading from the file failed */
class FileReadFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_READ_FAILED", $details);
    }
}

/** Exception indicating that writing to the file failed */
class FileWriteFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_WRITE_FAILED", $details);
    }
}

/** Exception indicating that copying the file failed */
class FileCopyFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FILE_COPY_FAILED", $details);
    }
}

/** Exception indicating that stat failed */
class ItemStatFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_STAT_FAILED", $details);
    }
}

/** Exception indicating that finding free space failed */
class FreeSpaceFailedException extends StorageException
{
    public function __construct(?string $details = null) {
        parent::__construct("FREE_SPACE_FAILED", $details);
    }
}

/** Exception indicating that activating the storage failed */
abstract class ActivateException extends StorageException { }

/** Exception indicating that the tested storage is not readable */
class TestReadFailedException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_TEST_READ_FAILED", $details);
    }
}

/** Exception indicating that the tested storage is not writeable */
class TestWriteFailedException extends ActivateException
{
    public function __construct(?string $details = null) {
        parent::__construct("STORAGE_TEST_WRITE_FAILED", $details);
    }
}

