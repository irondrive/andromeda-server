<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

/** Class for custom app output routines */
class OutputHandler
{
    /**
     * @param callable(Output) : void $output function to display custom output
     * @param bool $hasbytes true if the function might do output (can be true for 0 bytes)
     */
    public function __construct(private $output, private bool $hasbytes){ }
    
    /** @return bool true if the function attempts to do output */
    public function HasBytes() : bool { return $this->hasbytes; }
    
    /** Do the actual output routine */
    public function DoOutput(Output $output) : void { ($this->output)($output); }
}
