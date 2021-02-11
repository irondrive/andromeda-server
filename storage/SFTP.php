<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\{Main, Exceptions};

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");
require_once(ROOT."/apps/files/storage/CredCrypt.php");

/** Exception indicating that the SSH extension is not installed */
class SSHExtensionException extends ActivateException    { public $message = "SSH_EXTENSION_MISSING"; }

/** Exception indicating that the OpenSSL extension is not installed */
class OpenSSLExtensionException extends ActivateException { public $message = "OPENSSL_EXTENSION_MISSING"; }

/** Exception indicating that the SSH connection failed */
class SSHConnectionFailure extends ActivateException     { public $message = "SSH_CONNECTION_FAILURE"; }

/** Exception indicating that SSH authentication failed */
class SSHAuthenticationFailure extends ActivateException { public $message = "SSH_AUTHENTICATION_FAILURE"; }

/** Exception indicating that parsing the given keyfile failed */
class InvalidKeyfileException extends ActivateException  { public $message = "SSH_INVALID_KEYFILE"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) SFTP::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(SFTP::class);

/**
 * Allows using an SFTP server for backend storage using PHP fwrapper
 * 
 * Uses PHP's SSH2 extension for SFTP, and OpenSSL for parsing keys.
 * Uses credcrypt to allow encrypting the username/password.
 */
class SFTP extends FWrapper
{
    use CredCrypt;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), static::CredCryptGetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'privkey' => null, // path to private key
            'pubkey' => null,  // path to public key
            'keypass' => null, // key for unlocking the private key
            'keypass_nonce' => null,
        ));
    }
    
    /**
     * Returns a printable client object of this SFTP storage
     * @return array `{hostname:string, port:?int, pubkey:bool, keypass:bool}`
     * @see FWrapper::GetClientObject()
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), $this->CredCryptGetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'pubkey' => boolval($this->TryGetScalar('pubkey')),
            'keypass' => boolval($this->TryGetKeypass()),
        ));
    }

    /** Returns the configured username (mandatory) */
    protected function GetUsername() : string { return $this->GetEncryptedScalar('username'); }
    
    /** Returns the password for the private key */
    protected function TryGetKeypass() : ?string { return $this->TryGetEncryptedScalar('keypass'); }
    
    /** Sets the password for the private key, encrypted if $credcrypt */
    protected function SetKeypass(?string $keypass, bool $credcrypt) : self { return $this->SetEncryptedScalar('keypass',$keypass,$credcrypt); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".static::CredCryptGetCreateUsage()." --hostname alphanum [--port int] [--file file keyfile] [--keypass raw]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        $credcrypt = $input->TryGetParam('credcrypt', SafeParam::TYPE_BOOL) ?? false;   
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);
        
        $obj = parent::Create($database, $input, $account, $filesystem)->CredCryptCreate($input,$account)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT))
            ->SetKeypass($keypass, $credcrypt);

        if (($keyfile = $input->TryGetFile('keyfile')) !== null) 
            $obj->ProcessKeyfile($keyfile, $keypass);
        
        return $obj;
    }
    
    /**
     * Processes an uploaded keyfile into its private/public components and stores it
     * @param string $keyfile the uploaded key file path
     * @param string $keypass private key password if required
     * @throws OpenSSLExtensionException if OpenSSL is not installed
     * @throws InvalidKeyfileException if parsing fails
     */
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
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);        
        if ($keypass !== null) $this->SetKeypass($keypass, $this->hasCryptoField('keypass'));
        
        if (($keyfile = $input->TryGetFile('keyfile')) !== null)
            $this->ProcessKeyfile($keyfile, $keypass);
        
        return parent::Edit($input)->CredCryptEdit($input);
    }

    /** ssh2 connection resource */ private $ssh; 
    /** sftp connection resource */ private $sftp;
    
    /** Checks for the SSH2 extension */
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
        if (isset($this->ssh)) try { ssh2_disconnect($this->ssh); } catch (Exceptions\PHPError $e) { }
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