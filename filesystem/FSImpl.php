<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Transactions;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php");

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/filesystem/FSManager.php");
require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

/**
 * Abstract class for a filesystem implementation, with the actual disk functions.
 * 
 * This is the interface that on-disk objects (files) will call into. Filesystem 
 * implementations define how DB objects map to disk files, and then call the underlying 
 * storage. The FSImpl class's info is managed by the FSManager and is not a DB object.
 * 
 * @see FSManager
 */
abstract class FSImpl implements Transactions
{
    public function __construct(FSManager $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    
    /**
     * Returns the chunk size of the filesystem.
     * 
     * Reads and writes must be of one or more chunks if not null.
     * @return int|NULL FS chunk size
     */
    public function GetChunkSize() : ?int { return null; }
    
    /** Returns the underlying storage */
    protected function GetStorage() : Storage { return $this->filesystem->GetStorage(); }
    
    /** Returns a database reference */
    protected function GetDatabase() : ObjectDatabase { return $this->filesystem->GetDatabase(); }

    /** Synchronizes the given file's metadata with storage */
    public abstract function RefreshFile(File $file) : self;
    
    /** Synchronizes the given folder's metadata with storage */
    public abstract function RefreshFolder(Folder $folder) : self;
    
    /** Creates the given folder on disk */
    public abstract function CreateFolder(Folder $folder) : self;
    
    /** Deletes the given folder from disk */
    public abstract function DeleteFolder(Folder $folder) : self;

    /**
     * Creates a new file and imports its content
     * @param File $file the database object
     * @param string $path path to the file content
     * @return $this
     */
    public abstract function ImportFile(File $file, string $path) : self;
    
    /** Deletes the given file from storage */
    public abstract function DeleteFile(File $file) : self;
    
    /**
     * Reads from the given file
     * @param File $file file to read
     * @param int $start byte offset
     * @param int $length number of bytes
     * @return string file data
     */
    public abstract function ReadBytes(File $file, int $start, int $length) : string;
    
    /**
     * Writes to the given file
     * @param File $file file to write
     * @param int $start byte offset
     * @param string $data data to write
     * @return $this
     */
    public abstract function WriteBytes(File $file, int $start, string $data) : self;
    
    /**
     * Truncates (changes size of) a file
     * @param File $file file to truncate
     * @param int $length desired size in bytes
     * @return $this
     */
    public abstract function Truncate(File $file, int $length) : self;
    
    /** Renames a file to the given name */
    public abstract function RenameFile(File $file, string $name) : self;
    
    /** Renames a folder to the given name */
    public abstract function RenameFolder(Folder $folder, string $name) : self;
    
    /**
     * Moves a file
     * @param File $file file to move
     * @param Folder $parent new parent folder
     * @return $this
     */
    public abstract function MoveFile(File $file, Folder $parent) : self;
    
    /**
     * Moves a folder
     * @param Folder $folder folder to move
     * @param Folder $parent new parent folder
     * @return $this
     */
    public abstract function MoveFolder(Folder $folder, Folder $parent) : self;
    
    /**
     * Copies a file
     * @param File $file file to copy
     * @param File $dest new object for destination
     * @return $this
     */
    public abstract function CopyFile(File $file, File $dest) : self;
    
    /**
     * Copies a folder
     * @param Folder $folder folder to copy
     * @param Folder $dest new object for destination
     * @return $this
     */
    public abstract function CopyFolder(Folder $folder, Folder $dest) : self;
    
    /** Passes commit to the underlying storage */
    public function commit() { return $this->GetStorage()->commit(); }
    
    /** Passes rollback to the underlying storage */
    public function rollback() { return $this->GetStorage()->rollback(); }
}
