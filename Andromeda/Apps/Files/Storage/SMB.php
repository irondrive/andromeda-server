<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/Storage/Exceptions.php");
require_once(ROOT."/Apps/Files/Storage/FWrapper.php");

/** Exception indicating that the libsmbclient extension is missing */
class SMBExtensionException extends ActivateException { public $message = "SMB_EXTENSION_MISSING"; }

/** Exception indicating that the SMB state initialization failed */
class SMBStateInitException extends ActivateException { public $message = "SMB_STATE_INIT_FAILED"; }

/** Exception indicating that SMB failed to connect or read the base path */
class SMBConnectException extends ActivateException { public $message = "SMB_CONNECT_FAILED"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ 
    if (!$init) SMB::DecryptAccount($database, $account); });

abstract class SMBBase1 extends FWrapper { use BasePath; }
abstract class SMBBase2 extends SMBBase1 { use UserPass; }

/**
 * Allows using an SMB/CIFS server for backend storage
 * 
 * Uses PHP libsmbclient.  Mostly uses the PHP fwrapper
 * functions but some manual workarounds are needed.
 * Uses fieldcrypt to allow encrypting the username/password.
 */
class SMB extends SMBBase2
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'workgroup' => new FieldTypes\StringType(),
            'hostname' => new FieldTypes\StringType()
        ));
    }
    
    /**
     * Returns a printable client object of this SMB storage
     * @return array `{workgroup:?string, hostname:string}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return array_merge(parent::GetClientObject($activate), array(
            'workgroup' => $this->TryGetScalar('workgroup'),
            'hostname' => $this->GetScalar('hostname')
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--workgroup alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('workgroup', $input->GetOptParam('workgroup', 
                SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, null, SafeParam::MaxLength(255)))
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--workgroup ?alphanum]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('workgroup')) $this->SetScalar('workgroup', $input->GetNullParam('workgroup', 
            SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, null, SafeParam::MaxLength(255)));
        
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
    
    private $state;
    
    public function Activate() : self
    {
        if (isset($this->state)) return $this;
        
        $state = smbclient_state_new();
        
        if (!is_resource($state) || smbclient_state_init($state) !== true)
            throw new SMBStateInitException();
        
        if (!smbclient_option_set($state, 
            SMBCLIENT_OPT_ENCRYPT_LEVEL, 
            SMBCLIENT_ENCRYPTLEVEL_REQUEST))
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
                
        $connstr = "";
        if ($workgroup) $connstr .= "$workgroup;";
        if ($username) $connstr .= $username;
        if ($password) $connstr .= ":$password";
        if ($connstr) $connstr .= "@";
        
        $connstr = "smb://".$connstr.$this->GetScalar('hostname');
                
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
