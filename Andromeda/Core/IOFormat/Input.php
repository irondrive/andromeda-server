<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/IOFormat/SafeParam.php");
require_once(ROOT."/Core/IOFormat/SafeParams.php");
require_once(ROOT."/Core/IOFormat/InputAuth.php");
require_once(ROOT."/Core/IOFormat/InputFile.php");
require_once(ROOT."/Core/IOFormat/Exceptions.php");

require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog;

/** 
 * An abstracted Input object gathered from an interface
 * 
 * An Input object describes the app and action to be run, as well
 * as any input parameters or files, or basic authentication
 */
class Input
{
    /** @see Input::GetApp() */
    private string $app;        
    
    /** The app to be run */
    public function GetApp() : string { return $this->app; }
    
    /** @see Input::GetAction() */
    private string $action;     
    
    /** The app action to be run */
    public function GetAction() : string { return $this->action; }
    
    /** @see Input::GetAuth() */
    private ?InputAuth $auth;   
    
    /** The basic authentication to be used */
    public function GetAuth() : ?InputAuth { return $this->auth; }
    
    /** Sets the optional param logger to the given ActionLog */
    public function SetLogger(?ActionLog $logger) : self 
    { 
        if ($logger !== null && ($level = $logger->GetDetailsLevel()) > 0)
        {
            $logref = &$logger->GetInputLogRef(); 
            
            $logref ??= array();
            
            $this->params->SetLogRef($logref, $level);
        }        
        return $this; 
    }
    
    private SafeParams $params;
    
    /** The inner collection of parameters to be used */
    public function GetParams() : SafeParams { return $this->params; }

    /** @see Input::GetFiles() */
    private array $files;
    
    /** Returns the array of input files */
    public function GetFiles() : array { return $this->files; }

    /**
     * Determines whether or not the given key exists as an input file
     * @param string $key the parameter name to check for
     * @return bool true if the param exists as an input file
     */
    public function HasFile(string $key) : bool {
        
        return array_key_exists($key, $this->files); 
    }
        
    /**
     * Adds the given InputStream to the file array
     * @param string $key param name for file
     * @param InputStream $file input file stream
     * @return $this
     */
    public function AddFile(string $key, InputStream $file) : self 
    {
        $this->files[$key] = $file; return $this; 
    }
    
    /**
     * Gets the file mapped to the parameter name
     * @param string $key the parameter key name
     * @throws SafeParamKeyMissingException if the key does not exist
     * @return InputStream the uploaded file
     */
    public function GetFile(string $key) : InputStream
    {
        if (!$this->HasFile($key)) 
            throw new InputFileMissingException($key);
        else return $this->files[$key];
    }
    
    /**
     * Same as GetFile() but returns null rather than throwing an exception
     * @see Input::GetFile()
     */
    public function TryGetFile(string $key) : ?InputStream
    {
        if (!$this->HasFile($key)) return null;
        else return $this->files[$key];
    }
    
    /** Constructs an input object using the data gathered from the interface, and sanitizes the app/action strings */
    public function __construct(string $app, string $action, ?SafeParams $params = null, 
                                ?array $files = null, ?InputAuth $auth = null)
    {
        $this->params = $params ?? new SafeParams(); 
        $this->files = $files ?? array(); 
        
        $this->auth = $auth;

        $this->app = (new SafeParam("app", strtolower($app)))->GetAlphanum();
        $this->action = (new SafeParam("action", strtolower($action)))->GetAlphanum();
    }
}
