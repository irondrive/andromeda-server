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

class UnknownItemException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_ITEM"; }
class UnknownFileException  extends Exceptions\ClientNotFoundException      { public $message = "UNKNOWN_FILE"; }
class UnknownFolderException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_FOLDER"; }
class UnknownParentException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_PARENT"; }
class UnknownFilesystemException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FILESYSTEM"; }

class InvalidDLRangeException extends Exceptions\ClientException { public $code = 416; }

class DuplicateFileException extends Exceptions\ClientErrorException        { public $message = "FILE_ALREADY_EXISTS"; }
class DuplicateFolderException extends Exceptions\ClientErrorException      { public $message = "FOLDER_ALREADY_EXISTS"; }
class InvalidFileWriteException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_PARAMS"; }
class InvalidFileRangeException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_RANGE"; }

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
            
            case 'upload':     return $this->UploadFiles($input); break;  
            case 'download':   return $this->DownloadFile($input); break;
            case 'ftruncate':  return $this->TruncateFile($input); break;
            case 'writefile':  return $this->WriteToFile($input); break;
            
            case 'fileinfo':      return $this->GetFileInfo($input); break;
            case 'getfolder':     return $this->GetFolder($input); break;
            case 'getitembypath': return $this->GetItemByPath($input); break;
            case 'createfolder':  return $this->CreateFolder($input); break;
            
            case 'deletefile':   return $this->DeleteFile($input); break;
            case 'deletefolder': return $this->DeleteFolder($input); break;            
            case 'renamefile':   return $this->RenameFile($input); break;
            case 'renamefolder': return $this->RenameFolder($input); break;
            case 'movefile':     return $this->MoveFile($input); break;
            case 'movefolder':   return $this->MoveFolder($input); break;
            
            case 'getfilesystem':  return $this->GetFilesystem($input); break;
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
    
    protected function UploadFiles(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByID($this->database, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownParentException();
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $return = array(); $files = $input->GetFiles();
        if (!count($files)) throw new UnknownFileException();
        foreach (array_keys($files) as $name)
        {
            $file = File::TryLoadByParentAndName($this->database, $parent, $account, $name);
            if ($file !== null)
            {
                if ($overwrite) $file->Delete();
                else throw new DuplicateFileException();
            }
            
            $parent->CountBandwidth(filesize($files[$name]));
            
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

        $fsize = $file->GetSize();        
        $fstart = $input->TryGetParam('fstart',SafeParam::TYPE_INT) ?? 0;
        $flast  = $input->TryGetParam('flast',SafeParam::TYPE_INT) ?? $fsize-1;
        
        if (isset($_SERVER['HTTP_RANGE']))
        {
            $ranges = explode('=',$_SERVER['HTTP_RANGE']);
            if (count($ranges) != 2 || trim($ranges[0]) != "bytes")
                throw new InvalidDLRangeException();
            
            $ranges = explode('-',$ranges[1]);
            if (count($ranges) != 2) throw new InvalidDLRangeException();
            
            $fstart = intval($ranges[0]); 
            $flast2 = intval($ranges[1]); 
            if ($flast2) $flast = $flast2;     
        }

        if ($fstart < 0 || $flast+1 < $fstart || $flast >= $fsize)
            throw new InvalidDLRangeException();
        
        $this->API->GetInterface()->SetOutmode(IOInterface::OUTPUT_NONE);
        
        if ($fstart != 0 || $flast != $fsize-1)
        {
            http_response_code(206);
            header("Content-Range: bytes $fstart-$flast/$fsize");     
        }
        else $file->CountDownload();
        
        header("Content-Length: ".($flast-$fstart+1));       

        header("Accept-Ranges: bytes");
        header("Cache-Control: max-age=0");
        header("Content-type: application/octet-stream");
        header('Content-Disposition: attachment; filename="'.$file->GetName().'"');
        header('Content-Transfer-Encoding: binary');       
        
        set_time_limit(0);

        try { while (@ob_end_flush()); } catch (\Throwable $e) { }
        
        
        $fschunksize = $file->GetChunkSize();
        $chunksize = $this->config->GetChunkSize();     
        
        $align = ($fschunksize !== null);
        if ($align) $chunksize = ceil(min($fsize,$chunksize)/$fschunksize)*$fschunksize;

        for ($byte = $fstart; $byte <= $flast; $byte += $chunksize)
        {
            $maxlen = min($chunksize, $flast - $byte + 1);
            
            if ($align)
            {
                $rstart = intdiv($byte, $chunksize) * $chunksize;                
                $roffset = $byte - $rstart;
                
                $data = $file->ReadBytes($rstart, $chunksize);
                
                if ($roffset || $maxlen != strlen($data))
                    $data = substr($data, $roffset, $maxlen);
                
                $byte = $rstart;
            }
            else $data = $file->ReadBytes($byte, $maxlen);

            $file->CountBandwidth(strlen($data)); 
            if (connection_aborted()) break; else echo $data;
        }

        return array();
    }
    
    protected function WriteToFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        $files = array_values($input->GetFiles());
        if (count($files) === 1) $filepath = $files[0];
        else throw new InvalidFileWriteException();        
        
        $wstart = $input->TryGetParam('offset',SafeParam::TYPE_INT) ?? 0;
        $length = filesize($filepath); $wlast = $wstart + $length - 1;
        $flength = $file->GetSize();
        
        if ($wlast >= $flength)
            $file->SetSize($wlast+1);
        $flength = $file->GetSize();
        
        if ($wstart < 0 || $wlast < $wstart)
            throw new InvalidFileRangeException();
        
        $file->CountBandwidth($length);        
        
        $fschunksize = $file->GetChunkSize();
        $chunksize = $this->config->GetChunkSize();  
        
        $align = ($fschunksize !== null);
        if ($align) $chunksize = ceil(min($length,$chunksize)/$fschunksize)*$fschunksize;

        $rhandle = fopen($filepath, 'rb');

        for ($wbyte = $wstart; $wbyte <= $wlast; $wbyte += $chunksize)
        {
            fseek($rhandle, $wbyte - $wstart);

            if ($align)
            {              
                $roffset = $wbyte % $chunksize;                
                $rlength = min($wlast+1-$wbyte, $chunksize-$roffset);                
                $padlast = min($chunksize-$roffset, $flength-$wbyte) - $rlength;
                
                if ($roffset || $padlast)
                    $dataf = $file->ReadBytes($wbyte-$roffset, $chunksize);
                             
                $data = fread($rhandle, $rlength);
                
                if ($roffset) $data = substr($dataf, 0, $roffset).$data;                
                if ($padlast) $data = $data.substr($dataf, -$padlast);

                $wbyte -= $roffset;
            }
            else $data = fread($rhandle, $chunksize);

            $file->WriteBytes($wbyte, $data);
        }

        return $file->GetClientObject();
    }
    
    protected function TruncateFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        $size = $input->GetParam('size',SafeParam::TYPE_INT);
        
        if ($size < 0) throw new InvalidFileRangeException();
        
        $file->SetSize($size);
        
        return $file->GetClientObject();
    }
    
    protected function GetFileInfo(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        return $file->GetClientObject();
    }
    
    protected function GetFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        if (($filesystem = $input->TryGetParam('filesystem',SafeParam::TYPE_ID)) !== null)
        {
            $filesystem = FSManager::TryLoadByID($this->Database, $filesystem);
            if ($filesystem === null) throw new UnknownFilesystemException();
            return $filesystem->GetClientObject();
        }
        else return FSManager::LoadDefaultbyAccount($this->database, $account)->GetClientObject();
    }
    
    protected function GetFilesystems(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $filesystems = FSManager::LoadByAccount($this->database, $account);
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
    
    protected function GetItemByPath(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = $input->TryGetParam('rootfolder',SafeParam::TYPE_ID);   
        
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
        
        $path = $input->TryGetParam('path',SafeParam::TYPE_TEXT) ?? '/';
        $path = array_filter(explode('/',$path), function($p){ return $p; });        
        $name = array_pop($path);

        foreach ($path as $subfolder)
        {
            $subfolder = Folder::TryLoadByParentAndName($this->database, $folder, $account, $subfolder);
            if ($subfolder === null) throw new UnknownFolderException(); else $folder = $subfolder;
        }
        
        $item = null; $isfile = $input->TryGetParam('isfile',SafeParam::TYPE_BOOL);
        
        if ($name === null) $item = $folder;
        else
        {                     
            if ($isfile === null || $isfile) $item = File::TryLoadByParentAndName($this->database, $folder, $account, $name);
            if ($item === null && !$isfile)  $item = Folder::TryLoadByParentAndName($this->database, $folder, $account, $name);        
        }
        
        if ($item === null) throw new UnknownItemException();
        
        if ($isfile === false) $retval = $item->GetClientObject(Folder::WITHCONTENT);
        else $retval = $item->GetClientObject();
        
        $retval['isfile'] = is_a($item, File::class); return $retval;
    }
    
    protected function CreateFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownParentException();

        $name = $input->GetParam('name',SafeParam::TYPE_TEXT);
        
        $folder = Folder::TryLoadByParentAndName($this->database, $parent, $account, $name);
        if ($folder !== null) return $folder->GetClientObject();
 
        return Folder::Create($this->database, $parent, $account, $name)->GetClientObject();
    }
    
    protected function DeleteFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = $input->TryGetParam('file',SafeParam::TYPE_ID);
        $files = $input->TryGetParam('files',SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);

        if ($file !== null)
        {
            $fileobj = File::TryLoadByAccountAndID($this->database, $account, $file);
            if ($fileobj === null) throw new UnknownFileException();            
            $fileobj->Delete(); return array();
        }
        else if ($files !== null)
        {
            $retval = array();
            foreach ($files as $file)
            {
                $fileobj = File::TryLoadByAccountAndID($this->database, $account, $file);
                try { $fileobj->Delete(); $retval[$file] = true; } 
                catch (\Throwable $e){ $retval[$file] = false; }
            };
            return $retval;
        }
        else throw new UnknownFileException();
    }
    
    protected function DeleteFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = $input->TryGetParam('folder',SafeParam::TYPE_ID);
        $folders = $input->TryGetParam('folders',SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        if ($folder !== null)
        {
            $folderobj = Folder::TryLoadByAccountAndID($this->database, $account, $folder);
            if ($folderobj === null) throw new UnknownFolderException();
            $folderobj->Delete(); return array();
        }
        else if ($folders !== null)
        {
            $retval = array();
            foreach ($folders as $folder)
            {
                $folderobj = Folder::TryLoadByAccountAndID($this->database, $account, $folder);
                try { $folderobj->Delete(); $retval[$folder] = true; }
                catch (\Throwable $e){ $retval[$folder] = false; }
            };
            return $retval;
        }
        else throw new UnknownFolderException();
    }
    
    protected function RenameFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = File::TryLoadByAccountAndID($this->database, $account, $input->GetParam('file',SafeParam::TYPE_ID));
        if ($file === null) throw new UnknownFileException();
        
        $name = basename($input->GetParam('name',SafeParam::TYPE_TEXT));        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $oldfile = File::TryLoadByParentAndName($this->database, $file->GetParent(), $account, $name);
        if ($oldfile !== null) { if ($overwrite) $oldfile->Delete(); else throw new DuplicateFileException(); }
        
        return $file->SetName($name)->GetClientObject();
    }
    
    protected function RenameFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('folder',SafeParam::TYPE_ID));
        if ($folder === null) throw new UnknownFolderException();
        
        $name = basename($input->GetParam('name',SafeParam::TYPE_TEXT));   
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $oldfolder = File::TryLoadByParentAndName($this->database, $folder->GetParent(), $account, $name);
        if ($oldfolder !== null) { if ($overwrite) $oldfolder->Delete(); else throw new DuplicateFolderException(); }
        
        return $folder->SetName($name)->GetClientObject();
    }
    
    protected function MoveFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $file = $input->TryGetParam('file',SafeParam::TYPE_ID);
        $files = $input->TryGetParam('files',SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownParentException();
        
        if ($file !== null)
        {
            $fileobj = File::TryLoadByAccountAndID($this->database, $account, $file);
            if ($fileobj === null) throw new UnknownFileException();
            
            $oldfile = File::TryLoadByParentAndName($this->database, $parent, $account, $fileobj->GetName());
            if ($oldfile !== null) { if ($overwrite) $oldfile->Delete(); else throw new DuplicateFileException(); }       
            
            return $fileobj->SetParent($parent)->GetClientObject();
        }
        else if ($files !== null)
        {
            $retval = array();
            foreach ($files as $file)
            {
                $fileobj = File::TryLoadByAccountAndID($this->database, $account, $file);
                if ($fileobj === null) { $retval[$file] = false; continue; }
                
                $oldfile = File::TryLoadByParentAndName($this->database, $parent, $account, $fileobj->GetName());
                if ($oldfile !== null) { if ($overwrite) $oldfile->Delete(); else { $retval[$file] = false; continue; } }
                
                try { $retval[$file] = $fileobj->SetParent($parent)->GetClientObject(); }
                catch(\Throwable $e) { $retval[$file] = false; }
            };
            return $retval;
        }
        else throw new UnknownFileException();
    }
    
    protected function MoveFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $folder = $input->TryGetParam('folder',SafeParam::TYPE_ID);
        $folders = $input->TryGetParam('folders',SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownParentException();
        
        if ($folder !== null)
        {
            $folderobj = Folder::TryLoadByAccountAndID($this->database, $account, $folder);
            if ($folderobj === null) throw new UnknownFolderException();
            
            $oldfolder = Folder::TryLoadByParentAndName($this->database, $parent, $account, $folderobj->GetName());
            if ($oldfolder !== null) { if ($overwrite) $oldfolder->Delete(); else throw new DuplicateFolderException(); }
            
            return $folderobj->SetParent($parent)->GetClientObject();
        }
        else if ($folders !== null)
        {
            $retval = array();
            foreach ($folders as $folder)
            {
                $folderobj = Folder::TryLoadByAccountAndID($this->database, $account, $folder);
                if ($folderobj === null) { $retval[$folder] = false; continue; }
                
                $oldfolder = Folder::TryLoadByParentAndName($this->database, $parent, $account, $folderobj->GetName());
                if ($oldfolder !== null) { if ($overwrite) $oldfolder->Delete(); else { $retval[$folder] = false; continue; } }
                
                try { $retval[$folder] = $folderobj->SetParent($parent)->GetClientObject(); }
                catch(\Throwable $e) { $retval[$folder] = false; }
            };
            return $retval;
        }
        else throw new UnknownFolderException();
    }
}

