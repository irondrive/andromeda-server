<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Transactions;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

/** Client exception indicating that a write was attempted to a read-only storage */
class ReadOnlyException extends Exceptions\ClientDeniedException { public $message = "READ_ONLY_FILESYSTEM"; }

/** Base exception indicating a storage failure */
abstract class StorageException extends Exceptions\ServerException { }

/** Exception indicating that activating the storage failed */
abstract class ActivateException extends StorageException { }

/** Exception indicating that the tested storage is not readable */
class TestReadFailedException extends ActivateException { public $message = "STORAGE_TEST_READ_FAILED"; }

/** Exception indicating that the tested storage is not writeable */
class TestWriteFailedException extends ActivateException { public $message = "STORAGE_TEST_WRITE_FAILED"; }

/** Exception indicating that reading folder contents failed */
class FolderReadFailedException extends StorageException { public $message = "FOLDER_READ_FAILED"; }

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

/** Class representing a stat result */
class ItemStat
{
    public int $atime; public int $ctime; public int $mtime; public int $size;
    public function __construct(int $atime, int $ctime, int $mtime, int $size){ 
        $this->atime = $atime; $this->ctime = $ctime; $this->mtime = $mtime; $this->size = $size; }
}

/** 
 * A Storage implements the on-disk functions that actually store data.
 * @see FSManager 
 */
abstract class Storage extends StandardObject implements Transactions
{
    /** Returns the account that owns this storage (or null) */
    public function GetAccount() : ?Account { return $this->GetFilesystem()->GetOwner(); }
    
    /** Returns the FSManager that manages this storage */
    public function GetFilesystem() : FSManager { return $this->GetObject('filesystem'); }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'filesystem' => new FieldTypes\ObjectRef(FSManager::class)
        ));
    }
    
    /**
     * Returns a printable client object of this storage
     * @return array `{id:id, filesystem:id}`
     */
    public function GetClientObject() : array
    {
        $retval = array(
            'id' => $this->ID(),
            'filesystem' => $this->GetObjectID('filesystem')
        );
        
        if ($this->canGetFreeSpace())
            $retval['freespace'] = $this->GetFreeSpace();
        
        return $retval;
    }

    /** Returns the command usage for Create() */
    public abstract static function GetCreateUsage() : string;
    
    /** Creates a new storage with the given input and the given FS manager */
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::BaseCreate($database)->SetObject('filesystem',$filesystem);
    }
    
    /** Returns the command usage for Edit() */
    public abstract static function GetEditUsage() : string;
    
    /** Edits an existing storage with the given input */
    public function Edit(Input $input) : self { return $this; }
    
    /** Asserts that the underlying storage can be connected and read from/written to */
    public function Test() : self
    {
        $this->Activate();
        
        $ro = $this->GetFilesystem()->isReadOnly();
        
        if (!$this->isReadable()) throw new TestReadFailedException();
        if (!$ro && !$this->isWriteable()) throw new TestWriteFailedException();
        
        return $this;
    }
    
    /**
     * Asserts that the storage is not read only
     * @throws ReadOnlyException if the filesystem is read only
     */
    protected function CheckReadOnly()
    { 
        if ($this->GetFilesystem()->isReadOnly()) 
            throw new ReadOnlyException(); 
    }

    /** Activates the storage by making any required connections */
    public abstract function Activate() : self;

    /** Returns true if the filesystem root can be read */
    public abstract function isReadable() : bool;
    
    /** Returns true if the filesystem root can be written to */
    public abstract function isWriteable() : bool;
    
    /** Returns an ItemStat object on the given path */
    public abstract function ItemStat(string $path) : ItemStat;
    
    /** Returns true if the given path is a folder */
    public abstract function isFolder(string $path) : bool;
    
    /** Returns true if the given path is a file */
    public abstract function isFile(string $path) : bool;
    
    /**
     * Lists the contents of a folder
     * @param string $path folder path
     * @return string[] array of names
     */
    public abstract function ReadFolder(string $path) : ?array;
    
    /** Creates a folder with the given path */
    public abstract function CreateFolder(string $path) : self; 
    
    /** Deletes the folder with the given path */
    public abstract function DeleteFolder(string $path) : self;
    
    /** Deletes the file with the given path */
    public abstract function DeleteFile(string $path) : self;
    
    /**
     * Imports a file into the storage
     * @param string $src file to import
     * @param string $dest path of new file
     * @return $this
     */
    public abstract function ImportFile(string $src, string $dest) : self;
    
    /** Creates a new empty file at the given path */
    public abstract function CreateFile(string $path) : self;
    
    /**
     * Reads data from a file
     * @param string $path file to read
     * @param int $start byte offset to read
     * @param int $length exact number of bytes to read
     * @return string file data
     */
    public abstract function ReadBytes(string $path, int $start, int $length) : string;
    
    /**
     * Writes data to a file
     * @param string $path file to write
     * @param int $start byte offset to write
     * @param string $data data to write
     * @return $this
     */
    public abstract function WriteBytes(string $path, int $start, string $data) : self;
    
    /**
     * Truncates the file (changes size)
     * @param string $path file to resize
     * @param int $length new length of file
     * @return $this
     */
    public abstract function Truncate(string $path, int $length) : self;

    /** Renames a file from $old to $new - path shall not change */
    public abstract function RenameFile(string $old, string $new) : self;    
    
    /** Renames a folder from $old to $new - path shall not change */
    public abstract function RenameFolder(string $old, string $new) : self;
    
    /** Moves a file from $old to $new - name shall not change */
    public abstract function MoveFile(string $old, string $new) : self;
    
    /** Moves a folder from $old to $new - name shall not change */
    public abstract function MoveFolder(string $old, string $new) : self;
    
    /** Copies a file from $old to $new (path and name can change) */
    public abstract function CopyFile(string $old, string $new) : self; 
    
    /** Copies a file from $old to $new (path and name can change) */
    public function CopyFolder(string $old, string $new) : self { throw new FolderCopyFailedException(); }
    
    /** By default, most storages cannot copy whole folders */
    public function canCopyFolders() : bool { return false; }
    
    /** Returns whether or not the storage supports getting free space */
    public function canGetFreeSpace() : bool { return false; }
    
    /** Returns the available space in bytes on the storage */
    public function GetFreeSpace() : int { throw new FreeSpaceFailedException(); }
    
    /** By default, most storages use network bandwidth */
    public function usesBandwidth() : bool { return true; }
    
    public function commit() { }
    public function rollback() { }
}
