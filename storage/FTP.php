<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/CredCrypt.php");

class FTPExtensionException extends ActivateException    { public $message = "FTP_EXTENSION_MISSING"; }
class FTPConnectionFailure extends ActivateException     { public $message = "FTP_CONNECTION_FAILURE"; }
class FTPAuthenticationFailure extends ActivateException { public $message = "FTP_AUTHENTICATION_FAILURE"; }

class FTPWriteUnsupportedException extends Exceptions\ClientErrorException { public $message = "FTP_DOES_NOT_SUPPORT_MODIFY"; }

Account::RegisterCryptoDeleteHandler(function(ObjectDatabase $database, Account $account){ FTP::DecryptAccount($database, $account); });

class FTP extends CredCrypt
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'secure' => null,
        ));
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'secure' => $this->GetScalar('secure'),
        ));
    }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $account, $filesystem)
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT) ?? 21)
            ->SetScalar('secure', $input->TryGetParam('secure', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_ALPHANUM);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        $secure = $input->TryGetParam('secure', SafeParam::TYPE_BOOL);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        if ($secure !== null) $this->SetScalar('secure', $secure);
        
        return parent::Edit($input);
    }
    
    public function SubConstruct() : void
    {
        if (!function_exists('ftp_connect')) throw new FTPExtensionException();
    }
    
    public function Activate() : self
    {
        if (isset($this->ftp)) return $this;
        
        $host = $this->GetScalar('hostname'); $port = $this->TryGetScalar('port');
        $user = $this->TryGetUsername('username') ?? 'anonymous';
        $pass = $this->TryGetPassword('password') ?? "";
        
        if ($this->GetScalar('secure')) $this->ftp = ftp_ssl_connect($host, $port);
        else $this->ftp = $this->ftp = ftp_connect($host, $port);
        if (!$this->ftp) throw new FTPConnectionFailure();
        
        if (!ftp_login($this->ftp, $user, $pass)) throw new FTPAuthenticationFailure();
        
        return $this;
    }

    public function __destruct()
    {
      foreach ($this->appending_handles as $handle) fclose($handle);
           try { ftp_close($this->ftp); } catch (Exceptions\PHPException $e) { }
       
       try { ftp_close($this->ftp); } catch (Exceptions\PHPException $e) { }
    }
    
    protected function GetFullURL(string $path = "") : string
    {
        $port = $this->TryGetScalar('port') ?? "";
        $username = $this->TryGetUsername() ?? "";
        $password = $this->TryGetPassword() ?? "";
        
        $proto = $this->GetScalar('secure') ? "ftps" : "ftp";
        $usrstr = $username ? "$username:$password@" : "";
        $hostname = $this->GetScalar('hostname');
        $portstr = $port ? ":$port" : "";
        return "$proto://$usrstr$hostname$portstr/".$this->GetPath($path);
    }

    public function ItemStat(string $path) : ItemStat
    {
        $size = max(ftp_size($this->ftp, $this->GetPath($path)),0);
        $mtime = max(ftp_mdtm($this->ftp, $this->GetPath($path)),0);
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
        try { fclose($handle); } catch (\Throwable $e) { }
        
        if ($data === false) throw new FileReadFailedException();
        else return $data;
    }
    
    private $appending_handles = array(); 
    private $appending_offsets = array();
    
    private function RemoveAppending(string $path) : void
    {
        if (array_key_exists($path, $this->appending_handles))
        {
            fclose($this->appending_handles[$path]);
            unset($this->appending_handles[$path]);
            unset($this->appending_offsets[$path]);
        }
    }
    
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
    
    private function CheckAppending(string $path, int $offset) : bool
    {
        if (array_key_exists($path, $this->appending_offsets))
            return $this->appending_offsets[$path] === $offset;
        else return ftp_size($this->ftp, $this->GetPath($path)) === $offset;
    }

    public function WriteBytes(string $path, int $start, string $data) : self
    {
        $this->CheckReadOnly();
        if (!$this->CheckAppending($path, $start))
            throw new FTPWriteUnsupportedException();
        
        $handle = $this->TrackAppending($path, strlen($data));
        if (!$handle) throw new FileWriteFailedException();
        fwrite($handle, $data); return $this;
    }
    
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
