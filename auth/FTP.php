<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

-require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/auth/External.php");
require_once(ROOT."/apps/accounts/auth/Manager.php");

class FTPExtensionException extends Exceptions\ServerException   { public $message = "FTP_EXTENSION_MISSING"; }
class FTPConnectionFailure extends Exceptions\ServerException    { public $message = "FTP_CONNECTION_FAILURE"; }

Manager::RegisterAuthType(FTP::class);

/** Uses an FTP server for authentication */
class FTP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'implssl' => null, // true to use implicit SSL, false for explicit or none
            'hostname' => null,
            'port' => null
        ));
    }    
    
    public static function GetPropUsage() : string { return "--hostname alphanum [--port int] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        return parent::Create($database, $input)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT))
            ->SetScalar('implssl', $input->TryGetParam('implssl', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        $implssl = $input->TryGetParam('implssl', SafeParam::TYPE_BOOL);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        if ($implssl !== null) $this->SetScalar('implssl', $implssl);
        
        return $this;
    }
    
    /**
     * Returns a printable client object for this FTP
     * @return array `{hostname:stsring, port:int, implssl:bool}`
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
        
        if ($this->ftp === false) throw new FTPConnectionFailure();
        
        return $this;
    }
    
    public function VerifyPassword(Account $account, string $password) : bool
    {
        $this->Activate(); $username = $account->GetUsername();

        try { return ftp_login($this->ftp, $username, $password); }
        catch (Exceptions\PHPError $e) { return false; }
    }
    
    public function __destruct()
    {
        if (isset($this->ftp)) try { ftp_close($this->ftp); } catch (Exceptions\PHPError $e) { }
    }
}
