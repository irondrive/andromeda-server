<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/apps/files/storage/Storage.php");

/**
 * A storage that uses PHP's fwrapper functions
 * @see https://www.php.net/manual/en/wrappers.php
 */
abstract class FWrapper extends Storage
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'path' => null
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'path' => $this->GetPath()
        ));
    }
    
    public static function GetCreateUsage() : string { return "--path fspath"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        $path = $input->GetParam('path', SafeParam::TYPE_FSPATH);
        return parent::Create($database, $input, $filesystem)->SetPath($path);
    }
    
    public static function GetEditUsage() : string { return "[--path fspath]"; }
    
    public function Edit(Input $input) : self
    {
        $path = $input->TryGetParam('path', SafeParam::TYPE_FSPATH);
        if ($path !== null) $this->SetPath($path);
        return parent::Edit($input);
    }

    /** Returns the full fwrapper URL for the given path */
    protected abstract function GetFullURL(string $path = "") : string;
    
    /** Returns the full storage level path for the given root-relative path */
    protected function GetPath(string $path = "") : string 
    { 
       return $this->GetScalar('path').'/'.$path;
    }

    /** Sets the path of the storage's root */
    private function SetPath(string $path) : self { return $this->SetScalar('path',$path); }
    
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
    
    public function isReadable() : bool
    {
        return is_readable($this->GetFullURL());
    }
    
    public function isWriteable() : bool
    {
        return is_writeable($this->GetFullURL());
    }
    
    /** Manually tests if the root is writeable by uploading a test file */
    public function TestWriteable() : bool
    {
        try
        {
            $name = Utilities::Random(16).".tmp";
            $this->CreateFile($name)->DeleteFile($name);
            return true;
        }
        catch (StorageException $e){ return false; }
    }
    
    public function ReadFolder(string $path) : ?array
    {
        if (!$this->isFolder($path)) return null;
        $list = scandir($this->GetFullURL($path), SCANDIR_SORT_NONE);
        if ($list === false) throw new FolderReadFailedException();
        return array_filter($list, function($item){ return $item !== "." && $item !== ".."; });
    }
    
    public function CreateFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFolder($path)) return $this;
        if (!mkdir($this->GetFullURL($path))) throw new FolderCreateFailedException();
        else return $this;
    }
    
    public function DeleteFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFolder($path)) return $this;
        if (!rmdir($this->GetFullURL($path))) throw new FolderDeleteFailedException();
        else return $this;
    }
    
    public function DeleteFile(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFile($path)) return $this;
        if (!unlink($this->GetFullURL($path))) throw new FileDeleteFailedException();
        else return $this;
    }
    
    public function CreateFile(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFile($path)) return $this;
        if (!touch($this->GetFullURL($path))) throw new FileCreateFailedException();
        return $this;
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        $this->CheckReadOnly();
        if (!copy($src, $this->GetFullURL($dest)))
            throw new FileCopyFailedException();
        return $this;
    }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, false);
        
        if (fseek($handle, $start) !== 0)
            throw new FileReadFailedException();
        
        return $this->ReadHandle($handle, $length);
    }
    
    /** Returns true if fread() and fwrite() may need to be in chunks */
    protected function UseChunks() : bool { return true; }
    
    /**
     * Reads bytes from the given handle by chunking if required
     * @param resource $handle the stream to read from
     * @param int $length number of bytes to read
     * @throws FileReadFailedException if reading fails
     * @return string read bytes
     */
    protected function ReadHandle($handle, int $length) : string
    {
        if ($this->UseChunks()) 
        {
            $byte = 0; $data = array();
            
            while (!feof($handle) && $byte < $length)
            {
                $read = fread($handle, $length-$byte);
                
                if ($read === false) break;
                
                array_push($data, $read); $byte += strlen($read);
            }
            
            $data = implode($data);
        }
        else $data = fread($handle, $length);        
        
        if ($data === false || strlen($data) !== $length)
        {
            Main::GetInstance()->PrintDebug(array(
                'read'=>strlen($data), 'wanted'=>$length));
            
            throw new FileReadFailedException();
        }
        
        return $data;
    }
    
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->CheckReadOnly();
        
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, true);    
        
        if (fseek($handle, $start)) throw new FileWriteFailedException();

        return $this->WriteHandle($handle, $data);
    }
    
    /**
     * Writes the given data to the given handle, chunking if required
     * @param resource $handle stream to write to 
     * @param string $data data to write
     * @throws FileWriteFailedException if writing fails
     * @return $this
     */
    protected function WriteHandle($handle, string $data) : self
    {
        if ($this->UseChunks())
        {
            $written = 0; while ($written < strlen($data))
            {
                $piece = $written ? substr($data, $written) : $data;
                
                $bytes = fwrite($handle, $piece);
                
                if ($bytes === false) break;
                
                $written += $bytes;
            }
        }
        else $written = fwrite($handle, $data);
        
        if ($written !== strlen($data))
        {
            Main::GetInstance()->PrintDebug(array(
                'wrote'=>$written, 'wanted'=>strlen($data)));
            
            throw new FileWriteFailedException();
        }
        
        return $this;
    }
    
    public function Truncate(string $path, int $length) : self
    {
        $this->CheckReadOnly();
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, true);    
        
        if (!ftruncate($handle, $length))
            throw new FileWriteFailedException();
        
        return $this;
    }

    public function RenameFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileRenameFailedException();
        return $this;
    }
    
    public function RenameFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FolderRenameFailedException();
        return $this;
    }
    
    public function MoveFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileMoveFailedException();
        return $this;
    }
    
    public function MoveFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!rename($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FolderMoveFailedException();
        return $this;
    }
    
    public function CopyFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!copy($this->GetFullURL($old), $this->GetFullURL($new)))
            throw new FileCopyFailedException();
        return $this;
    }
    
    /** path=>resource map for all file handles */
    private $handles = array(); 
    
    /** path array listing files being written to */
    private $writing = array();

    /** Opens and returns read handle for the given file path */
    protected function GetReadHandle(string $path) { return fopen($path,'rb'); }
    
    /** Opens and returns a read/write handle for the given file path */
    protected function GetWriteHandle(string $path) { return fopen($path,'rb+'); }
    
    /** Closes the given file resource handle */
    protected function CloseHandle($handle) : bool { return fclose($handle); }
    
    /** Closes any open handles for the given file path */
    protected function ClosePath(string $path) : void
    {
        if (array_key_exists($path, $this->handles))
        {
            $this->CloseHandle($this->handles[$path]);
            unset($this->handles[$path]);
            
            if (in_array($path, $this->writing))
                unset($this->writing[$path]);
        }
    }

    /**
     * Opens, caches and returns a handle for the given file
     * @param string $path path to file
     * @param bool $isWrite true if write access is needed
     * @throws FileWriteFailedException opening write handle fails
     * @throws FileReadFailedException opening read handle fails
     * @return resource opened handle
     */
    protected function GetHandle(string $path, bool $isWrite)
    {        
        $isOpen = array_key_exists($path, $this->handles);

        if ($isWrite && $isOpen && !in_array($path, $this->writing))
        {
            $this->ClosePath($path); $isOpen = false;    
        }
        
        if (!$isOpen)
        {
            if ($isWrite)
            {
                $this->handles[$path] = $this->GetWriteHandle($path);
                
                if (!($this->handles[$path] ?? false)) 
                    throw new FileWriteFailedException();
                
                array_push($this->writing, $path);
            }
            else 
            {
                $this->handles[$path] = $this->GetReadHandle($path);
                
                if (!($this->handles[$path] ?? false)) 
                    throw new FileReadFailedException();
            }
        }

        return $this->handles[$path];
    }
    
    public function __destruct()
    {
        foreach ($this->handles as $handle)
        {
            try { $this->CloseHandle($handle); } 
            catch (\Throwable $e) {
                ErrorManager::GetInstance()->Log($e); }       
        }
    }
}

