<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

/** Exception indicating that the SSH connection failed */
class SSHConnectionFailure extends ActivateException     { public $message = "SSH_CONNECTION_FAILURE"; }

/** Exception indicating that SSH authentication failed */
class SSHAuthenticationFailure extends ActivateException { public $message = "SSH_AUTHENTICATION_FAILURE"; }

/** Exception indicating that the server's public key has changed */
class HostKeyMismatchException extends ActivateException { public $message = "SSH_HOST_KEY_MISMATCH"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) SFTP::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(SFTP::class);

/**
 * Allows using an SFTP server for backend storage using phpseclib
 * 
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SFTP extends StandardFWrapper
{
    protected static function getEncryptedFields() : array { return array_merge(parent::getEncryptedFields(), array('privkey','keypass')); }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'hostkey' => null,
            'privkey' => null, // private key material for auth
            'keypass' => null, // key for unlocking the private key
        ));
    }
    
    /**
     * Returns a printable client object of this SFTP storage
     * @return array `{hostname:string, port:?int, pubkey:bool, keypass:bool}`
     * @see FWrapper::GetClientObject()
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'hostkey' => $this->TryGetHostKey(),
            'privkey' => boolval($this->TryGetScalar('privkey')),
            'keypass' => boolval($this->TryGetScalar('keypass')),
        ));
    }

    /** Returns the configured username (mandatory) */
    protected function GetUsername() : string { return $this->GetEncryptedScalar('username'); }
    
    /** Returns the private key for authentication */
    protected function TryGetPrivkey() : ?string { return $this->TryGetEncryptedScalar('privkey'); }
    
    /** Returns the password for the private key */
    protected function TryGetKeypass() : ?string { return $this->TryGetEncryptedScalar('keypass'); }
    
    /** Sets the private key used for authentication */
    protected function SetPrivkey(?string $privkey) : self 
    {
        return $this->SetEncryptedScalar('privkey',$privkey); 
    }
    
    /** Sets the password for the private key, encrypted if $fieldcrypt */
    protected function SetKeypass(?string $keypass) : self 
    {
        return $this->SetEncryptedScalar('keypass',$keypass); 
    }

    /** Returns the cached public key for the server host */
    protected function TryGetHostKey() : ? string { return $this->TryGetScalar('hostkey'); }
    
    /** Sets the cached host public key to the given value */
    protected function SetHostKey(?string $val) : self { return $this->SetScalar('hostkey',$val); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--port int] [--privkey% path] [--keypass raw]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    { 
        $obj = parent::Create($database, $input, $filesystem)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->GetOptParam('port', SafeParam::TYPE_UINT))
            ->SetKeypass($input->GetOptParam('keypass', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER));         
        
        if ($input->HasFile('privkey')) $obj->SetPrivkey(file_get_contents($input->GetFile('privkey')));
        
        return $obj;
    }

    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--port ?int] [--privkey% path] [--keypass ?raw] [--resethost bool]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('hostname')) $this->SetScalar('hostname',$input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
        if ($input->HasParam('port')) $this->SetScalar('port',$input->GetNullParam('port', SafeParam::TYPE_UINT));
        
        if ($input->HasParam('keypass')) $this->SetKeypass($input->GetNullParam('keypass',SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER));
        if ($input->HasFile('privkey')) $this->SetPrivkey(file_get_contents($input->GetFile('privkey')));
        
        if ($input->GetOptParam('resethost',SafeParam::TYPE_BOOL)) $this->SetHostKey(null);
        
        return parent::Edit($input);
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
            $sftp = new \phpseclib3\Net\SFTP($host, $port);
            
            $hostkey = $sftp->getServerPublicHostKey();
            
            $cached = $this->TryGetHostKey();
            if ($cached === null) $this->SetHostKey($hostkey);
            else if ($cached !== $hostkey) throw new HostKeyMismatchException();            
        }
        catch (Exceptions\PHPError $e) { throw SSHConnectionFailure::Copy($e); }

        try
        {
            $username = $this->GetUsername();
            $sftp->login($username);
    
            if (($password = $this->TryGetPassword()) !== null) 
                $sftp->login($username, $password);
    
            if (($privkey = $this->TryGetPrivkey()) !== null)
            {
                $keypass = $this->TryGetKeypass();
                $privkey = \phpseclib3\Crypt\PublicKeyLoader::load($privkey, $keypass);            
                $sftp->login($username, $privkey);
            }
    
            if (!$sftp->isAuthenticated()) throw new SSHAuthenticationFailure();
        }
        catch (\RuntimeException $e) { throw SSHAuthenticationFailure::Copy($e); }
        
        $this->sftp = $sftp; return $this;
    }    
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }

    protected function GetFullURL(string $path = "") : string
    {
        return "sftp://".$this->sftp."/".$this->GetPath($path);
    }
    
    protected function SubImportFile(string $src, string $dest) : self
    {
        $mode = \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE;
        
        if (!$this->sftp->put($this->GetPath($dest), $src, $mode))   
            throw new FileCreateFailedException();
            
        return $this;
    }
}