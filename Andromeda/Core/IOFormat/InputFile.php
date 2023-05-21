<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

/** An input file stream */
class InputStream
{
    protected $handle;
    protected ?string $name;
    
    public function __construct($handle, ?string $name = null) {
        $this->handle = $handle; $this->name = $name; }
   
    /** Returns the file's stream resource */
    public function GetHandle() { return $this->handle; }
    
    /** Returns the name of the file to be used */
    public function GetName() : ?string { return $this->name; }
    
    /** Returns the entire stream contents */
    public function GetData() : string 
    { 
        $handle = $this->GetHandle();
        
        $retval = stream_get_contents($handle);
        
        fclose($handle); return $retval;
    }
}

/** A file given as a path to an actual file */
class InputPath extends InputStream
{
    private string $path;
    private bool $istemp;
    
    /**
     * @param string $path path to the input file
     * @param ?string $name optional new name of the file
     * @param bool $istemp if true, is a tmp file we can move
     */
    public function __construct(string $path, ?string $name = null, bool $istemp = false) {
        $this->path = $path; $this->name = $name ?? basename($path); $this->istemp = $istemp; }
        
    // TODO GetName() needs to be not null here
    
    /** Returns the path to the input file */
    public function GetPath() : string { return $this->path; }
    
    /** Returns true if the file is a temp file that can be moved */
    public function isTemp() : bool { return $this->istemp; }
    
    /** Returns the size of the file to be used */
    public function GetSize() : int { return filesize($this->path); }
    
    public function GetHandle() { return $this->handle ??= fopen($this->path,'rb'); }
}
