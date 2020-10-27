<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/Database.php"); use Andromeda\Core\Database\Transactions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class StorageException extends Exceptions\ServerException { }

class FolderCreateFailedException extends StorageException  { public $message = "FOLDER_CREATE_FAILED"; }
class FolderDeleteFailedException extends StorageException  { public $message = "FOLDER_DELETE_FAILED"; }
class FolderMoveFailedException extends StorageException    { public $message = "FOLDER_MOVE_FAILED"; }
class FolderRenameFailedException extends StorageException  { public $message = "FOLDER_RENAME_FAILED"; }
class FileCreateFailedException extends StorageException    { public $message = "FILE_CREATE_FAILED"; }
class FileDeleteFailedException extends StorageException    { public $message = "FILE_DELETE_FAILED"; }
class FileMoveFailedException extends StorageException      { public $message = "FILE_MOVE_FAILED"; }
class FileRenameFailedException extends StorageException    { public $message = "FILE_RENAME_FAILED"; }
class FileReadFailedException extends StorageException      { public $message = "FILE_READ_FAILED"; }
class FileWriteFailedException extends StorageException     { public $message = "FILE_WRITE_FAILED"; }
class ItemStatFailedException extends StorageException      { public $message = "ITEM_STAT_FAILED"; }

class ItemStat
{
    public int $atime; public int $ctime; public int $mtime; public int $size;
    public function __construct(int $atime, int $ctime, int $mtime, int $size){ 
        $this->atime = $atime; $this->ctime = $ctime; $this->mtime = $mtime; $this->size = $size; }
}

abstract class Storage extends StandardObject implements Transactions
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner' => new FieldTypes\ObjectRef(Account::class)
        ));
    }
    
    public abstract function GetClientObject() : array;

    public function commit() { }
    public function rollback() { }
}
