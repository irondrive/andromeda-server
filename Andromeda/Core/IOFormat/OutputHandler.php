<?php declare(strict_types=1); namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/IOFormat/Output.php");

/** Class for custom app output routines */
class OutputHandler
{
    /** @var callable() : ?int */
    private $getbytes; 
    /** @var callable(Output) : void */
    private $output;
    
    /**
     * @param callable() : ?int $getbytes get the number of bytes that will be output (can be 0) or null if no output
     * @param callable(Output) : void $output function to display custom output
     */
    public function __construct(callable $getbytes, callable $output)
    {
        $this->getbytes = $getbytes;
        $this->output = $output; 
    }
    
    /** Return the number of bytes that will be output */
    public function GetBytes() : ?int { return ($this->getbytes)(); }
    
    /** Do the actual output routine */
    public function DoOutput(Output $output) : void { ($this->output)($output); }
}
