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
    
    protected function GetPath() : string { return $this->GetScalar('path').'/'; }
    
    // TODO create a class with these properties so we can return it all with a single stat()
    public function GetATime(string $path) : int { return fileatime($this->GetPath().$path); }
    public function GetCTime(string $path) : int { return filectime($this->GetPath().$path); }
    public function GetMTime(string $path) : int { return filemtime($this->GetPath().$path); }
    public function GetSize(string $path) : int  { return filesize($this->GetPath().$path); }
    
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
    
    public function CreateFolder(string $path) : bool
    {
        return mkdir($this->GetPath().$path);        
    }
    
    public function DeleteFolder(string $path) : bool
    {
        return rmdir($this->GetPath().$path);
    }
    
    public function DeleteFile(string $path) : bool
    {
        return unlink($this->GetPath().$path);
    }
    
    public function ImportFile(string $src, string $dest) : bool
    {
        return rename($src, $this->GetPath().$dest);
    }
    
    public function CreateFile(string $path) : bool
    {
        return touch($this->GetPath().$path);
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
            $this->writing[$path] = fopen($path, $reading?'rwb':'wb');            
            if ($reading) $this->reading[$path] = $this->writing[$path];
        }
        else // isRead
        {
            if ($writing) fclose($this->writing[$path]);
            $this->reading[$path] = fopen($path, $writing?'rwb':'rb');
            if ($writing) $this->writing[$path] = $this->reading[$path];
        }
        
        return $isWrite ? $this->writing[$path] : $this->reading[$path];
    }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, false);        
        fseek($handle, $start); return fread($handle, $length);
    }
    
    public function WriteBytes(string $path, int $start, string $data) : bool
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, true);        
        fseek($handle, $start); return fwrite($handle, $data);
    }
    
    public function Truncate(string $path, int $length) : bool
    {
        $path = $this->GetPath().$path;
        $handle = $this->GetHandle($path, true);        
        return ftruncate($handle, $length);
    }
    
    public function __destruct()
    {
        $handles = array_merge($this->reading, $this->writing);
        try { foreach ($handles as $handle) fclose($handle); }
        catch (\Throwable $e) { }        
    }
    
    public function RenameFile(string $old, string $new) : bool
    {
        return rename($this->GetPath().$old, $this->GetPath().$new);
    }
    
    public function RenameFolder(string $old, string $new) : bool
    {
        return rename($this->GetPath().$old, $this->GetPath().$new);
    }
    
    public function MoveFile(string $old, string $new) : bool
    {
        return rename($this->GetPath().$old, $this->GetPath().$new);
    }
    
    public function MoveFolder(string $old, string $new) : bool
    {
        return rename($this->GetPath().$old, $this->GetPath().$new);
    }
}

