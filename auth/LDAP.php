<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/auth/Manager.php");

class LDAPExtensionException extends Exceptions\ServerException   { public $message = "LDAP_EXTENSION_MISSING"; }
class LDAPConnectionFailure extends Exceptions\ServerException    { public $message = "LDAP_CONNECTION_FAILURE"; }

Manager::RegisterAuthType(LDAP::class);

class LDAP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'secure' => null,
            'userprefix' => null
        ));
    }
    
    public static function GetPropUsage() : string { return "--hostname alphanum [--secure bool] [--userprefix text]"; }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        return parent::Create($database, $input)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('secure', $input->TryGetParam('secure', SafeParam::TYPE_BOOL) ?? false)
            ->SetScalar('userprefix', $input->TryGetParam('userprefix', SafeParam::TYPE_TEXT));
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $secure = $input->TryGetParam('secure', SafeParam::TYPE_BOOL);
        $userprefix = $input->TryGetParam('userprefix', SafeParam::TYPE_TEXT);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($secure !== null) $this->SetScalar('secure', $secure);
        if ($userprefix !== null) $this->SetScalar('userprefix', $userprefix);
        
        return $this;
    }
    
    public function GetClientObject() : array
    {
        return array(
            'hostname' => $this->GetHostname(),
            'secure' => $this->GetUseSSL(),
            'userprefix' => $this->GetUserPrefix()
        );
    }
    
    private $ldap;
    
    public function GetHostname() : string { return $this->GetScalar('hostname'); }
    public function GetUseSSL() : bool { return $this->GetScalar('secure'); }
    public function GetUserPrefix() : ?string { return $this->TryGetScalar('userprefix'); }
    
    public function SubConstruct() : void
    {        
        if (!function_exists('ldap_bind')) throw new LDAPExtensionException();
    }
    
    public function Activate() : self
    {
        if (isset($this->ldap)) return $this;
        
        $protocol = $this->GetUseSSL() ? "ldaps" : "ldap";
        
        $this->ldap = ldap_connect("$protocol://".$this->GetHostname());
        if (!$this->ldap) throw new LDAPConnectionFailure();
        
        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0);
        
        return $this;
    }
    
    public function VerifyPassword(string $username, string $password) : bool
    {
        $this->Activate();  
        
        $prefix = $this->GetUserPrefix(); 
        if ($prefix !== null) $username = "$prefix\\$username";
        
        try { return ldap_bind($this->ldap, $username, $password); }
        catch (Exceptions\PHPError $e) {
            Main::GetInstance()->PrintDebug(ldap_error()); return false; } 
    }
    
    public function __destruct()
    {
        if (isset($this->ldap)) try { ldap_close($this->ldap); } catch (Exceptions\PHPError $e) { }
    }
}
