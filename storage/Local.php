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
    
    private $handles = array();
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetPath().$path;
        if (!array_key_exists($path, $this->handles))
            $handles[$path] = fopen($path,'rb');
        
        fseek($handles[$path], $start);
        return fread($handles[$path], $length);
    }
    
    public function __destruct()
    {
        foreach ($this->handles as $handle) fclose($handle);
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

