<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** Basic file input */
abstract class InputFile
{
    /**
     * Returns the file's stream resource
     * @return resource
     */
    public abstract function GetHandle();
    
    /**
     * Returns the entire stream contents 
     * @throws FileReadFailedException if it fails
     */
    public function GetData() : string
    {
        $handle = $this->GetHandle();
        
        $retval = stream_get_contents($handle);
        
        if ($retval === false)
            throw new FileReadFailedException("stream");
        
        fclose($handle); return $retval;
    }
}
