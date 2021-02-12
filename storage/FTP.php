<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

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
class FTPWriteUnsupportedException extends Exceptions\ClientErrorException { public $message = "FTP_DOES_NOT_SUPPORT_MODIFY"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) FTP::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(FTP::class);

/**
 * Allows FTP to be used as a backend storage
 * 
 * The FTP extension's methods are mostly used rather than the fwrapper
 * functions, since the fwrapper functions create a new connection for
 * every call.  fwrapper functions are still used as fallbacks where needed.
 * Uses the credcrypt trait for optionally encrypting server credentials.
 */
class FTP extends FWrapper
{    
    use CredCrypt;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), static::CredCryptGetFieldTemplate(), array(
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
        return array_merge(parent::GetClientObject(), $this->CredCryptGetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".static::CredCryptGetCreateUsage()." --hostname alphanum [--port int] [--implssl bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $account, $filesystem)->CredCryptCreate($input,$account)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT))
            ->SetScalar('implssl', $input->TryGetParam('implssl', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        $implssl = $input->TryGetParam('implssl', SafeParam::TYPE_BOOL);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        if ($implssl !== null) $this->SetScalar('implssl', $implssl);
        
        return parent::Edit($input)->CredCryptEdit($input);
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
        
        return $this;
    }

    public function __destruct()
    {
       foreach ($this->appending_handles as $handle) fclose($handle);

       if (isset($this->ftp)) try { ftp_close($this->ftp); } catch (Exceptions\PHPError $e) { }
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
    
    public function isWriteable() : bool
    {
        try
        {
            $name = Utilities::Random(16).".tmp";            
            $this->CreateFile($name)->DeleteFile($name);
            
            return true;
        }
        catch (StorageException $e){ return false; }        
    }
    
    public function ReadFolder(string $path) : ?array
    {
        if (!$this->isFolder($path)) return null;
        return array_map(function($item){ return basename($item); },
            ftp_nlist($this->ftp, $this->GetPath($path)));
    }
    
    public function ReadBytes(string $path, int $start, int $length) : string
    {
        $this->RemoveAppending($path);
            
        $stropt = stream_context_create(array('ftp'=>array('resume_pos'=>$start)));
        $handle = fopen($this->GetFullURL($path), 'rb', null, $stropt);
        
        $data = fread($handle, $length);
        try { fclose($handle); } catch (\Throwable $e) { 
            ErrorManager::GetInstance()->Log($e); }
        
        if ($data === false || strlen($data) !== $length) 
            throw new FileReadFailedException();
        else return $data;
    }
    
    // FTP does not support writing to random offsets but it does allow
    // the special case of writing to the end of the file (appending)
    
    /** path=>resource array of handles */
    private $appending_handles = array(); 
    
    /** path=>byte array of current byte offsets */
    private $appending_offsets = array();
    
    /** Closes the appending handle for the given path */
    private function RemoveAppending(string $path) : void
    {
        if (array_key_exists($path, $this->appending_handles))
        {
            fclose($this->appending_handles[$path]);
            unset($this->appending_handles[$path]);
            unset($this->appending_offsets[$path]);
        }
    }
    
    /** Creates, tracks and returns an appending context for the given path at the given offset */
    private function TrackAppending(string $path, int $bytes)
    {
        if (!array_key_exists($path, $this->appending_handles))
        {
            $this->appending_offsets[$path] = ftp_size($this->ftp, $this->GetPath($path));
            $this->appending_handles[$path] = fopen($this->GetFullURL($path),'a');
        }
        $this->appending_offsets[$path] += $bytes;
        return $this->appending_handles[$path];
    }
    
    /** Returns true if the given path can be appended at the given offset */
    private function CheckAppending(string $path, int $offset) : bool
    {
        if (array_key_exists($path, $this->appending_offsets))
            return $this->appending_offsets[$path] === $offset;
        else return ftp_size($this->ftp, $this->GetPath($path)) === $offset;
    }

    /**
     * FTP can only append ($start must equal the length of the file)
     * @throws FTPWriteUnsupportedException if not appending
     * @see FWrapper::WriteBytes()
     */
    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->CheckReadOnly();
        if (!$this->CheckAppending($path, $start))
            throw new FTPWriteUnsupportedException();
        
        $handle = $this->TrackAppending($path, strlen($data));
        if (!$handle || fwrite($handle, $data) !== strlen($data)) 
            throw new FileWriteFailedException();
        return $this;
    }
    
    /** @throws FTPWriteUnsupportedException */
    public function Truncate(string $path, int $length) : self
    {
        throw new FTPWriteUnsupportedException();
    }    
    
    public function CreateFile(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFile($path)) return $this;
        $handle = fopen($this->GetFullURL($path),'w');
        if (!$handle) throw new FileCreateFailedException();
        fclose($handle); return $this;
    }
    
    public function ImportFile(string $src, string $dest): self
    {
        $this->CheckReadOnly();
        if (!ftp_put($this->ftp, $this->GetPath($dest), $src))
            throw new FileCreateFailedException();
        return $this;
    }
    
    public function CreateFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if ($this->isFolder($path)) return $this;
        if (!ftp_mkdir($this->ftp, $this->GetPath($path))) throw new FolderCreateFailedException();
        else return $this;
    }
    
    public function DeleteFolder(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFolder($path)) return $this;
        if (!ftp_rmdir($this->ftp, $this->GetPath($path))) throw new FolderDeleteFailedException();
        else return $this;
    }
    
    public function DeleteFile(string $path) : self
    {
        $this->CheckReadOnly();
        if (!$this->isFile($path)) return $this;
        if (!ftp_delete($this->ftp, $this->GetPath($path))) throw new FileDeleteFailedException();
        else return $this;
    }
    
    public function RenameFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FileRenameFailedException();
        return $this;
    }
    
    public function RenameFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FolderRenameFailedException();
        return $this;
    }
    
    public function MoveFile(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FileMoveFailedException();
        return $this;
    }
    
    public function MoveFolder(string $old, string $new) : self
    {
        $this->CheckReadOnly();
        if (!ftp_rename($this->ftp, $this->GetPath($old), $this->GetPath($new)))
            throw new FolderMoveFailedException();
        return $this;
    }
}
