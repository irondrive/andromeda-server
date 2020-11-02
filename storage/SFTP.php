<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\{Main, Exceptions};

require_once(ROOT."/apps/files/storage/FWrapper.php");

class SSHExtensionException extends Exceptions\ServerException    { public $message = "SSH_EXTENSION_MISSING"; }
class SSHConnectionFailure extends Exceptions\ServerException     { public $message = "SSH_CONNECTION_FAILURE"; }
class SSHAuthenticationFailure extends Exceptions\ServerException { public $message = "SSH_AUTHENTICATION_FAILURE"; }

class SFTP extends FWrapper
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'privkey' => null,
            'pubkey' => null,
            'keypass' => null,
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'username' => $this->TryGetScalar('username'),
            'password' => boolval($this->TryGetScalar('password')),
            'pubkey' => boolval($this->TryGetScalar('pubkey')),
            'keypass' => boolval($this->TryGetScalar('keypass')),
        ));
    }
    
    private $ssh = null; private $sftp = null;
    
    public function SubConstruct() : void
    {
        if (!function_exists('ssh2_connect')) throw new SSHExtensionException();
        
        $host = $this->GetScalar('hostname'); $port = $this->TryGetScalar('port') ?? 22;
                
        $this->ssh = ssh2_connect($host, $port);        
        if (!$this->ssh) throw new SSHConnectionFailure();
        
        $username = $this->GetScalar('username');
        $password = $this->TryGetScalar('password');

        $authok = true; $authd = false;      
        if ($password) { $authd = true; $authok &= ssh2_auth_password($this->ssh, $username, $password); }
        
        $datadir = Main::GetInstance()->GetConfig()->GetDataDir();
        $pubkey = $this->TryGetScalar('pubkey');
        $privkey = $this->TryGetScalar('privkey');
        $keypass = $this->TryGetScalar('keypass');
        
        if ($pubkey) { $authd = true; $authok &= ssh2_auth_pubkey_file($this->ssh, $username, $datadir.$pubkey, $datadir.$privkey, $keypass); }
        
        if (!$authok || !$authd) throw new SSHConnectionFailure('methods: '.implode(',',ssh2_auth_none($this->ssh, $username)));

        $this->sftp = ssh2_sftp($this->ssh);
        if (!$this->sftp) throw new SSHConnectionFailure();
    }
    
    public function __destruct()
    {
        try { ssh2_disconnect($this->ssh); } catch (Exceptions\PHPException $e) { }
    }

    protected function GetFullURL(string $path) : string
    {
        return "ssh2.sftp://".$this->sftp."/".$this->GetPath($path);
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        $this->CheckReadOnly();
        if (!ssh2_scp_send($this->ssh, $src, $this->GetPath($dest)))
            throw new FileCreateFailedException();
        return $this;
    }
}