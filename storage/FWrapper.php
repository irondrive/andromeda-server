<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/storage/Storage.php");

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
    
    protected abstract function GetFullURL(string $path) : string;
    
    protected function GetPath($path) : string { return $this->GetScalar('path').'/'.$path; }  
    
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
    
    public function ReadFolder(string $path) : ?array
    {
        if (!$this->isFolder($path)) return null;
        return array_filter(scandir($this->GetFullURL($path)), 
            function($item){ return $item !== "." && $item !== ".."; });
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
    
    public abstract function ImportFile(string $src, string $dest) : self;
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, false);        
        if (fseek($handle, $start) !== 0)
            throw new FileReadFailedException();
        $data = fread($handle, $length);
        if ($data === false) throw new FileReadFailedException();
        else return $data;
    }
    
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->CheckReadOnly();
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, true);        
        if (fseek($handle, $start) !== 0 || !fwrite($handle, $data))
            throw new FileWriteFailedException();
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
    
    private $reading = array(); private $writing = array();
    
    private function GetHandle(string $path, bool $isWrite)
    {
        $writing = array_key_exists($path, $this->writing);
        $reading = array_key_exists($path, $this->reading);

        if ($isWrite && $writing)  return $this->writing[$path];
        if (!$isWrite && $reading) return $this->reading[$path];

        if ($isWrite)
        {
            if ($reading) fclose($this->reading[$path]);
            $this->writing[$path] = fopen($path,'rb+');
            if ($reading) $this->reading[$path] = $this->writing[$path];
        }
        else $this->reading[$path] = fopen($path,'rb');
        
        if ($isWrite && $this->writing[$path] === false) throw new FileWriteFailedException();
        else if (!$isWrite && $this->reading[$path] === false) throw new FileReadFailedException();
        
        return $isWrite ? $this->writing[$path] : $this->reading[$path];
    }
    
    public function __destruct()
    {
        foreach ($this->reading as $handle) 
            try { fclose($handle); } catch (\Throwable $e) { }        
    }
}

