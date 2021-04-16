<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/auth/External.php");
require_once(ROOT."/apps/accounts/auth/Manager.php");

/** Exception indicating that the LDAP extension does not exist */
class LDAPExtensionException extends Exceptions\ServerException   { public $message = "LDAP_EXTENSION_MISSING"; }

/** Exception indicating that the LDAP connection failed */
class LDAPConnectionFailure extends Exceptions\ServerException    { public $message = "LDAP_CONNECTION_FAILURE"; }

/** Exception indicating that LDAP encountered an error */
class LDAPErrorException extends Exceptions\ServerException       { public $message = "LDAP_EXTENSION_ERROR"; }

Manager::RegisterAuthType(LDAP::class);

/** Uses an LDAP server for authentication */
class LDAP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'secure' => null,    // true to use LDAP-SSL
            'userprefix' => null // LDAP prefix for user lookup
        ));
    }
    
    public static function GetPropUsage() : string { return "--hostname alphanum [--secure bool] [--userprefix ?text]"; }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        return parent::Create($database, $input)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('secure', $input->GetOptParam('secure', SafeParam::TYPE_BOOL) ?? false)
            ->SetScalar('userprefix', $input->GetOptNullParam('userprefix', SafeParam::TYPE_TEXT));
    }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('hostname')) $this->SetScalar('hostname',$input->GetParam('hostname',SafeParam::TYPE_HOSTNAME));
        if ($input->HasParam('secure')) $this->SetScalar('secure',$input->GetParam('secure',SafeParam::TYPE_BOOL));
        if ($input->HasParam('userprefix')) $this->SetScalar('userprefix',$input->GetNullParam('userprefix',SafeParam::TYPE_TEXT));
        
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
            
            if ($lerr = ldap_error()) $errman->LogException(new LDAPErrorException($lerr));
            
            return false; 
        } 
    }
}
