<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/CredCrypt.php");

class SMBExtensionException extends ActivateException { public $message = "SMB_EXTENSION_MISSING"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) SMB::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(SMB::class);

class SMB extends CredCrypt
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'workgroup' => null,
            'hostname' => null
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'workgroup' => $this->TryGetScalar('workgroup'),
            'hostname' => $this->GetScalar('hostname')
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--workgroup alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $account, $filesystem)
            ->SetScalar('workgroup', $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(255)))
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
    }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('workgroup')) $this->SetScalar('workgroup', $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(255)));
        if ($input->HasParam('hostname')) $this->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
        
        return parent::Edit($input);
    }
    
    public function TryGetWorkgroup() : ?string { return $this->TryGetScalar('workgroup'); }

    public function SubConstruct() : void
    {
        if (!function_exists('smbclient_version')) throw new SMBExtensionException();
    }
    
    public function Activate() : self { return $this; }

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
    protected function GetReadHandle(string $path) { return fopen($path,'r'); }
    protected function GetWriteHandle(string $path) { return fopen($path,'r+'); }
    
    // WORKAROUND: php-smbclient does not implement ftruncate as it should
    public function Truncate(string $path, int $length) : self
    {
        $this->CheckReadOnly();
        $this->ClosePath($path); // close existing handles
        
        $state = smbclient_state_new();
        if ($state === false) throw new FileWriteFailedException();
        
        if (smbclient_state_init($state) === 1) 
            throw new FileWriteFailedException();
        
        $handle = smbclient_open($state, $this->GetFullURL($path), 'r+');
        if ($handle === false) throw new FileWriteFailedException();
            
        if (!smbclient_ftruncate($state, $handle, $length))
            throw new FileWriteFailedException();
        
        if (!smbclient_close($state, $handle))
            throw new FileWriteFailedException();

        return $this;
    }
    
    // WORKAROUND: php-smbclient seems to only be able to read 8K at a time
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $path = $this->GetFullURL($path);
        $handle = $this->GetHandle($path, false);
        
        $blocksize = 8192; $data = array();

        for ($byte = $start; $byte < $start + $length; )
        {
            if (fseek($handle, $byte) !== 0)
                throw new FileReadFailedException();
            
            $delta = min($start+$length-$byte, $blocksize - $byte%$blocksize);
            
            $temp = fread($handle, $delta);
            if ($temp === false || strlen($temp) !== $delta)
                throw new FileReadFailedException();
            
            array_push($data, $temp); $byte += $delta;          
        }
        
        return implode('', $data);
    }
    
    // TODO can do a get free space here

}
