<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Accounts/Auth/External.php");
require_once(ROOT."/Apps/Accounts/Auth/Manager.php");

/** Exception indicating that the LDAP extension does not exist */
class LDAPExtensionException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating that the LDAP connection failed */
class LDAPConnectionFailure extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_CONNECTION_FAILURE", $details);
    }
}

/** Exception indicating that LDAP encountered an error */
class LDAPErrorException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LDAP_EXTENSION_ERROR", $details);
    }
}

/** Uses an LDAP server for authentication */
class LDAP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => new FieldTypes\StringType(),
            'secure' => new FieldTypes\BoolType(),    // true to use LDAP-SSL
            'userprefix' => new FieldTypes\StringType() // LDAP prefix for user lookup
        ));
    }
    
    public static function GetPropUsage() : string { return "--hostname hostname [--secure bool] [--userprefix ?utf8]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        return parent::Create($database, $params)
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname())
            ->SetScalar('secure', $params->GetOptParam('secure',false)->GetBool())
            ->SetScalar('userprefix', $params->GetOptParam('userprefix',null)->GetNullUTF8String());
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname')) $this->SetScalar('hostname',$params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('secure')) $this->SetScalar('secure',$params->GetParam('secure')->GetBool());
        if ($params->HasParam('userprefix')) $this->SetScalar('userprefix',$params->GetParam('userprefix')->GetNullUTF8String());
        
        return $this;
    }
    
    /**
     * Returns a printable client object for this LDAP
     * @return array `{hostname:string, secure:bool, userprefix:string}`
     */
    public function GetClientObject() : array
    {
        return array(
            'hostname' => $this->GetHostname(),
            'secure' => $this->GetUseSSL(),
            'userprefix' => $this->GetUserPrefix()
        );
    }
    
    private $ldap;
    
    /** Returns the hostname of the LDAP server */
    public function GetHostname() : string { return $this->GetScalar('hostname'); }
    
    /** Returns whether to use SSL with the LDAP server */
    public function GetUseSSL() : bool { return $this->GetScalar('secure'); }
    
    /** Returns the user prefix to use for looking up users in LDAP */
    public function GetUserPrefix() : ?string { return $this->TryGetScalar('userprefix'); }
    
    /** Checks for the existence of the LDAP extension */
    public function SubConstruct() : void
    {        
        if (!function_exists('ldap_bind')) throw new LDAPExtensionException();
    }
    
    /** Initiates a connection to the LDAP server */
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
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $this->Activate();
        
        $prefix = $this->GetUserPrefix(); 
        if ($prefix !== null) $username = "$prefix\\$username";
        
        try 
        {
            $success = ldap_bind($this->ldap, $username, $password); 
            
            ldap_close($this->ldap); unset($this->ldap); return $success;
        }
        catch (Exceptions\PHPError $e) 
        {
            $errman = ErrorManager::GetInstance(); $errman->LogException($e);
            
            if ($lerr = ldap_error($this->ldap)) 
                $errman->LogException(new LDAPErrorException($lerr));
            
            return false; 
        } 
    }
}
