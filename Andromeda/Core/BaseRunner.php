<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

use Andromeda\Core\IOFormat\Input;

/**
 * A base runner that can handle Run(Input), 
 * commit/rollback, and tracks run contexts
 */
abstract class BaseRunner
{
    /** 
     * stack frames for nested Run() calls
     * @var array<RunContext>
     */
    protected array $stack = array();
    
    /** true if Run() has been called since the last commit or rollback */
    protected bool $dirty = false;

    /** Returns the RunContext that is currently being executed, if any */
    public function GetContext() : ?RunContext 
    {
        return Utilities::array_last($this->stack); 
    }

    public function __construct()
    {
        register_shutdown_function(function(){
            if ($this->dirty) $this->rollback(); });
        
        if (function_exists('pcntl_signal')) pcntl_signal(SIGTERM, function(){
            if ($this->dirty) $this->rollback(); });
    }
    
    /**
     * Run the given Input command
     * @param Input $input the user input command to run
     * @return mixed
     */
    public abstract function Run(Input $input);
    
    /**
     * Rolls back the current transaction
     * @param ?\Throwable $e the exception that caused the rollback (or null)
     */
    public abstract function rollback(?\Throwable $e = null) : void;
    
    /** Commits the current transaction */
    public abstract function commit() : void;
}
