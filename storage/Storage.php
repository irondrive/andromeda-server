<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Transactions;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

use Andromeda\Core\Exceptions\ClientDeniedException;

class ReadOnlyException extends ClientDeniedException { public $message = "READ_ONLY_FILESYSTEM"; }

class StorageException extends Exceptions\ServerException { }
class ActivateException extends StorageException { }
class TestFailedException extends ActivateException { public $message = "STORAGE_TEST_FAILED"; }

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
class FileCopyFailedException extends StorageException      { public $message = "FILE_COPY_FAILED"; }
class ItemStatFailedException extends StorageException      { public $message = "ITEM_STAT_FAILED"; }
class FreeSpaceFailedException extends StorageException     { public $message = "FREE_SPACE_FAILED"; }

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
            'filesystem' => new FieldTypes\ObjectRef(FSManager::class),
            'owner' => new FieldTypes\ObjectRef(Account::class)
        ));
    }
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->TryGetObjectID('owner'),
            'filesystem' => $this->GetObjectID('filesystem')
        );
    }

    public abstract static function GetCreateUsage() : string;
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$filesystem)->SetObject('owner',$account);
    }
    
    public function Edit(Input $input) : self { return $this; }
    
    public abstract function Test() : self;
    public abstract function Activate() : self;

    public function GetAccount() : ?Account { return $this->TryGetObject('owner'); }
    public function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }    
    
    protected function CheckReadOnly(){ if ($this->GetFilesystem()->isReadOnly()) throw new ReadOnlyException(); }

    public function GetFreeSpace() : ?int { return null; }
    
    public abstract function ItemStat(string $path) : ItemStat;    
    public abstract function isFolder(string $path) : bool;    
    public abstract function isFile(string $path) : bool;
    
    public abstract function ReadFolder(string $path) : ?array;    
    public abstract function CreateFolder(string $path) : self; 
    
    public abstract function DeleteFolder(string $path) : self;    
    public abstract function DeleteFile(string $path) : self;
    
    public abstract function ImportFile(string $src, string $dest) : self;    
    public abstract function CreateFile(string $path) : self;
    
    public abstract function ReadBytes(string $path, int $start, int $length) : string;    
    public abstract function WriteBytes(string $path, int $start, string $data) : self;    
    public abstract function Truncate(string $path, int $length) : self;

    public abstract function RenameFile(string $old, string $new) : self;    
    public abstract function RenameFolder(string $old, string $new) : self;    
    public abstract function MoveFile(string $old, string $new) : self;    
    public abstract function MoveFolder(string $old, string $new) : self;    
    public abstract function CopyFile(string $old, string $new) : self; 
    public function canCopyFolders() : bool { return false; }

    public function commit() { }
    public function rollback() { }
}
