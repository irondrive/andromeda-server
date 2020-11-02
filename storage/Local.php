<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/storage/FWrapper.php");

class Local extends FWrapper
{
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'freespace' => $this->GetFreeSpace()
        ));
    }    
    
    public function GetFreeSpace() : int
    {
        $space = disk_free_space($this->GetPath());
        if ($space === false) throw new FreeSpaceFailedException();
        else return $space;
    }
    
    protected function GetFullURL(string $path) : string
    {
        return $this->GetPath($path);
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        $this->CheckReadOnly();
        if (!rename($src, $this->GetFullURL($dest)))
            throw new FileCreateFailedException();
        return $this;
    }
}