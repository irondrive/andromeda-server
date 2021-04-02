<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/apps/accounts/FieldCrypt.php"); use Andromeda\Apps\Accounts\FieldCrypt;

require_once(ROOT."/apps/files/storage/Storage.php");
require_once(ROOT."/apps/files/storage/Traits.php");

/** Exception indicating that the file handle failed to open */
class FileOpenFailedException extends StorageException { public $message = "FILE_OPEN_FAILED"; }

/** Exception indicating that the file handle failed to seek */
class FileSeekFailedException extends StorageException { public $message = "FILE_SEEK_FAILED"; }

/** Exception indicating that the file handle failed to close */
class FileCloseFailedException extends StorageException { public $message = "FILE_CLOSE_FAILED"; }

class FileContext
{
    public $handle;
    public int $offset;
    public bool $isWrite;
    
    public function __construct($handle, int $offset, bool $isWrite){
        $this->handle = $handle; $this->offset = $offset; $this->isWrite = $isWrite; }
}

/**
 * A storage that uses PHP's fwrapper functions
 * @see https://www.php.net/manual/en/wrappers.php
 */
abstract class FWrapper extends Storage
{
    /** Returns the full fwrapper URL for the given path */
    protected abstract function GetFullURL(string $path = "") : string;
    
    public function ItemStat(string $path) : ItemStat
    {
        $data = stat($this->GetFullURL($path));
        if (!$data) throw new ItemStatFailedException();
        return new ItemStat($data['atime'], $data['ctime'], $data['mtime'], $data['size']);
    }
    
    public function isFolder(string $path) : bool
    {
        return is_dir($this->GetFullURL($path));
    }
    
    public function isFile(string $path) : bool
    {
        return is_file($this->GetFullURL($path));
    }
    
    protected function assertReadable() : void
    {
        if (!is_readable($this->GetFullURL()))
            throw new TestReadFailedException();
    }
    
    protected function assertWriteable() : void
    {
        if (!is_writeable($this->GetFullURL()))
            throw new TestWriteFailedException();
    }

    protected function SubReadFolder(string $path) : array
    {
        $list = scandir($this->GetFullURL($path), SCANDIR_SORT_NONE);
        if ($list === false) throw new FolderReadFailedException();
        return array_filter($list, function($item){ return $item !== "." && $item !== ".."; });
    }
    
    protected function SubCreateFolder(string $path) : self
    {
        if (!mkdir($this->GetFullURL($path))) 
            throw new FolderCreateFailedException();        
        else return $this;
    }
    
    protected function SubCreateFile(string $path) : self
    {
        if (file_put_contents($this->GetFullURL($path),'') === false)
            throw new FileCreateFailedException();
        return $this;
    }

    protected function SubImportFile(string $src, string $dest) : self
    {
        $this->ClosePath($dest);
        
        if (!copy($src, $this->GetFullURL($dest)))
            throw new FileCopyFailedException();
        return $this;
    }
    
    protected function SubTruncate(string $path, int $length) : self
    {
        $this->ClosePath($path);
        
        if (!($handle = static::OpenWriteHandle($path)))
            throw new FileWriteFailedException();

        if (!ftruncate($handle, $length))
            throw new FileWriteFailedException();
        
        if (!fclose($handle)) throw new FileWriteFailedException();
        
        return $this;
    }    
    
