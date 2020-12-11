<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/CredCrypt.php");

class SMBExtensionException extends ActivateException { public $message = "SMB_EXTENSION_MISSING"; }

Account::RegisterCryptoDeleteHandler(function(ObjectDatabase $database, Account $account){ SMB::DecryptAccount($database, $account); });

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
            ->SetScalar('workgroup', $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_ALPHANUM));
    }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('workgroup')) $this->SetScalar('workgroup', $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM));
        if ($input->HasParam('hostname')) $this->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_ALPHANUM));
        
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
    
    // TODO can do a get free space here

}
