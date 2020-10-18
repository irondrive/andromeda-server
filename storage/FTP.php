<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/storage/Storage.php");

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class FTPExtensionException extends Exceptions\ServerException   { public $message = "FTP_EXTENSION_MISSING"; }
class FTPConnectionFailure extends Exceptions\ServerException    { public $message = "FTP_CONNECTION_FAILURE"; }

class FTP extends Storage
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'path' => null,
            'hostname' => null,
            'port' => null,
            'secure' => null,
            'username' => null,
            'password' => null
        ));
    }
    
    private $ftp = null;
    
    // TODO maybe use PHP regular fopen/fread protocol wrappers stuff?
    // not sure how else to read a byte range with FTP
    // https://stackoverflow.com/questions/20997757/send-partial-of-ftp-stream-to-php-output
    // probably could use a base class here if all the functions will be the same

    public function SubConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
        
        $host = $this->GetScalar('hostname'); $port = $this->GetScalar('port');
        
        if ($this->GetScalar('secure')) $this->ftp = ftp_ssl_connect($host, $port);
        else $this->ftp = $this->ftp = ftp_connect($host, $port);
        
        if ($this->ftp === false) throw new FTPConnectionFailure();
        
        ftp_login($this->ftp, $this->GetScalar('username'), $this->GetScalar('password'));
    }
    
    public function __destruct()
    {
        try { ftp_close($this->ftp); } catch (Exceptions\PHPException $e) { }
    }
}
