<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }
 
class InputAuth 
{ 
    public function __construct(string $username, string $password) { 
        $this->username = $username; $this->password = $password; }
    public function GetUsername() : string { return $this->username; }
    public function GetPassword() : string { return $this->password; }
}

class Input
{
    private int $time;          public function GetTime() : int { return $this->time; }
    private string $app;        public function GetApp() : string { return $this->app; }
    private string $action;     public function GetAction() : string { return $this->action; }
    private SafeParams $params; public function GetParams() : SafeParams { return $this->params; }
    private array $files;       public function GetFiles() : array { return $this->files; }
    private ?InputAuth $auth;   public function GetAuth() : ?InputAuth { return $this->auth; }
    
    public function HasParam(string $key) : bool {
        return $this->params->HasParam($key); }
    
    public function GetParam(string $key, int $type, ?callable $usrfunc = null) { 
        return $this->params->GetParam($key, $type, $usrfunc); }
    
    public function TryGetParam(string $key, int $type, ?callable $usrfunc = null) {
        return $this->params->TryGetParam($key, $type, $usrfunc); }
        
    public function HasFile(string $key) : bool {
        return array_key_exists($key, $this->files); }
        
    public function GetFile(string $key) : string
    {
        if (!$this->HasFile($key)) 
            throw new SafeParamKeyMissingException($key);
        else return $this->files[$key];
    }
    
    public function TryGetFile(string $key) : ?string
    {
        if (!$this->HasFile($key)) return null;
        else return $this->files[$key];
    }
    
    public function __construct(string $app, string $action, SafeParams $params,
                                array $files = array(), ?InputAuth $auth = null)
    {
        $this->time = time(); $this->params = $params; $this->files = $files; $this->auth = $auth;

        $this->app = (new SafeParam("app", strtolower($app)))->GetValue(SafeParam::TYPE_ALPHANUM);
        $this->action = (new SafeParam("action", strtolower($action)))->GetValue(SafeParam::TYPE_ALPHANUM);
    }
}