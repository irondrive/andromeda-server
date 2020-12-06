<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\{Main, Exceptions};

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

class SSHExtensionException extends ActivateException    { public $message = "SSH_EXTENSION_MISSING"; }
class OpenSSLExtensionException extends ActivateException { public $message = "OPENSSL_EXTENSION_MISSING"; }

class SSHConnectionFailure extends ActivateException     { public $message = "SSH_CONNECTION_FAILURE"; }
class SSHAuthenticationFailure extends ActivateException { public $message = "SSH_AUTHENTICATION_FAILURE"; }
class InvalidKeyfileException extends ActivateException  { public $message = "SSH_INVALID_KEYFILE"; }

Account::RegisterCryptoDeleteHandler(function(ObjectDatabase $database, Account $account){ SFTP::DecryptAccount($database, $account); });

class SFTP extends CredCrypt
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'privkey' => null,
            'pubkey' => null,
            'keypass' => null,
            'keypass_nonce' => null,
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'pubkey' => boolval($this->TryGetScalar('pubkey')),
            'keypass' => boolval($this->TryGetKeypass()),
        ));
    }

    // TODO get free space?

    protected function GetUsername() : string { return $this->GetEncryptedScalar('username'); }    
    protected function TryGetKeypass() : ?string { return $this->TryGetEncryptedScalar('keypass'); }    
    protected function SetKeypass(?string $keypass, bool $credcrypt) : self { return $this->SetEncryptedScalar('keypass',$keypass,$credcrypt); }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        $credcrypt = $input->TryGetParam('credcrypt', SafeParam::TYPE_BOOL) ?? false;   
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);
        
        $obj = parent::Create($database, $input, $account, $filesystem)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT))
            ->SetKeypass($keypass, $credcrypt);

        if (($keyfile = $input->TryGetFile('keyfile')) !== null) 
            $obj->ProcessKeyfile($keyfile, $keypass);
        
        return $obj;
    }
    
    private function ProcessKeyfile(string $keyfile, ?string $keypass) : void
    {
        if (!function_exists('openssl_pkey_get_private'))
            throw new OpenSSLExtensionException();

        $keyfile = file_get_contents($keyfile);
        $keyrsrc = openssl_pkey_get_private($keyfile, $keypass);
        if ($keyrsrc === false) throw new InvalidKeyfileException();
        
        $pubkey = openssl_pkey_get_details($keyrsrc)['key'];
        
        $fpath = implode('/',array('sftp_keys', $this->GetObjectID('filesystem')));
        $privfile = "$fpath/privkey"; $pubfile = "$fpath/pubkey";
        
        $datadir = Main::GetInstance()->GetConfig()->GetDataDir();
        file_put_contents("$datadir/$privfile", $keyfile);
        file_put_contents("$datadir/$pubfile", $pubkey);
        
        $this->SetScalar('privkey', $privfile)->SetScalar('pubkey', $pubfile);
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_ALPHANUM);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);        
        if ($keypass !== null) $this->SetKeypass($keypass, $this->hasCryptoField('keypass'));
        
        if (($keyfile = $input->TryGetFile('keyfile')) !== null)
            $this->ProcessKeyfile($keyfile, $keypass);
        
        return parent::Edit($input);
    }

    private $ssh; private $sftp;
    
    public function SubConstruct() : void
    {
        if (!function_exists('ssh2_connect')) throw new SSHExtensionException();
    }
    
    public function Activate() : self
    {
        if (isset($this->ssh)) return $this;
        
        $host = $this->GetScalar('hostname'); 
        $port = $this->TryGetScalar('port') ?? 22;
                
        $this->ssh = ssh2_connect($host, $port);        
        if (!$this->ssh) throw new SSHConnectionFailure();
        
        $username = $this->GetUsername();
        $password = $this->TryGetPassword();

        $authok = true; $authd = false;      
        if ($password) { $authd = true; $authok &= ssh2_auth_password($this->ssh, $username, $password); }
        
        $datadir = Main::GetInstance()->GetConfig()->GetDataDir();
        $pubkey = $this->TryGetScalar('pubkey');
        $privkey = $this->TryGetScalar('privkey');
        $keypass = $this->TryGetKeypass();
        
        if ($pubkey) { $authd = true; $authok &= ssh2_auth_pubkey_file($this->ssh, $username, $datadir.$pubkey, $datadir.$privkey, $keypass); }
        
        if (!$authok || !$authd) throw new SSHConnectionFailure('methods: '.implode(',',ssh2_auth_none($this->ssh, $username)));

        $this->sftp = ssh2_sftp($this->ssh);
        if (!$this->sftp) throw new SSHConnectionFailure();
        
        return $this;
    }
    
    public function __destruct()
    {
        try { ssh2_disconnect($this->ssh); } catch (Exceptions\PHPException $e) { }
    }

    protected function GetFullURL(string $path = "") : string
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