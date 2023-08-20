<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\Errors\{BaseExceptions, ErrorManager};
use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Accounts/AuthSource/Exceptions.php");
require_once(ROOT."/Apps/Accounts/AuthSource/External.php");

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** Uses an FTP server for authentication */
class FTP extends External
{
    use TableTypes\TableNoChildren;
    
    /** Hostname of the FTP server */
    private FieldTypes\StringType $hostname;
    /** Port number to use (null for default) */
    private FieldTypes\NullIntType $port;
    /** True if implicit SSL should be used (not STARTTLS) */
    private FieldTypes\BoolType $implssl;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->hostname = $fields[] = new FieldTypes\StringType('hostname');
        $this->port =     $fields[] = new FieldTypes\NullIntType('port');
        $this->implssl =  $fields[] = new FieldTypes\BoolType('implssl');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    public static function GetPropUsage() : string { return "--hostname hostname [--port ?uint16] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $obj = parent::Create($database, $params);

        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        $obj->implssl->SetValue($params->GetOptParam('implssl',false)->GetBool());
        
        return $obj;
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname'))
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        
        if ($params->HasParam('port'))
            $this->port->SetValue($params->GetParam('port')->GetNullUint16());
        
        if ($params->HasParam('implssl'))
            $this->implssl->SetValue($params->GetParam('implssl')->GetBool());

        return $this;
    }
    
    /**
     * Returns a printable client object for this FTP
     * @return array<string, mixed> `{hostname:string, port:?int, implssl:bool}` + External
     * @see External::GetClientObject()
     */
    public function GetClientObject(bool $admin) : array
    {
        return parent::GetClientObject($admin) + array(
            'hostname' => $this->hostname->GetValue(),
            'port' => $this->port->TryGetValue(),
            'implssl' => $this->implssl->GetValue()
        );
    }
    
    private $ftpConn;
    
    /** Asserts that the FTP extension exists */
    public function PostConstruct() : void
    {
        if (!function_exists('ftp_connect')) 
            throw new FTPExtensionException();
    }
    
    /** Initiates a connection to the FTP server */
    public function Activate() : self
    {
        if (isset($this->ftpConn)) return $this;
        
        $host = $this->hostname->GetValue(); 
        $port = $this->port->TryGetValue() ?? 0; // 0 to use default
        
        if ($this->implssl->GetValue()) 
             $this->ftpConn = ftp_ssl_connect($host, $port);
        else $this->ftpConn = ftp_connect($host, $port);
        
        if (!$this->ftpConn) throw new FTPConnectionFailure();
        
        return $this;
    }
    
    public function VerifyAccountPassword(Account $account, string $password) : bool
    {
        $this->Activate();

        try 
        { 
            $success = ftp_login($this->ftpConn, $account->GetUsername(), $password); 
            
            ftp_close($this->ftpConn); unset($this->ftpConn); return $success;
        }
        catch (BaseExceptions\PHPError $e) 
        { 
            ErrorManager::GetInstance()->LogException($e); return false; 
        }
    }
}
