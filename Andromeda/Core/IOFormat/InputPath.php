<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** A file given as a path to an actual file */
class InputPath extends InputFile
{
    private string $path;
    private string $name;
    private bool $istemp;
    /** @var ?resource */
    private $handle = null;
    
    /**
     * @param string $path path to the input file
     * @param ?string $name optional new name of the file
     * @param bool $istemp if true, is a tmp file we can move
     */
    public function __construct(string $path, ?string $name = null, bool $istemp = false) 
    {
        $this->path = $path; 
        $this->istemp = $istemp; 
        $this->name = $name ?? basename($path);
    }
    
    public function __destruct(){ if ($this->handle !== null) fclose($this->handle); }
    
    /** Returns the path to the input file */
    public function GetPath() : string { return $this->path; }
    
    /** Returns the name of the file to be used */    
    public function GetName() : string { return $this->name; }
    
    /** Returns true if the file is a temp file that can be moved */
    public function isTemp() : bool { return $this->istemp; }
    
    /** 
     * Returns the size of the file to be used 
     * @throws FileReadFailedException if it fails
     */
    public function GetSize() : int 
    { 
        $retval = filesize($this->path);
        if ($retval === false)
            throw new FileReadFailedException($this->path);
        else return $retval;
    }

    /**
     * Returns the file's stream resource
     * @throws FileReadFailedException if it fails
     * @return resource
     */
    public function GetHandle() 
    {
        if ($this->handle === null)
        {
            $handle = fopen($this->path,'rb');
            if ($handle === false)
                throw new FileReadFailedException($this->path);
            else $this->handle = $handle;
        }
        
        return $this->handle;
    }
}
