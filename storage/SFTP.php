<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");
require_once(ROOT."/apps/files/storage/CredCrypt.php");

/** Exception indicating that the SSH connection failed */
class SSHConnectionFailure extends ActivateException     { public $message = "SSH_CONNECTION_FAILURE"; }

/** Exception indicating that SSH authentication failed */
class SSHAuthenticationFailure extends ActivateException { public $message = "SSH_AUTHENTICATION_FAILURE"; }

/** Exception indicating that the server's public key has changed */
class HostKeyMismatchException extends ActivateException { public $message = "SSH_HOST_KEY_MISMATCH"; }

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
            'hostkey' => null,
            'privkey' => null, // private key material for auth
            'keypass' => null, // key for unlocking the private key
            'privkey_nonce' => null,
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
            'hostkey' => $this->TryGetHostKey(),
            'privkey' => boolval($this->TryGetScalar('privkey')),
            'keypass' => boolval($this->TryGetKeypass()),
        ));
    }

    /** Returns the configured username (mandatory) */
    protected function GetUsername() : string { return $this->GetEncryptedScalar('username'); }
    
    /** Returns the private key for authentication */
    protected function TryGetPrivkey() : ?string { return $this->TryGetEncryptedScalar('privkey'); }
    
    /** Returns the password for the private key */
    protected function TryGetKeypass() : ?string { return $this->TryGetEncryptedScalar('keypass'); }
    
    /** Sets the private key used for authentication */
    protected function SetPrivkey(?string $privkey, ?bool $credcrypt = null) : self 
    {
        $credcrypt ??= $this->hasCryptoField('privkey');
        return $this->SetEncryptedScalar('privkey',$privkey,$credcrypt); 
    }
    
    /** Sets the password for the private key, encrypted if $credcrypt */
    protected function SetKeypass(?string $keypass, ?bool $credcrypt = null) : self 
    {
        $credcrypt ??= $this->hasCryptoField('keypass');
        return $this->SetEncryptedScalar('keypass',$keypass,$credcrypt); 
    }
    
    /** Returns the cached public key for the server host */
    protected function TryGetHostKey() : ? string { return $this->TryGetScalar('hostkey'); }
    
    /** Sets the cached host public key to the given value */
    protected function SetHostKey(?string $val) : self { return $this->SetScalar('hostkey',$val); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".static::CredCryptGetCreateUsage()." --hostname alphanum [--port int] [--file file privkey] [--keypass raw]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        $credcrypt = $input->TryGetParam('credcrypt', SafeParam::TYPE_BOOL) ?? false;   
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);
        
        $obj = parent::Create($database, $input, $account, $filesystem)->CredCryptCreate($input,$account)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT));         
        
        if ($input->HasFile('privkey')) $obj->SetPrivkey(file_get_contents($input->GetFile('privkey')), $credcrypt);
        
        $obj->SetKeypass($keypass, $credcrypt);
        
        return $obj;
    }

    public static function GetEditUsage() : string { return parent::GetEditUsage()." ".static::CredCryptGetEditUsage()." --hostname alphanum [--port int] [--file file privkey] [--keypass raw] [--resethost bool]"; }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        
        $keypass = $input->TryGetParam('keypass', SafeParam::TYPE_RAW);        
        if ($keypass !== null) $this->SetKeypass($keypass);

        if ($input->HasFile('privkey')) $this->SetPrivkey(file_get_contents($input->GetFile('privkey')));
        
        if ($input->TryGetParam('resethost',SafeParam::TYPE_BOOL)) $this->SetHostKey(null);
        
        return parent::Edit($input)->CredCryptEdit($input);
    }

    /** sftp connection resource */ private $sftp;

    public function Activate() : self
    {
        if (isset($this->sftp)) return $this;
        
        \phpseclib3\Net\SFTP\Stream::register();

        $host = $this->GetScalar('hostname'); 
        $port = $this->TryGetScalar('port') ?? 22;
        
        try
        {
            $this->sftp = new \phpseclib3\Net\SFTP($host, $port);
            
            $hostkey = $this->sftp->getServerPublicHostKey();
            
            $cached = $this->TryGetHostKey();
            if ($cached === null) $this->SetHostKey($hostkey);
            else if ($cached !== $hostkey) throw new HostKeyMismatchException();            
        }
        catch (Exceptions\PHPError $e) { throw SSHConnectionFailure::Copy($e); }

        try
        {
            $username = $this->GetUsername();
            $this->sftp->login($username);
    
            if (($password = $this->TryGetPassword()) !== null) 
                $this->sftp->login($username, $password);
    
            if (($privkey = $this->TryGetPrivkey()) !== null)
            {
                $keypass = $this->TryGetKeypass();
                $privkey = \phpseclib3\Crypt\PublicKeyLoader::load($privkey, $keypass);            
                $this->sftp->login($username, $privkey);
            }
    
            if (!$this->sftp->isAuthenticated()) throw new SSHAuthenticationFailure();
        }
        catch (\RuntimeException $e) { throw SSHAuthenticationFailure::Copy($e); }
        
        return $this;
    }

    protected function GetFullURL(string $path = "") : string
    {
        return "sftp://".$this->sftp."/".$this->GetPath($path);
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        $this->CheckReadOnly(); 
        
        $mode = \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE;
        
        if (!$this->sftp->put($this->GetPath($dest), $src, $mode))   
            throw new FileCreateFailedException();
            
        return $this;
    }
    
    // WORKAROUND - is_writeable does not work on directories
    public function isWriteable() : bool { return $this->TestWriteable(); }
    
    // WORKAROUND writing past the end of a file is not supported
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $length = $start+strlen($data);
        
        if ($length > filesize($this->GetFullURL($path))) 
            $this->Truncate($path, $length);
        
        return parent::WriteBytes($path, $start, $data);
    }
}