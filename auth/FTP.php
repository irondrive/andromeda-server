<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class FTPExtensionException extends Exceptions\ServerException   { public $message = "FTP_EXTENSION_MISSING"; }
class FTPConnectionFailure extends Exceptions\ServerException    { public $message = "FTP_CONNECTION_FAILURE"; }

class FTP extends BaseObject implements Source
{
    private $ftp = null;
    
    public function GetHostname() : string { return $this->GetScalar('hostname'); }
    public function GetPort() : int { return $this->GetScalar('port'); }
    public function GetUseSSL() : bool { return $this->GetScalar('secure'); }
    
    public function GetAccountGroup() : ?Group { return $this->TryGetObject('account_group'); }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        parent::__construct($database, $data);
        
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
        
        $host = $this->GetHostname(); $port = $this->GetPort();
        
        if ($this->GetUseSSL()) $this->ftp = ftp_ssl_connect($host, $port);
        else $this->ftp = $this->ftp = ftp_connect($host, $port);
        
        if ($this->ftp === false) throw new FTPConnectionFailure();
    }
    
    public function VerifyPassword(string $username, string $password) : bool
    {
        if (strlen($username) == 0 || strlen($password) == 0) return false;
        
        try { return ftp_login($this->ftp, $username, $password); }
        catch (Exceptions\PHPException $e) { return false; }       
    }
    
    public function __destruct()
    {
        try { ftp_close($this->ftp); } catch (Exceptions\PHPException $e) { }
    }
}
