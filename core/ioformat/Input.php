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
    
    public function HasParam(string $key) {
        return $this->params->HasParam($key); }
    
    public function GetParam(string $key, int $type) { 
        return $this->params->GetParam($key, $type); }
    
    public function TryGetParam(string $key, int $type) {
        return $this->params->TryGetParam($key, $type); }
    
    public function __construct(string $app, string $action, SafeParams $params,
                                array $files = array(), ?InputAuth $auth = null)
    {
        $this->time = time(); $this->params = $params; $this->files = $files; $this->auth = $auth;

        $this->app = (new SafeParam("alphanum", strtolower($app)))->GetData();
        $this->action = (new SafeParam("alphanum", strtolower($action)))->GetData();
    }
}