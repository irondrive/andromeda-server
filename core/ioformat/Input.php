<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }
 
class InputAuth 
{ 
    public function __construct(string $username, string $password) { 
        $this->username = $username; $this->password = $password; }
    public function GetUsername() : string { return $this->username; }
    public function GetPassword() : string { return $this->password; }
}

class Address
{
    public function __construct(string $ipaddr, string $agent) {
        $this->ipaddr = $ipaddr; $this->agent = $agent; }
    public function GetAddress() : string { return $this->ipaddr; }
    public function GetAgent() : string { return $this->agent; }
}

class Input
{
    private int $time;          public function GetTime() : int { return $this->time; }
    private string $app;        public function GetApp() : string { return $this->app; }
    private string $action;     public function GetAction() : string { return $this->action; }
    private SafeParams $params; public function GetParams() : SafeParams { return $this->params; }
    private Address $addr;      public function GetAddress() : Address { return $this->addr; }
    private array $files;       public function GetFiles() : array { return $this->files; }
    private ?InputAuth $auth;   public function GetAuth() : ?InputAuth { return $this->auth; }
    
    public function HasParam(string $key) {
        return $this->params->HasParam($key); }
    
    public function GetParam(string $key, int $type) { 
        return $this->params->GetParam($key, $type); }
    
    public function TryGetParam(string $key, int $type) {
        return $this->params->TryGetParam($key, $type); }
    
    public function __construct(string $app, string $action, SafeParams $params, ?Address $addr = null,
                                array $files = array(), ?InputAuth $auth = null)
    {
        $this->time = time(); $this->params = $params; $this->files = $files; $this->auth = $auth;
        
        $this->addr = $addr ?? new Address("","");

        $this->app = (new SafeParam("alphanum", strtolower($app)))->GetData();
        $this->action = (new SafeParam("alphanum", strtolower($action)))->GetData();
    }
}