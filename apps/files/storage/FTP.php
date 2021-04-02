<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

/** Exception indicating that the FTP extension is not installed */
class FTPExtensionException extends ActivateException    { public $message = "FTP_EXTENSION_MISSING"; }

/** Exception indicating that the FTP server connection failed */
class FTPConnectionFailure extends ActivateException     { public $message = "FTP_CONNECTION_FAILURE"; }

/** Exception indicating that authentication on the FTP server failed */
class FTPAuthenticationFailure extends ActivateException { public $message = "FTP_AUTHENTICATION_FAILURE"; }

/** Exception indicating that a random write was requested (FTP does not support it) */
class FTPAppendOnlyException extends Exceptions\ClientErrorException { public $message = "FTP_WRITE_APPEND_ONLY"; }

/** Exception indicating that FTP does not support file copy */
class FTPCopyFileException extends Exceptions\ClientErrorException { public $message = "FTP_NO_COPY_SUPPORT"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) FTP::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(FTP::class);

/**
 * Allows FTP to be used as a backend storage
 * 
 * The FTP extension's methods are mostly used rather than the fwrapper
 * functions, since the fwrapper functions create a new connection for
 * every call.  fwrapper functions are still used as fallbacks where needed.
 * Uses the fieldcrypt trait for optionally encrypting server credentials.
 */
class FTP extends StandardFWrapper
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'implssl' => null, // if true, use implicit SSL, else explicit/none
        ));
    }
    
    /**
     * Returns a printable client object of this FTP storage
     * @return array `{hostname:string, port:?int, implssl:bool}`
     * @see FWrapper::GetClientObject()
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--port int] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->GetOptParam('port', SafeParam::TYPE_UINT))
            ->SetScalar('implssl', $input->GetOptParam('implssl', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--port ?int] [--implssl bool]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('hostname')) $this->SetScalar('hostname',$input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
        if ($input->HasParam('implssl')) $this->SetScalar('implssl',$input->GetParam('implssl', SafeParam::TYPE_BOOL));
        if ($input->HasParam('port')) $this->SetScalar('port',$input->GetNullParam('port', SafeParam::TYPE_UINT));
        
        return parent::Edit($input);
    }
    
    /** Check for the FTP extension */
    public function SubConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
    }
    
    /** The FTP connection resource */ private $ftp;
    
    public function Activate() : self
    {
        if (isset($this->ftp)) return $this;
        
        $host = $this->GetScalar('hostname'); 
        $port = $this->TryGetScalar('port') ?? 21;
        $user = $this->TryGetUsername() ?? 'anonymous';
        $pass = $this->TryGetPassword() ?? "";
        
        if ($this->GetScalar('implssl')) 
            $ftp = ftp_ssl_connect($host, $port);
        else $ftp = ftp_connect($host, $port);
        
        if (!$ftp) throw new FTPConnectionFailure();
        
        if (!ftp_login($ftp, $user, $pass)) throw new FTPAuthenticationFailure();
        
        ftp_pasv($ftp, true);
        
        $this->ftp = $ftp; return $this;
    }

    protected function GetFullURL(string $path = "") : string
    {
        $port = $this->TryGetScalar('port') ?? "";
        $username = rawurlencode($this->TryGetUsername() ?? "");
        $password = rawurlencode($this->TryGetPassword() ?? "");
        
        $proto = $this->GetScalar('implssl') ? "ftps" : "ftp";
        $usrstr = $username ? "$username:$password@" : "";
        $hostname = $this->GetScalar('hostname');
        $portstr = $port ? ":$port" : "";
        $connectstr = "$proto://$usrstr$hostname$portstr";
        
        return $connectstr.'/'.$this->GetPath($path);
    }
    
    // even though FTP can uses PHP's fwrapper, we'll override most of the
    // required methods anyway because it's faster to reuse one connection

    public function ItemStat(string $path) : ItemStat
    {
        $size = max(ftp_size($this->ftp, $this->GetPath($path)),0);
        $mtime = max(ftp_mdtm($this->ftp, $this->GetPath($path)),0);
        // FTP does not support atime or ctime!
        return new ItemStat(0, 0, $mtime, $size);
    }
    
    public function isFolder(string $path) : bool
    {
        try { return ftp_chdir($this->ftp, $this->GetPath($path)); }
        catch (\Throwable $e) { return false; }
    }
    
    public function isFile(string $path) : bool
    {
        return ftp_size($this->ftp, $this->GetPath($path)) >= 0;
    }
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }
    
    protected function SubReadFolder(string $path) : array
    {        
        $list = ftp_nlist($this->ftp, $this->GetPath($path));
        if ($list === false) throw new FolderReadFailedException();
        return array_map(function($item){ return basename($item); }, $list);
    }    
    
    protected function SubCreateFolder(string $path) : self
    {
        if (!ftp_mkdir($this->ftp, $this->GetPath($path))) 
            throw new FolderCreateFailedException();
        else return $this;
    }
    
    protected function SubCreateFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if (!($handle = fopen($this->GetFullURL($path),'w'))) 
            throw new FileCreateFailedException();
        
        if (!fclose($handle)) throw new FileCreateFailedException(); 
        
        return $this;
    }
    
    protected function SubImportFile(string $src, string $dest): self
    {
        $this->ClosePath($dest);
        
        if (!ftp_put($this->ftp, $this->GetPath($dest), $src))
            throw new FileCreateFailedException();
        return $this;
    }
    
    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if ($isWrite)
        {
            $fsize = ftp_size($this->ftp, $this->GetPath($path));
            
            if ($offset !== $fsize) throw new FTPAppendOnlyException();
            
            $handle = fopen($this->GetFullURL($path), 'a');
        }
        else
        {
            $stropt = stream_context_create(array('ftp'=>array('resume_pos'=>$offset)));
            
            $handle = fopen($this->GetFullURL($path), 'rb', null, $stropt);
        }
        
        if (!$handle) throw new FileOpenFailedException();
        
        return new FileContext($handle, $offset, $isWrite);
    }
    
    /** @throws FTPAppendOnlyException */
    protected function SubTruncate(string $path, int $length) : self
    {
        if (!$length) $this->DeleteFile($path)->CreateFile($path);
        else if (ftp_size($this->ftp, $this->GetPath($path)) !== $length)
            throw new FTPAppendOnlyException();
        return $this;
    }    
    
    protected function SubDeleteFile(string $path) : self
    {
        $this->ClosePath($path);
        
        if (!ftp_delete($this->ftp, $this->GetPath($path)))
            throw new FileDeleteFailedException();
            else return $this;
    }
    
    protected function SubDeleteFolder(string $path) : self
    {
        if (!ftp_rmdir($this->ftp, $this->GetPath($path))) 
            throw new FolderDeleteFailedException();
        else return $this;
    }

    protected function SubRenameFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FileRenameFailedException();
        return $this;
    }
    
    protected function SubRenameFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FolderRenameFailedException();
        return $this;
    }
        
    protected function SubMoveFile(string $old, string $new) : self
    {
        $this->ClosePath($old); $this->ClosePath($new);
        
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FileMoveFailedException();
        return $this;
    }
    
    protected function SubMoveFolder(string $old, string $new) : self
    {
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FolderMoveFailedException();
        return $this;
    }
    
    protected function SubCopyFile(string $old, string $new) : self
    {
        throw new FTPCopyFileException();
    }
}
