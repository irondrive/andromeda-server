<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class LDAPExtensionException extends Exceptions\ServerException   { public $message = "LDAP_EXTENSION_MISSING"; }

class LDAP extends BaseObject implements Source
{
    private $ldap = null;
    
    public function GetHostname() : string { return $this->GetScalar('hostname'); }
    public function GetUserPrefix() : ?string { return $this->TryGetScalar('userprefix'); }
    public function GetUseSSL() : bool { return $this->GetScalar('secure'); }
    
    public function GetAccountGroup() : ?Group { return $this->TryGetObject('account_group'); }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        parent::__construct($database, $data);
        
        if (!function_exists('ldap_bind')) throw new LDAPExtensionException();
        
        $protocol = $this->GetUseSSL() ? "ldaps" : "ldap";
        
        $this->ldap = ldap_connect("$protocol://".$this->GetHostname());        
        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0);
    }
    
    public function VerifyPassword(string $username, string $password) : bool
    {
        if (strlen($username) == 0 || strlen($password) == 0) return false;      
        
        $prefix = $this->GetUserPrefix(); 
        if ($prefix !== null) $username = "$prefix\\$username";
        
        try { return ldap_bind($this->ldap, $username, $password); }
        catch (Exceptions\PHPException $e) { return false; }           
    }
    
    public function __destruct()
    {
        try { ldap_close($this->ldap); } catch (Exceptions\PHPException $e) { }
    }
}
