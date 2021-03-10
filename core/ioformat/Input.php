<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/ioformat/SafeParam.php");
require_once(ROOT."/core/ioformat/SafeParams.php");

class InputFileMissingException extends SafeParamException {
    public function __construct(string $key) { $this->message = "INPUT_FILE_MISSING: $key"; } }

/** A username and password combination */
class InputAuth 
{ 
    public function __construct(string $username, string $password) { 
        $this->username = $username; $this->password = $password; }
    public function GetUsername() : string { return $this->username; }
    public function GetPassword() : string { return $this->password; }
}

/** A file path and name combination */
class InputFile
{
    public function __construct(string $path, string $name) {
        $this->path = $path; $this->name = $name; }
    public function GetPath() : string { return $this->path; }
    public function GetName() : string { return $this->name; }
}

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
        
    /** @see Input::GetParams() */
    private SafeParams $params;
    
    /** The collection of parameters to be used */
    public function GetParams() : SafeParams { return $this->params; }
    
    // The following params functions are just syntactic sugar to avoid GetParams()
    
    /** @see SafeParams::HasParam() */
    public function HasParam(string $key) : bool {
        return $this->params->HasParam($key); }
        
    /** @see SafeParams::AddParam() */
    public function AddParam(string $key, $value) : self { 
        $this->params->AddParam($key, $value); return $this; }
    
    /** @see SafeParams::GetParam() */
    public function GetParam(string $key, int $type, ?callable $usrfunc = null) { 
        return $this->params->GetParam($key, $type, $usrfunc); }
        
    /** @see SafeParams::GetOptParam() */
    public function GetOptParam(string $key, int $type, ?callable $usrfunc = null) {
        return $this->params->GetOptParam($key, $type, $usrfunc); }
        
    /** @see SafeParams::GetNullParam() */
    public function GetNullParam(string $key, int $type, ?callable $usrfunc = null) {
        return $this->params->GetNullParam($key, $type, $usrfunc); }      
        
    /** @see Input::GetFiles() */
    private array $files;

    /**
     * Determines whether or not the given key exists as an input file
     * @param string $key the parameter name to check for
     * @return bool true if the param exists as an input file
     */
    public function HasFile(string $key) : bool {
        return array_key_exists($key, $this->files); }
        
    /**
     * Adds the given InputFile to the file array
     * @param string $key param name for file
     * @param InputFile $file file name/path
     * @return $this
     */
    public function AddFile(string $key, InputFile $file) : self {
        $this->files[$key] = $file; return $this; }
    
    /**
     * Gets the file mapped to the parameter name
     * @param string $key the parameter key name
     * @throws SafeParamKeyMissingException if the key does not exist
     * @return InputFile the temporary uploaded file
     */
    public function GetFile(string $key) : InputFile
    {
        if (!$this->HasFile($key)) 
            throw new InputFileMissingException($key);
        else return $this->files[$key];
    }
    
    /**
     * Same as GetFile() but returns null rather than throwing an exception
     * @see Input::GetFile()
     */
    public function TryGetFile(string $key) : ?InputFile
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

        $this->app = (new SafeParam("app", strtolower($app)))->GetValue(SafeParam::TYPE_ALPHANUM);
        $this->action = (new SafeParam("action", strtolower($action)))->GetValue(SafeParam::TYPE_ALPHANUM);
    }
}