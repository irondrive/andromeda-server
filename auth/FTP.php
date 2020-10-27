<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/auth/Local.php");
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class FTPExtensionException extends Exceptions\ServerException   { public $message = "FTP_EXTENSION_MISSING"; }
class FTPConnectionFailure extends Exceptions\ServerException    { public $message = "FTP_CONNECTION_FAILURE"; }

class FTP extends External implements ISource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'secure' => null,
            'hostname' => null,
            'port' => null
        ));
    }
    
    private $ftp = null;

    public function GetAccountGroup() : ?Group { return $this->TryGetObject('default_group'); }
    
    public function SubConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
        
        $host = $this->GetScalar('hostname'); $port = $this->GetScalar('port');
        
        if ($this->GetScalar('secure')) $this->ftp = ftp_ssl_connect($host, $port);
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
