<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Base exception indicating a storage failure */
abstract class StorageException extends Exceptions\ServerException { }

/** Exception indicating that reading folder contents failed */
class FolderReadFailedException extends StorageException    { public $message = "FOLDER_READ_FAILED"; }

/** Exception indicating that creating the folder failed */
class FolderCreateFailedException extends StorageException  { public $message = "FOLDER_CREATE_FAILED"; }

/** Exception indicating that deleting the folder failed */
class FolderDeleteFailedException extends StorageException  { public $message = "FOLDER_DELETE_FAILED"; }

/** Exception indicating that moving the folder failed */
class FolderMoveFailedException extends StorageException    { public $message = "FOLDER_MOVE_FAILED"; }

/** Exception indicating that renaming the folder failed */
class FolderRenameFailedException extends StorageException  { public $message = "FOLDER_RENAME_FAILED"; }

/** Exception indicating that copying the folder failed */
class FolderCopyFailedException extends StorageException    { public $message = "FOLDER_COPY_FAILED"; }

/** Exception indicating that creating the file failed */
class FileCreateFailedException extends StorageException    { public $message = "FILE_CREATE_FAILED"; }

/** Exception indicating that deleting the file failed */
class FileDeleteFailedException extends StorageException    { public $message = "FILE_DELETE_FAILED"; }

/** Exception indicating that moving the file failed */
class FileMoveFailedException extends StorageException      { public $message = "FILE_MOVE_FAILED"; }

/** Exception indicating that renaming the file failed */
class FileRenameFailedException extends StorageException    { public $message = "FILE_RENAME_FAILED"; }

/** Exception indicating that reading from the file failed */
class FileReadFailedException extends StorageException      { public $message = "FILE_READ_FAILED"; }

/** Exception indicating that writing to the file failed */
class FileWriteFailedException extends StorageException     { public $message = "FILE_WRITE_FAILED"; }

/** Exception indicating that copying the file failed */
class FileCopyFailedException extends StorageException      { public $message = "FILE_COPY_FAILED"; }

/** Exception indicating that stat failed */
class ItemStatFailedException extends StorageException      { public $message = "ITEM_STAT_FAILED"; }

/** Exception indicating that finding free space failed */
class FreeSpaceFailedException extends StorageException     { public $message = "FREE_SPACE_FAILED"; }

/** Exception indicating that activating the storage failed */
abstract class ActivateException extends StorageException { }

/** Exception indicating that the tested storage is not readable */
class TestReadFailedException extends ActivateException { public $message = "STORAGE_TEST_READ_FAILED"; use Exceptions\Copyable; }

/** Exception indicating that the tested storage is not writeable */
class TestWriteFailedException extends ActivateException { public $message = "STORAGE_TEST_WRITE_FAILED"; use Exceptions\Copyable; }

