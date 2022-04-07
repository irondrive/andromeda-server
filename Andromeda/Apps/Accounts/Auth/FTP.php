<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Accounts/Auth/External.php");
require_once(ROOT."/Apps/Accounts/Auth/Manager.php");

/** Exception indicating the PHP FTP extension is missing */
class FTPExtensionException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating the FTP connection failed to connect */
class FTPConnectionFailure extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("FTP_CONNECTION_FAILURE", $details);
    }
}

/** Uses an FTP server for authentication */
class FTP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'implssl' => new FieldTypes\BoolType(), // true to use implicit SSL, false for explicit or none
            'hostname' => new FieldTypes\StringType(),
            'port' => new FieldTypes\IntType()
        ));
    }    
    
    public static function GetPropUsage() : string { return "--hostname hostname [--port ?uint16] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        return parent::Create($database, $params)
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname())
            ->SetScalar('implssl', $params->GetOptParam('implssl',false)->GetBool())
            ->SetScalar('port', $params->GetOptParam('port',null)->GetNullUint16());
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname')) $this->SetScalar('hostname',$params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('implssl')) $this->SetScalar('implssl',$params->GetParam('implssl')->GetBool());
        if ($params->HasParam('port')) $this->SetScalar('port',$params->GetParam('port')->GetNullUint16());
        
        return $this;
    }
    
    /**
     * Returns a printable client object for this FTP
     * @return array `{hostname:string, port:?int, implssl:bool}`
     */
    public function GetClientObject() : array
    {
        return array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
        );
    }
    
    private $ftp;
    
    /** Asserts that the FTP extension exists */
    public function SubConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
    }
    
    /** Initiates a connection to the FTP server */
    public function Activate() : self
    {
        if (isset($this->ftp)) return $this;
        
        $host = $this->GetScalar('hostname'); $port = $this->TryGetScalar('port') ?? 21;
        
        if ($this->GetScalar('implssl')) $this->ftp = ftp_ssl_connect($host, $port);
        else $this->ftp = ftp_connect($host, $port);
        
        if (!$this->ftp) throw new FTPConnectionFailure();
        
        return $this;
    }
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $this->Activate();

        try 
        { 
            $success = ftp_login($this->ftp, $username, $password); 
            
            ftp_close($this->ftp); unset($this->ftp); return $success;
        }
        catch (Exceptions\PHPError $e) 
        { 
            ErrorManager::GetInstance()->LogException($e); return false; 
        }
    }
}
