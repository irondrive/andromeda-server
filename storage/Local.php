<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/storage/Storage.php");

class Local extends Storage
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'path' => null
        ));
    }
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObjectID('owner'),
            'path' => $this->GetPath()
        );
    }
    
    protected function GetPath() : string { return $this->GetScalar('path').'/'; }

    public function ItemStat(string $path) : ItemStat
    {
        $data = stat($this->GetPath().$path);
        if (!$data) throw new ItemStatFailedException();
        return new ItemStat($data['atime'], $data['ctime'], $data['mtime'], $data['size']);
    }
    
    public function isFolder(string $path) : bool
    {
        return is_dir($this->GetPath().$path);
    }
    
    public function isFile(string $path) : bool
    {
        return is_file($this->GetPath().$path);
    }
    
    public function ReadFolder(string $path) : ?array
    {
        $path = $this->GetPath().$path; if (!is_dir($path)) return null;
        return array_filter(scandir($path), function($item){ return $item !== "." && $item !== ".."; });
    }
    
    public function CreateFolder(string $path) : self
    {
        if (is_dir($path)) return $this;
        if (!mkdir($this->GetPath().$path)) throw new FolderCreateFailedException();
        else return $this;
    }
    
    public function DeleteFolder(string $path) : self
    {
        if (!is_dir($path)) return $this;
        if (!rmdir($this->GetPath().$path)) throw new FolderDeleteFailedException();
        else return $this;
    }
    
    public function DeleteFile(string $path) : self
    {
        if (!is_file($path)) return $this;
        if (!unlink($this->GetPath().$path)) throw new FileDeleteFailedException();
        else return $this;
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        if (!rename($src, $this->GetPath().$dest))
            throw new FileCreateFailedException();
    }
    
    public function CreateFile(string $path) : self
    {
        if (is_file($path)) return $this;
        if (!touch($this->GetPath().$path)) throw new FileCreateFailedException();
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
            if ($this->writing[$path] === false)
                throw new FileWriteFailedException();
            $this->reading[$path] = $this->writing[$path];
        }
        else 
        {
            $this->reading[$path] = fopen($path,'rb');
            if ($this->reading[$path] === false)
                throw new FileReadFailedException();
        }

        return $isWrite ? $this->writing[$path] : $this->reading[$path];
    }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, false);        
        if (fseek($handle, $start) !== 0)
            throw new FileReadFailedException();
        $data = fread($handle, $length);
        if ($data === false) throw new FileReadFailedException();
        else return $data;
    }
    
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, true);        
        if (fseek($handle, $start) !== 0 || !fwrite($handle, $data))
            throw new FileWriteFailedException();
        return $this;
    }
    
    public function Truncate(string $path, int $length) : self
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, true);        
        if (!ftruncate($handle, $length))
            throw new FileWriteFailedException();
        return $this;
    }
    
    public function __destruct()
    {
        try { foreach ($this->reading as $handle) fclose($handle); }
        catch (\Throwable $e) { }        
    }
    
    public function RenameFile(string $old, string $new) : self
    {
        if (!rename($this->GetPath().$old, $this->GetPath().$new))
            throw new FileRenameFailedException();
        return $this;
    }
    
    public function RenameFolder(string $old, string $new) : self
    {
        if (!rename($this->GetPath().$old, $this->GetPath().$new))
            throw new FolderRenameFailedException();
        return $this;
    }
    
    public function MoveFile(string $old, string $new) : self
    {
        if (!rename($this->GetPath().$old, $this->GetPath().$new))
            throw new FileMoveFailedException();
        return $this;
    }
    
    public function MoveFolder(string $old, string $new) : self
    {
        if (!rename($this->GetPath().$old, $this->GetPath().$new))
            throw new FolderMoveFailedException();
        return $this;
    }
}

