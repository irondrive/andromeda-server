<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/Config.php");
require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/File.php");
require_once(ROOT."/apps/files/Folder.php");

require_once(ROOT."/apps/files/storage/Storage.php");
require_once(ROOT."/apps/files/storage/Local.php");
require_once(ROOT."/apps/files/storage/FTP.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, Main};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;

require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;

use Andromeda\Core\Database\ObjectNotFoundException;

class UnknownFileException  extends Exceptions\ClientNotFoundException      { public $message = "UNKNOWN_FILE"; }
class UnknownFolderException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_FOLDER"; }
class UnknownFilesystemException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FILESYSTEM"; }

class DuplicateFileException extends Exceptions\ClientErrorException        { public $message = "FILE_ALREADY_EXISTS"; }
class InvalidFileRangeException extends Exceptions\ClientException          { public $code = 416; }

class FilesApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public function __construct(Main $api)
    {
        parent::__construct($api);
        
        try { $this->config = Config::Load($api->GetDatabase()); }
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }
    }
    
    public function Run(Input $input)
    {
        $this->database = $this->API->GetDatabase();
        
        $this->authenticator = Authenticator::TryAuthenticate($this->database, $input);

        switch($input->GetAction())
        {
            case 'getconfig': return $this->GetConfig($input); break;
            
            case 'upload':     return $this->UploadFile($input); break;  
            case 'download':   return $this->DownloadFile($input); break;
            case 'fileinfo':   return $this->GetFileInfo($input); break;
            
            case 'getfolder':    return $this->GetFolder($input); break;
            case 'createfolder': return $this->CreateFolder($input); break;
            
            case 'deletefile':   return $this->DeleteFile($input); break;
            case 'deletefolder': return $this->DeleteFolder($input); break;            
            case 'renamefile':   return $this->RenameFile($input); break;
            case 'renamefolder': return $this->RenameFolder($input); break;
            case 'movefile':     return $this->MoveFile($input); break;
            case 'movefolder':   return $this->MoveFolder($input); break;
            
            case 'getfilesystems': return $this->GetFilesystems($input); break;
            
            default: throw new UnknownActionException();
        }
    }
    
    protected function GetConfig(Input $input) : array
    {
        $account = $this->authenticator->GetAccount();
        $admin = $account !== null && $account->isAdmin();
        
        // TODO GET CONFIG (if there is any)
    }
    
    protected function UploadFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByID($this->database, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $return = array(); $files = $input->GetFiles();
        foreach (array_keys($files) as $name)
        {
            $file = File::TryLoadByParentAndName($this->database, $parent, $account, $name);
            if ($file !== null)
            {
                if ($overwrite) $file->Delete();
                else throw new DuplicateFileException();
            }
            
            $file = File::Import($this->database, $parent, $account, $name, $files[$name]);
            array_push($return, $file->GetClientObject());
        }        
        return $return;
    }
    
    protected function DownloadFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        // TODO since this is not AJAX, we might want to redirect to a page when doing a 404, etc. user won't want to see a bunch of JSON
        // TODO if no page is configured, configure outmode as PLAIN and just show "404 - not found" with MIME type text/plain (do this at the beginning of this function)
        
        $this->API->GetInterface()->SetOutmode(IOInterface::OUTPUT_NONE);
        
        set_time_limit(0);  
        
        header("Accept-Ranges: bytes");
        header("Cache-Control: max-age=0");
        header("Content-type: application/octet-stream");        
        header('Content-Disposition: attachment; filename="'.$file->GetName().'"');
        header('Content-Transfer-Encoding: binary');

        $fsize = $file->GetSize(); $fstart = 0; $flast = $fsize-1;
        if (isset($_SERVER['HTTP_RANGE'])) // TODO allow supplying this in a param also
        {
            $ranges = explode('=',$_SERVER['HTTP_RANGE']);
            if (count($ranges) != 2 || trim($ranges[0]) != "bytes")
                throw new InvalidFileRangeException();
            
            $ranges = explode('-',$ranges[1]);
            if (count($ranges) != 2) throw new InvalidFileRangeException();
            
            $fstart = intval($ranges[0]); 
            $flast2 = intval($ranges[1]); 
            if ($flast2) $flast = $flast2;
            
            http_response_code(206);
            header("Content-Range: bytes $fstart-$flast/$fsize");            
        }        

        if ($flast >= $fsize || $flast+1 < $fstart)
            throw new InvalidFileRangeException();        
        header("Content-Length: ".($flast-$fstart+1));

        try { while (@ob_end_flush()); } catch (\Throwable $e) { }
        
        $file->CountDownload();
        
        $chunksize = $this->config->GetChunkSize();             
        for ($byte = $fstart; $byte <= $flast; $byte += $chunksize)
        {
            if (connection_aborted()) break;
            $data = $file->ReadBytes($byte, $chunksize);
            $file->CountBandwidth(strlen($data)); echo $data;
        }
        
        return array();
    }
    
    protected function GetFileInfo(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        return $file->GetClientObject();
    }
    
    protected function GetFilesystems(Input $array) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $filesystems = Filesystem::LoadByAccount($this->database, $account);
        return array_map(function($filesystem){ return $filesystem->GetClientObject(); }, $filesystems);
    }
    
    protected function GetFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = $input->TryGetParam('folder',SafeParam::TYPE_ID);        

        if ($folder !== null)
        {
            $folder = Folder::TryLoadByAccountAndID($this->database, $account, $folder);
        }
        else
        {
            $filesys = $input->TryGetParam('filesystem',SafeParam::TYPE_ID);
            if ($filesys !== null)
            {
                $filesys = FSManager::TryLoadByID($this->database, $filesys);  
                if ($filesys === null) throw new UnknownFilesystemException();
            }
                
            $folder = Folder::LoadRootByAccount($this->database, $account, $filesys);
        }

        if ($folder === null) throw new UnknownFolderException();

        // TODO user param to get only folders or only files

        $recursive = $input->TryGetParam('recursive',SafeParam::TYPE_BOOL) ?? false;
        $return = $folder->CountVisit()->GetClientObject($recursive ? Folder::RECURSIVE : Folder::WITHCONTENT);
        if ($return === null) throw new UnknownFolderException(); return $return;
    }
    
    protected function CreateFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();

        $name = $input->GetParam('name',SafeParam::TYPE_TEXT);
        
        $folder = Folder::TryLoadByParentAndName($this->database, $parent, $account, $name);
        if ($folder !== null) return $folder->GetClientObject();
 
        return Folder::Create($this->database, $parent, $account, $name)->GetClientObject();
    }
    
    protected function DeleteFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        // TODO DELETE allow sending an array of items to delete also... batch? piecemeal transactions?
        
        $file->Delete();
        return array();
    }
    
    protected function DeleteFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('folder',SafeParam::TYPE_ID));
        if ($folder === null) throw new UnknownFolderException();
        
        // TODO DELETE allow sending an array of items to delete also... batch? piecemeal transactions?
        
        $folder->Delete();        
        return array();
    }
    
    protected function RenameFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        $name = basename($input->GetParam('name',SafeParam::TYPE_TEXT));        
        return $file->SetName($name)->GetClientObject();
    }
    
    protected function RenameFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('folder',SafeParam::TYPE_ID));
        if ($folder === null) throw new UnknownFolderException();
        
        $name = basename($input->GetParam('name',SafeParam::TYPE_TEXT));        
        return $folder->SetName($name)->GetClientObject();
    }
    
    protected function MoveFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();
        
        // TODO batch moving of files also
        
        return $file->SetParent($parent)->GetClientObject();
    }
    
    protected function MoveFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('folder',SafeParam::TYPE_ID));
        if ($folder === null) throw new UnknownFolderException();
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();
        
        // TODO batch moving of folders also

        return $folder->SetParent($parent)->GetClientObject();
    }
}

