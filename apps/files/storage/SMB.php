<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

/** Exception indicating that the libsmbclient extension is missing */
class SMBExtensionException extends ActivateException { public $message = "SMB_EXTENSION_MISSING"; }

/** Exception indicating that the SMB state initialization failed */
class SMBStateInitException extends ActivateException { public $message = "SMB_STATE_INIT_FAILED"; }

/** Exception indicating that SMB failed to connect or read the base path */
class SMBConnectException extends ActivateException { public $message = "SMB_BASE_CONNECT_FAILED"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) SMB::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(SMB::class);

/**
 * Allows using an SMB/CIFS server for backend storage
 * 
 * Uses PHP libsmbclient.  Mostly uses the PHP fwrapper
 * functions but some manual workarounds are needed.
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SMB extends StandardFWrapper
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'workgroup' => null,
            'hostname' => null
        ));
    }
    
    /**
     * Returns a printable client object of this SMB storage
     * @return array `{workgroup:?string, hostname:string}`
     * FWrapper::GetClientObject()
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'workgroup' => $this->TryGetScalar('workgroup'),
            'hostname' => $this->GetScalar('hostname')
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--workgroup alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('workgroup', $input->GetOptParam('workgroup', 
                SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, SafeParam::MaxLength(255)))            
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--workgroup ?alphanum]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('workgroup')) $this->SetScalar('workgroup', $input->GetNullParam('workgroup', 
            SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, SafeParam::MaxLength(255)));
        
        if ($input->HasParam('hostname')) $this->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
        
        return parent::Edit($input);
    }
    
    /** Returns the SMB workgroup to use (or null) */
    public function TryGetWorkgroup() : ?string { return $this->TryGetScalar('workgroup'); }

    /** Checks for the SMB client extension */
    public function SubConstruct() : void
    {
        if (!function_exists('smbclient_version')) throw new SMBExtensionException();
    }
    
    private $state = false;
    
    public function Activate() : self
    {
        if (isset($this->state)) return $this;
        
        $state = smbclient_state_new();
        if ($state === false) throw new SMBStateInitException();
        
        if (smbclient_state_init($state) === 1)
            throw new SMBStateInitException();
        
        if (!is_readable($this->GetFullURL()))
            throw new SMBConnectException();
        
        $this->state = $state;
        
        return $this; 
    }    

    protected function GetFullURL(string $path = "") : string
    {
        $username = rawurlencode($this->TryGetUsername() ?? "");
        $password = rawurlencode($this->TryGetPassword() ?? "");
        $workgroup = rawurlencode($this->TryGetWorkgroup() ?? "");
                
        $connstr = "smb://";
        if ($workgroup) $connstr .= "$workgroup;";
        if ($username) $connstr .= $username;
        if ($password) $connstr .= ":$password";
        if ($connstr) $connstr .= "@";
        $connstr .= $this->GetScalar('hostname');
                
        return $connstr.'/'.$this->GetPath($path);
    }
    
    // WORKAROUND: php-smbclient does not support b fopen flag
    protected function OpenReadHandle(string $path){ return fopen($this->GetFullURL($path),'r'); }
    protected function OpenWriteHandle(string $path){ return fopen($this->GetFullURL($path),'r+'); }
    
    // WORKAROUND: php-smbclient <= 3.0.5 does not implement stream ftruncate
    protected function SubTruncate(string $path, int $length) : self
    {
        $this->ClosePath($path); // close existing handles
        
        $handle = smbclient_open($this->state, $this->GetFullURL($path), 'r+');
        if (!$handle) throw new FileWriteFailedException();
            
        if (!smbclient_ftruncate($this->state, $handle, $length))
            throw new FileWriteFailedException();
        
        if (!smbclient_close($this->state, $handle))
            throw new FileWriteFailedException();

        return $this;
    }
    
    public function canGetFreeSpace() : bool { return true; }
    
    public function GetFreeSpace() : int
    {
        $data = smbclient_statvfs($this->state, $this->GetFullURL());
        
        if ($data === false) throw new FreeSpaceFailedException();
        
        return $data['frsize'] * $data['bsize'] * $data['bavail'];
    }
}
