<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");
require_once(ROOT."/apps/files/storage/CredCrypt.php");

/** Exception indicating that the FTP extension is not installed */
class FTPExtensionException extends ActivateException    { public $message = "FTP_EXTENSION_MISSING"; }

/** Exception indicating that the FTP server connection failed */
class FTPConnectionFailure extends ActivateException     { public $message = "FTP_CONNECTION_FAILURE"; }

/** Exception indicating that authentication on the FTP server failed */
class FTPAuthenticationFailure extends ActivateException { public $message = "FTP_AUTHENTICATION_FAILURE"; }

/** Exception indicating that a random write was requested (FTP does not support it) */
class FTPWriteUnsupportedException extends Exceptions\ClientErrorException { public $message = "FTP_WRITE_APPEND_ONLY"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) FTP::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(FTP::class);

class FTPHandle
{
    public $handle;
    public int $offset;
    public bool $isAppend;
    
    public function __construct($handle, int $offset, bool $isAppend){
        $this->handle = $handle; $this->offset = $offset; $this->isAppend = $isAppend; }
}

abstract class FTPCredCrypt extends FWrapper { use CredCrypt; }

/**
 * Allows FTP to be used as a backend storage
 * 
 * The FTP extension's methods are mostly used rather than the fwrapper
 * functions, since the fwrapper functions create a new connection for
 * every call.  fwrapper functions are still used as fallbacks where needed.
 * Uses the credcrypt trait for optionally encrypting server credentials.
 */
class FTP extends FTPCredCrypt
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
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --hostname alphanum [--port ?int] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->GetNullParam('port', SafeParam::TYPE_INT))
            ->SetScalar('implssl', $input->GetOptParam('implssl', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--hostname alphanum] [--port ?int] [--implssl bool]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('hostname')) $this->SetScalar('hostname',$input->GetParam('hostname', SafeParam::TYPE_HOSTNAME));
        if ($input->HasParam('implssl')) $this->SetScalar('implssl',$input->GetParam('implssl', SafeParam::TYPE_BOOL));
        if ($input->HasParam('port')) $this->SetScalar('port',$input->GetNullParam('port', SafeParam::TYPE_INT));
        
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
            $this->ftp = ftp_ssl_connect($host, $port);
        else $this->ftp = ftp_connect($host, $port);
        
        if (!$this->ftp) throw new FTPConnectionFailure();
        
        if (!ftp_login($this->ftp, $user, $pass)) throw new FTPAuthenticationFailure();
        
        ftp_pasv($this->ftp, true);
        
        return $this;
    }

    public function __destruct()
    {
       parent::__destruct();
        
       foreach (array_keys($this->handles) as $path) 
           $this->CloseFTPHandle($path);
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
    public function isWriteable() : bool { return $this->TestWriteable(); }
    
    public function ReadFolder(string $path) : array
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
        $handle = fopen($this->GetFullURL($path),'w');
        if (!$handle) throw new FileCreateFailedException();
        fclose($handle); return $this;
    }
    
    protected function SubImportFile(string $src, string $dest): self
    {
        if (!ftp_put($this->ftp, $this->GetPath($dest), $src))
            throw new FileCreateFailedException();
        return $this;
    }
    
    /** @var array[path:FTPHandle] array of read/write handles */
    private $handles = array();
    
    private function CloseFTPHandle(string $path) : void
    {
        if (array_key_exists($path, $this->handles))
        {
            try { fclose($this->handles[$path]); } catch (\Throwable $e) { }
            
            unset($this->handles[$path]);
        }
    }
    
    private function GetFTPHandle(string $path, int $start, bool $isAppend, callable $create)
    {
        $handle = (array_key_exists($path, $this->handles)) ? $this->handles[$path] : null;
        
        if ($handle !== null && ($handle->isAppend != $isAppend || $handle->offset != $start))
        {
            $this->CloseFTPHandle($path); $handle = null;
        }
        
        $handle ??= new FTPHandle($create(), $start, $isAppend);

        $this->handles[$path] = $handle;
        
        return $handle;
    }
    
    private function GetFTPReadHandle(string $path, int $start)
    {
        return $this->GetFTPHandle($path, $start, false, function()use($start,$path)
        {
            $stropt = stream_context_create(array('ftp'=>array('resume_pos'=>$start)));
            return fopen($this->GetFullURL($path), 'rb', null, $stropt);
        });
    }
    
    private function GetFTPWriteHandle(string $path, int $start)
    {
        return $this->GetFTPHandle($path, $start, true, function()use($start,$path)
        {
            $fsize = ftp_size($this->ftp, $this->GetPath($path));
            if ($start != $fsize) throw new FTPWriteUnsupportedException();
            
            return fopen($this->GetFullURL($path),'a');
        });
    }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $handle = $this->GetFTPReadHandle($path, $start);
        
        $data = $this->ReadHandle($handle->handle, $length);
        
        $handle->offset += strlen($data);
        
        return $data;
    }    
    
    /**
     * FTP can only append ($start must equal the length of the file)
     * @throws FTPWriteUnsupportedException if not appending
     * @see FWrapper::WriteBytes()
     */
    protected function SubWriteBytes(string $path, int $start, string $data) : self
    {        
        $handle = $this->GetFTPWriteHandle($path, $start);
        
        $this->WriteHandle($handle->handle, $data);
        
        $handle->offset += strlen($data);
        
        return $this;
    }

    /** @throws FTPWriteUnsupportedException */
    protected function SubTruncate(string $path, int $length) : self
    {
        if (!$length) $this->DeleteFile($path)->CreateFile($path);
        else if (ftp_size($this->ftp, $this->GetPath($path)) !== $length)
            throw new FTPWriteUnsupportedException();
        return $this;
    }    
    
    protected function SubDeleteFolder(string $path) : self
    {
        if (!ftp_rmdir($this->ftp, $this->GetPath($path))) 
            throw new FolderDeleteFailedException();
        else return $this;
    }
    
    protected function SubDeleteFile(string $path) : self
    {
        if (!ftp_delete($this->ftp, $this->GetPath($path)))
            throw new FileDeleteFailedException();
        else return $this;
    }
    
    protected function SubRenameFile(string $old, string $new) : self
    {
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
}
