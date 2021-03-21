<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\{Utilities, Transactions};
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
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
 * 
 * Storages implement transactions, but only on a best-effort basis,
 * since the underlying filesystems are obviously not transactional.
 * Certain actions like deleting or writing to files cannot be undone.
 * Any "expected" exceptions should always be checked before storage actions.
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
    
    /** By default, most storages use network bandwidth */
    public function usesBandwidth() : bool { return true; }
    
    /** Returns whether or not the storage supports getting free space */
    public function canGetFreeSpace() : bool { return false; }
    
    /** Returns the available space in bytes on the storage */
    public function GetFreeSpace() : int { throw new FreeSpaceFailedException(); }

    /** Activates the storage by making any required connections */
    public abstract function Activate() : self;

    /** Returns true if the filesystem root can be read */
    public abstract function isReadable() : bool;
    
    /** Returns true if the filesystem root can be written to */
    public abstract function isWriteable() : bool;
    
    /** 
     * Manually tests if the root is writeable by uploading a test file 
     * 
     * Can be used to implement isWriteable() if no specific function exists
     */
    protected function TestWriteable() : bool
    {
        try
        {
            $name = Utilities::Random(16).".tmp";
            $this->CreateFile($name)->DeleteFile($name);
            return true;
        }
        catch (StorageException $e){ return false; }
    }    
    
    /**
     * Asserts that the storage is not read only
     * @throws ReadOnlyException if the filesystem is read only
     */
    protected function AssertCanWrite()
    {
        if ($this->GetFilesystem()->isReadOnly())
            throw new ReadOnlyException();
    }
    
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
    public abstract function ReadFolder(string $path) : array;
    
    /** Creates a folder with the given path */
    public function CreateFolder(string $path) : self
    {
        $this->AssertCanWrite();
        
        $this->SubCreateFolder($path);
        
        array_push($this->onRollback, function()use($path){ 
            $this->SubDeleteFolder($path); });
        
        return $this;
    }
    
    /**
     * The storage-specific CreateFolder
     * @see Storage::CreateFolder()
     */
    protected abstract function SubCreateFolder(string $path) : self;    
    
    /** Creates a new empty file at the given path */
    public function CreateFile(string $path) : self
    {
        $this->AssertCanWrite();
        
        $this->SubCreateFile($path);
        
        array_push($this->onRollback, function()use($path){
            $this->SubDeleteFile($path); });
        
        return $this;
    }
    
    /**
     * The storage-specific CreateFile
     * @see Storage::CreateFile()
     */
    protected abstract function SubCreateFile(string $path) : self;
    
    /**
     * Imports a file into the storage
     * @param string $src file to import
     * @param string $dest path of new file
     * @return $this
     */
    public function ImportFile(string $src, string $dest) : self
    {
        $this->AssertCanWrite();
        
        $this->SubImportFile($src, $dest);
        
        array_push($this->onRollback, function()use($dest){
            $this->SubDeleteFile($dest); });
        
        return $this;
    }    
    
    /**
     * The storage-specific ImportFile
     * @see Storage::ImportFile()
     */
    protected abstract function SubImportFile(string $src, string $dest) : self;
    
    /**
     * Reads data from a file
     * @param string $path file to read
     * @param int $start byte offset to read
     * @param int $length exact number of bytes to read
     * @return string file data
     */
    public abstract function ReadBytes(string $path, int $start, int $length) : string;
    
    /**
     * Writes data to a file - NO ROLLBACK
     * @param string $path file to write
     * @param int $start byte offset to write
     * @param string $data data to write
     * @return $this
     */
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->AssertCanWrite();
        
        return $this->SubWriteBytes($path, $start, $data);
    }
    
    /**
     * The storage-specific WriteBytes
     * @see Storage::WriteBytes()
     */
    protected abstract function SubWriteBytes(string $path, int $start, string $data) : self;
    
    /**
     * Truncates the file (changes size) - NO ROLLBACK
     * @param string $path file to resize
     * @param int $length new length of file
     * @return $this
     */
    public function Truncate(string $path, int $length) : self
    {
        $this->AssertCanWrite();
        
        return $this->SubTruncate($path, $length);
    }    
    
    /**
     * The storage-specific Truncate
     * @see Storage::Truncate()
     */
    protected abstract function SubTruncate(string $path, int $length) : self;
    
    /** Deletes the file with the given path - NO ROLLBACK */
    public function DeleteFile(string $path) : self
    {
        $this->AssertCanWrite();
        
        if (!$this->isFile($path)) return $this;
        
        return $this->SubDeleteFile($path);
    }
    
    /**
     * The storage-specific DeleteFile
     * @see Storage::DeleteFile()
     */
    protected abstract function SubDeleteFile(string $path) : self;
    
    /** Deletes the folder with the given path - NO ROLLBACK */
    public function DeleteFolder(string $path) : self
    {
        $this->AssertCanWrite();
        
        if (!$this->isFolder($path)) return $this;
        
        return $this->SubDeleteFolder($path);
    }
    
    /**
     * The storage-specific DeleteFolder
     * @see Storage::DeleteFolder()
     */
    protected abstract function SubDeleteFolder(string $path) : self;

    /** Renames a file from $old to $new - path shall not change */
    public function RenameFile(string $old, string $new) : self
    {
        $this->AssertCanWrite();
        
        $this->SubRenameFile($old, $new);
        
        array_push($this->onRollback, function()use($new,$old){
            $this->SubRenameFile($new, $old); });
        
        return $this;
    }    
    
    /**
     * The storage-specific RenameFile
     * @see Storage::RenameFile()
     */
    protected abstract function SubRenameFile(string $old, string $new) : self;
    
    /** Renames a folder from $old to $new - path shall not change */
    public function RenameFolder(string $old, string $new) : self
    {
        $this->AssertCanWrite();
        
        $this->SubRenameFolder($old, $new);
        
        array_push($this->onRollback, function()use($new,$old){
            $this->SubRenameFolder($new, $old); });
        
        return $this;
    }
    
    /**
     * The storage-specific RenameFolder
     * @see Storage::RenameFolder()
     */
    protected abstract function SubRenameFolder(string $old, string $new) : self;
    
    /** Moves a file from $old to $new - name shall not change */
    public function MoveFile(string $old, string $new) : self
    {
        $this->AssertCanWrite();
        
        $this->SubMoveFile($old, $new);
        
        array_push($this->onRollback, function()use($new,$old){
            $this->SubMoveFile($new, $old); });
        
        return $this;
    }
    
    /**
     * The storage-specific MoveFile
     * @see Storage::MoveFile()
     */
    protected abstract function SubMoveFile(string $old, string $new) : self;
    
    /** Moves a folder from $old to $new - name shall not change */
    public function MoveFolder(string $old, string $new) : self
    {
        $this->AssertCanWrite();
        
        $this->SubMoveFolder($old, $new);
        
        array_push($this->onRollback, function()use($new,$old){
            $this->SubMoveFolder($new, $old); });
        
        return $this;
    }
    
    /**
     * The storage-specific MoveFolder
     * @see Storage::MoveFolder()
     */
    protected abstract function SubMoveFolder(string $old, string $new) : self;
    
    /** Copies a file from $old to $new (path and name can change) */
    public function CopyFile(string $old, string $new) : self
    {
        $this->AssertCanWrite();
        
        $this->SubCopyFile($old, $new);
        
        array_push($this->onRollback, function()use($new){
            $this->SubDeleteFile($new); });
        
        return $this;
    }
    
    /**
     * The storage-specific CopyFile
     * @see Storage::CopyFile()
     */
    protected abstract function SubCopyFile(string $old, string $new) : self;
    
    /** Copies a file from $old to $new (path and name can change) */
    public function CopyFolder(string $old, string $new) : self 
    { 
        $this->AssertCanWrite();
        
        $this->SubCopyFolder($old, $new);
        
        array_push($this->onRollback, function()use($new){
            $this->SubDeleteFolder($new); });
            
        return $this;
    }
    
    /**
     * The storage-specific CopyFolder
     * @see Storage::CopyFolder()
     */
    protected function SubCopyFolder(string $old, string $new) : self
    {
        throw new FolderCopyFailedException();
    }
    
    /** By default, most storages cannot copy whole folders */
    public function canCopyFolders() : bool { return false; }
    
    /** array of all instantiated storages */
    private static $instances = array();    
    
    public function SubConstruct() : void { array_push(self::$instances, $this); }
    
    /** array of functions to run for commit */
    protected array $onCommit = array();
    
    public function commit() { foreach ($this->onCommit as $func) $func(); }
    
    /** Commits all instantiated filesystems */
    public static function commitAll() { foreach (self::$instances as $fs) $fs->commit(); }
    
    /** array of functions to run for rollback */
    protected array $onRollback = array();
    
    public function rollback() 
    {
        foreach (array_reverse($this->onRollback) as $func) try { $func(); } 
            catch (\Throwable $e) { ErrorManager::GetInstance()->Log($e); }
    }
    
    /** Rolls back all instantiated filesystems */
    public static function rollbackAll()
    {
        foreach (self::$instances as $fs)
        {
            try { $fs->rollback(); } catch (\Throwable $e) {
                ErrorManager::GetInstance()->Log($e); }
        }
    }    
}