    protected function SubDeleteFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if (!unlink($this->GetFullURL($path))) 
            throw new FileDeleteFailedException();
        else return $this;
    }    
    
    protected function SubDeleteFolder(string $path) : self
    {
        if (!rmdir($this->GetFullURL($path))) 
            throw new FolderDeleteFailedException();
        else return $this;
    }

    protected function SubRenameFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileRenameFailedException();
        return $this;
    }
    
    protected function SubRenameFolder(string $old, string $new) : self
    {
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FolderRenameFailedException();
        return $this;
    }
    
    protected function SubMoveFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileMoveFailedException();
        return $this;
    }
    
    protected function SubMoveFolder(string $old, string $new) : self
    {
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FolderMoveFailedException();
        return $this;
    }
    
    protected function SubCopyFile(string $old, string $new) : self
    {
        $this->ClosePath($new);
        
        if (!copy($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileCopyFailedException();
        return $this;
    }    
    
    /** Returns true if fread() and fwrite() may need to be in chunks (network) */
    protected function UseChunks() : bool { return true; }
    
    protected function SubReadBytes(string $path, int $start, int $length) : string
    {
        $context = $this->GetContext($path, $start, false);
        
        if ($this->UseChunks())
        {
            $byte = 0; $data = array();
            
            while (!feof($context->handle) && $byte < $length)
            {
                $read = fread($context->handle, $length-$byte);
                
                if ($read === false) break;
                
                $data[] = $read; $byte += strlen($read);
            }
            
            $data = implode($data);
        }
        else $data = fread($context->handle, $length);
        
        if ($data === false || strlen($data) !== $length)
        {
            Main::GetInstance()->PrintDebug(array(
                'read'=>strlen($data), 'wanted'=>$length));
            
            throw new FileReadFailedException();
        }
        
        $context->offset += $length;
        
        return $data;
    }
    
    protected function SubWriteBytes(string $path, int $start, string $data) : self
    {        
        $context = $this->GetContext($path, $start, true);

        if ($this->UseChunks())
        {
            $written = 0; while ($written < strlen($data))
            {
                $piece = $written ? substr($data, $written) : $data;
                
                $bytes = fwrite($context->handle, $piece);
                
                if ($bytes === false) break;
                
                $written += $bytes;
            }
        }
        else $written = fwrite($context->handle, $data);
        
        if ($written !== strlen($data))
        {
            Main::GetInstance()->PrintDebug(array(
                'wrote'=>$written, 'wanted'=>strlen($data)));
            
            throw new FileWriteFailedException();
        }

        $context->offset += strlen($data);        
        
        return $this;
    }
    
    /** array<path, FileContext> map for all file handles */
    private $contexts = array();
    
    /** Returns true if we can read from a stream opened as write */
    protected static function supportsReadWrite() : bool { return true; }
    
    /** Returns true if an already-open stream can be seeked randomly */
    protected static function supportsSeekReuse() : bool { return true; }
    
    /** Returns a read handle for the given path */
    protected function OpenReadHandle(string $path){ return fopen($this->GetFullURL($path),'rb'); }
    
    /** Returns a write handle for the given path */
    protected function OpenWriteHandle(string $path){ return fopen($this->GetFullURL($path),'rb+'); }
    
    /**
     * Returns a new handle for the given path
     * @param string $path path of file
     * @param int $offset offset to initialize to
     * @param isWrite true if this is a write
     * @throws FileOpenFailedException if opening fails
     * @throws FileSeekFailedException if seeking fails
     * @return FileContext new file context
     */
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        $handle = $isWrite ? $this->OpenWriteHandle($path) : $this->OpenReadHandle($path);
        
        if (!$handle) throw new FileOpenFailedException();
        
        if (fseek($handle, $offset) !== 0) throw new FileSeekFailedException();
        
        return new FileContext($handle, $offset, false);
    }

    /**
     * Returns a context for the given file
     * @param string $path path to file
     * @param int $offset desired byte offset
     * @param bool $isWrite true if write access is needed
     * @return FileContext file stream context
     */
    protected function GetContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        $context = $this->contexts[$path] ?? null;

        if ($context !== null)
        {
            // clsoe the stream if we want to write to a read stream
            $close = ($isWrite && !$context->isWrite);
            
            // close the stream if we want to read from a write stream and that's unsupported
            $close |= (!$isWrite && $context->isWrite && !static::supportsReadWrite());
            
            // close the stream if its offset is wrong and we can't seek it
            $close |= ($context->offset !== $offset && !static::supportsSeekReuse());
            
            if ($close) { $this->ClosePath($path); $context = null; }
        }

        $context ??= $this->OpenContext($path, $offset, $isWrite);
        
        if ($context->offset !== $offset)
        {
            if (fseek($context->handle, $offset) !== 0)
                throw new FileSeekFailedException();
                
            $context->offset = $offset;
        }
        
        $this->contexts[$path] = $context; return $context;
    }
        
    /** Closes any open handles for the given file path */
    protected function ClosePath(string $path) : void
    {
        if (array_key_exists($path, $this->contexts))
        {
            if (!fclose($this->contexts[$path]->handle))
                throw new FileCloseFailedException();
                
            unset($this->contexts[$path]);
        }
    }  
    
    /** Close all open handles when Saving */
    protected function SubSave() : void
    {
        foreach (array_keys($this->contexts) as $path) $this->ClosePath($path);
    }
}

abstract class SFWBase1 extends FWrapper { use BasePath; }
abstract class SFWBase2 extends SFWBase1 { use FieldCrypt; }
abstract class SFWBase3 extends SFWBase2 { use OptFieldCrypt; }

/** A standard fwrapper storage with a base path and user/password */
abstract class StandardFWrapper extends SFWBase3 { use UserPass; }
