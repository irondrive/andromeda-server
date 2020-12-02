<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/CredCrypt.php");

class SMBExtensionException extends ActivateException    { public $message = "SMB_EXTENSION_MISSING"; }
class SMBConnectionFailure extends ActivateException     { public $message = "SMB_CONNECTION_FAILURE"; }
class SMBFWrapperNotSupported extends StorageException   { public $message = "SMB_FWRAPPER_NOT_SUPPORTED"; }

class FolderReadFailedException extends StorageException  { public $message = "FOLDER_READ_FAILED"; }

Account::RegisterCryptoDeleteHandler(function(ObjectDatabase $database, Account $account){ SMB::DecryptAccount($database, $account); });
// TODO move this to CredCrypt

class SMB extends CredCrypt
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'workgroup' => null,
            'hostname' => null,
            'port' => null
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'workgroup' => $this->TryGetScalar('workgroup'),
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port')
        ));
    }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $account, $filesystem)
            ->SetScalar('workgroup', $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT));
    }
    
    public function Edit(Input $input) : self
    {
        $workgroup = $input->TryGetParam('workgroup', SafeParam::TYPE_ALPHANUM);
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_ALPHANUM);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        
        if ($workgroup !== null) $this->SetScalar('workgroup', $workgroup);
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port); 
        // TODO what if the user actually wants to set it to null...?
        
        return parent::Edit($input);
    }

    public function SubConstruct() : void
    {
        if (!function_exists('smbclient_state_new')) throw new SMBExtensionException();
    }
    
    public function Activate() : self
    {
        if (isset($this->smb)) return $this;

        $workgroup = $this->TryGetScalar('workgroup');
        $username = $this->TryGetUsername();
        $password = $this->TryGetPassword();
        
        $this->smb = smbclient_state_new();

        if (!smbclient_state_init($this->smb, $workgroup, $username, $password))
            throw new SMBConnectionFailure();

        // TODO see smbclient_option_set for port, others

        return $this;
    }

    public function __destruct()
    {
       try { smbclient_state_free($this->smb); } catch (Exceptions\PHPException $e) { }
    }
    
    protected function GetFullURL(string $path = "") : string
    {
        throw new SMBFWrapperNotSupported();
    }

    protected function GetSMBURL(string $path = "") : string
    {
        $hostname = $this->GetScalar('hostname');
        return "smb://$hostname/".$this->GetPath($path);
    }

    public function Test() : self
    {
        $this->Activate();

        /*try
        {*/
            if (!$this->isFolder("/"))
                throw new InvalidStoragePathException(smbclient_state_errno($this->smb));

            if (!$this->GetFilesystem()->isReadOnly())
            {
                $tmpfile = Utilities::Random(16);
                $this->CreateFile($tmpfile);
                $this->DeleteFile($tmpfile);
            } // TODO catch PHPException | StorageException and convert to ActivateException
        /**}
        catch (\Throwable $e) { die('test failed: '.smbclient_state_errno($this->smb)); }*/

        return $this;
    }

    // TODO can do a get free space here

    public function ItemStat(string $path) : ItemStat
    {
        $data = smbclient_stat($this->smb, $this->GetSMBURL($path));
        if (!$data) throw new ItemStatFailedException(smbclient_state_errno($this->smb));
        return new ItemStat($data['atime'], $data['ctime'], $data['mtime'], $data['size']);
    }
    
    public function isFolder(string $path) : bool
    {
        try
        {
            if (!($handle = smbclient_opendir($this->smb, $this->GetSMBURL($path)))) return false;            
            smbclient_closedir($this->smb, $handle); return true;
        }
        catch (Exceptions\PHPException $e) { return false; }
    }
    
    public function isFile(string $path) : bool
    {
        try
        {
            if (!($handle = smbclient_open($this->smb, $this->GetSMBURL($path),'r'))) return false;            
            smbclient_close($this->smb, $handle); return true;
        }
        catch (Exceptions\PHPException $e) { return false; }
    }
    
    public function ReadFolder(string $path) : ?array
    {
        if (!$this->isFolder($path)) return null;

        $handle = smbclient_opendir($this->smb, $this->GetSMBURL($path));
        if (!$handle) throw new FolderReadFailedException(smbclient_state_errno($this->smb));

        $retval = array();
        while (($data = smbclient_readdir($this->smb, $handle)) !== false)
        {
            if (in_array($data['type'],array('file','directory')) &&
                !in_array($data['name'],array('.','..')))
                array_push($retval, $data['name']);
        }

        smbclient_closedir($this->smb, $handle); return $retval;
    }
    
    public function CreateFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFolder($path)) return $this;
        if (!smbclient_mkdir($this->smb, $this->GetSMBURL($path))) 
            throw new FolderCreateFailedException(smbclient_state_errno($this->smb));
        else return $this;
    }
    
    public function DeleteFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFolder($path)) return $this;
        if (!smbclient_rmdir($this->smb, $this->GetSMBURL($path))) 
            throw new FolderDeleteFailedException(smbclient_state_errno($this->smb));
        else return $this;
    }
    
    public function DeleteFile(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFile($path)) return $this;
        if (!smbclient_unlink($this->smb, $this->GetSMBURL($path))) 
            throw new FileDeleteFailedException(smbclient_state_errno($this->smb));
        else return $this;
    }
    
    public function CreateFile(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFile($path)) return $this;
        $handle = smbclient_creat($this->smb, $this->GetSMBURL($path));
        if (!$handle) throw new FileCreateFailedException(smbclient_state_errno($this->smb));
        $this->CloseHandle($handle); return $this;
    }

    protected function GetReadHandle(string $path) { return smbclient_open($this->smb, $path,'r'); }
    protected function GetWriteHandle(string $path) { return smbclient_open($this->smb, $path,'r+'); }
    protected function CloseHandle($handle) : bool { return smbclient_close($this->smb, $handle); }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $handle = $this->GetHandle($this->GetSMBUrl($path), false);        
        if (smbclient_lseek($this->smb, $handle, $start, SEEK_SET) === false)
            throw new FileReadFailedException(smbclient_state_errno($this->smb));
        $data = smbclient_read($this->smb, $handle, $length);
        if ($data === false) throw new FileReadFailedException(smbclient_state_errno($this->smb));
        else return $data;
    }
    
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->CheckReadOnly();
        $handle = $this->GetHandle($this->GetSMBUrl($path), true);
        if (smbclient_lseek($this->smb, $handle, $start, SEEK_SET) === false)
            throw new FileWriteFailedException(smbclient_state_errno($this->smb));
        if (!smbclient_write($this->smb, $handle, $data))
            throw new FileWriteFailedException(smbclient_state_errno($this->smb));
        return $this;
    }
    
    public function Truncate(string $path, int $length) : self
    {
        $this->CheckReadOnly();
        $handle = $this->GetHandle($this->GetSMBUrl($path), true);        
        if (!smbclient_ftruncate($this->smb, $handle, $length))
            throw new FileWriteFailedException(smbclient_state_errno($this->smb));
        return $this;
    }

    public function RenameFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!smbclient_rename($this->smb, $this->GetSMBUrl($old), $this->smb, $this->GetSMBUrl($new)))
            throw new FileRenameFailedException(smbclient_state_errno($this->smb));
        return $this;
    }
    
    public function RenameFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!smbclient_rename($this->smb, $this->GetSMBUrl($old), $this->smb, $this->GetSMBUrl($new)))
            throw new FolderRenameFailedException(smbclient_state_errno($this->smb));
        return $this;
    }
    
    public function MoveFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!smbclient_rename($this->smb, $this->GetSMBUrl($old), $this->smb, $this->GetSMBUrl($new)))
            throw new FileMoveFailedException(smbclient_state_errno($this->smb));
        return $this;
    }
    
    public function MoveFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!smbclient_rename($this->smb, $this->GetSMBUrl($old), $this->smb, $this->GetSMBUrl($new)))
            throw new FolderMoveFailedException(smbclient_state_errno($this->smb));
        return $this;
    }
}
