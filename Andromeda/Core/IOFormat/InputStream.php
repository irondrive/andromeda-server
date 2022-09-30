<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** An input file stream */
class InputStream extends InputFile
{
    /** @param resource $handle */
    public function __construct($handle) { $this->handle = $handle; }
        
    public function __destruct()
    { 
        if (is_resource($this->handle))
            fclose($this->handle); 
    }
   
    /** 
     * Returns the file's stream resource 
     * @throws Exceptions\FileReadFailedException if it fails
     * @return resource
     */
    public function GetHandle()
    { 
        if (!is_resource($this->handle))
            throw new Exceptions\FileReadFailedException("stream");
        
        return $this->handle; 
    }
}
