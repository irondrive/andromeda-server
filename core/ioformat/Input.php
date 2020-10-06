<?php namespace Andromeda\Core\IOFormat; if (!defined('Andromeda')) { die(); }
 
class InputAuth 
{ 
    public function __construct(string $username, string $password) { 
        $this->username = $username; $this->password = $password; }
    public function GetUsername() { return $this->username; }
    public function GetPassword() { return $this->password; }
}

class Address
{
    public function __construct(string $ipaddr = "", string $agent = "") {
        $this->ipaddr = $ipaddr; $this->agent = $agent; }
    public function GetAddress(){ return $this->ipaddr; }
    public function GetAgent(){ return $this->agent; }
}

class Input
{
    private $time; private $app; private $action; private $params; private $addr; private $files; private $auth;
    
    public function GetTime() : int { return $this->time; }
    public function GetApp() : string { return $this->app; }
    public function GetAction() : string { return $this->action; }
    public function GetParams() : SafeParams { return $this->params; }
    public function GetAddress() : Address { return $this->addr; }
    public function GetFiles() : array { return $this->files; }
    public function GetAuth() : ?InputAuth { return $this->auth; }
    
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
        
        $this->addr = $addr ?? new Address();

        $this->app = (new SafeParam("alphanum", strtolower($app)))->GetData();
        $this->action = (new SafeParam("alphanum", strtolower($action)))->GetData();
    }
}