<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/Config.php");
require_once(ROOT."/apps/files/ItemAccess.php");
require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/File.php");
require_once(ROOT."/apps/files/Folder.php");
require_once(ROOT."/apps/files/Comment.php");
require_once(ROOT."/apps/files/Tag.php");
require_once(ROOT."/apps/files/Like.php");
require_once(ROOT."/apps/files/Share.php");

require_once(ROOT."/apps/files/storage/Storage.php");
require_once(ROOT."/apps/files/storage/Local.php");
require_once(ROOT."/apps/files/storage/FTP.php");
require_once(ROOT."/apps/files/storage/SFTP.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\{AppBase, Main};
require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\AuthEntity;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

use Andromeda\Core\UnknownActionException;
use Andromeda\Core\UnknownConfigException;

use Andromeda\Core\Database\ObjectNotFoundException;
use Andromeda\Apps\Files\Storage\{StorageException, ReadOnlyException};

class UnknownItemException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_ITEM"; }
class UnknownFileException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_FILE"; }
class UnknownFolderException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_FOLDER"; }
class UnknownParentException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_PARENT"; }
class UnknownDestinationException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_DESTINATION"; }
class UnknownFilesystemException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FILESYSTEM"; }

class InvalidDLRangeException extends Exceptions\ClientException { public $code = 416; }

class InvalidFileWriteException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_PARAMS"; }
class InvalidFileRangeException extends Exceptions\ClientErrorException     { public $message = "INVALID_FILE_WRITE_RANGE"; }

class ItemAccessDeniedException extends Exceptions\ClientDeniedException { public $message = "ITEM_ACCESS_DENIED"; }

class UserStorageDisabledException extends Exceptions\ClientDeniedException   { public $message = "USER_STORAGE_NOT_ALLOWED"; }
class RandomWriteDisabledException extends Exceptions\ClientDeniedException   { public $message = "RANDOM_WRITE_NOT_ALLOWED"; }

class EmailShareDisabledException extends Exceptions\ClientDeniedException    { public $message = "EMAIL_SHARES_DISABLED"; }
class ShareEveryoneDisabledException extends Exceptions\ClientDeniedException { public $message = "SHARE_EVERYONE_DISABLED"; }

class FilesApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public static function getUsage() : array 
    { 
        return array(
            '- FOR shared files/folders: [--sid id --skey alphanum] [--spassword raw]',
            'getconfig',
            'setconfig',
            'upload --file file [name] --parent id [--overwrite bool]',
            'download --fileid id [--fstart int] [--flast int]',
            'ftruncate --fileid id --size int',
            'writefile --file file --fileid id [--offset int]',
            'fileinfo --fileid id',
            'getfolder [--folder id | --filesystem id] [--files bool] [--folders bool] [--recursive bool] [--limit int] [--offset int] [--details bool]',
            'getitembypath [--rootfolder id | --filesystem id] [--path text] [--isfile bool]',
            'createfolder --parent id --name text',
            'deletefile [--fileid id | --files id_array]',
            'deletefolder [--folder id | --folders id_array]',
            'renamefile --fileid id --name text [--overwrite bool]',
            'renamefolder --folder id --name text [--overwrite bool]',
            'movefile --parent id [--fileid id | --files id_array] [--overwrite bool]',
            'movefolder --parent id [--folder id | --folders id_array] [--overwrite bool]',
            'likefile --fileid id --value -1|0|1',
            'likefolder --folder id --value -1|0|1',
            'tagfile [--fileid id | --files id_array] --tag alphanum',
            'tagfolder [--folder id | --folders id_array] --tag alphanum',
            'deletetag --tag id',
            'commentfile --fileid id --comment text [--private bool]',
            'commentfolder --folder id --comment text [--private bool]',
            'editcomment --commentid id --comment text',
            'deletecomment --commentid id',
            'sharefile [--fileid id | --files id_array] (--link bool [--email email] | --account id | --group id | --everyone bool) [--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--spassword raw]',
            'sharefolder [--folder id | --folders id_array] (--link bool [--email email] | --account id | --group id | --everyone bool) [--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool] [--spassword raw]',
            'editshare --share id [--read bool] [--upload bool] [--modify bool] [--social bool] [--reshare bool]',
            'deleteshare --share id',
            'shareinfo --sid id --skey alphanum [--spassword raw]',
            'listshares [--mine bool]',
            'getfilesystem [--filesystem id]',
            'getfilesystems',
            'createfilesystem --name name --sttype local|ftp|sftp [--global bool] [--fstype 0|1|2] [--readonly bool]',
            'deletefilesystem --filesystem id --auth_password raw',
            'editfilesystem --filesystem id --name name [--readonly bool]'
        ); 
    }
    
    private Config $config;
    private ObjectDatabase $database;
    
    private static $providesCrypto;
    private function providesCrypto(callable $func){ static::$providesCrypto = $func; }
    public static function needsCrypto(){ $func = static::$providesCrypto; $func(); }
     
    public function __construct(Main $api)
    {
        parent::__construct($api);
        $this->database = $api->GetDatabase();
        
        try { $this->config = Config::Load($this->database); }
        catch (ObjectNotFoundException $e) { throw new UnknownConfigException(); }
        
        Account::RegisterDeleteHandler(function(ObjectDatabase $database, Account $account)
        { 
            FSManager::DeleteByAccount($database, $account);             
            Folder::DeleteRootsByAccount($database, $account);
        });        
    }
        
    public function Run(Input $input)
    {        
        $this->authenticator = Authenticator::TryAuthenticate($this->database, $input, $this->API->GetInterface());
        
        $this->providesCrypto(function(){ $this->authenticator->RequireCrypto(); });

        switch($input->GetAction())
        {
            case 'getconfig': return $this->GetConfig($input); break;
            case 'setconfig': return $this->SetConfig($input); break;
            
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
            
            case 'likefile':      return $this->LikeFile($input); break;
            case 'likefolder':    return $this->LikeFolder($input); break;
            case 'tagfile':       return $this->TagFile($input); break;
            case 'tagfolder':     return $this->TagFolder($input); break;
            case 'deletetag':     return $this->DeleteTag($input); break;
            case 'commentfile':   return $this->CommentFile($input); break;
            case 'commentfolder': return $this->CommentFolder($input); break;
            case 'editcomment':   return $this->EditComment($input); break;
            case 'deletecomment': return $this->DeleteComment($input); break;
            
            case 'sharefile':    return $this->ShareFile($input); break;
            case 'sharefolder':  return $this->ShareFolder($input); break;
            case 'editshare':    return $this->EditShare($input); break;
            case 'deleteshare':  return $this->DeleteShare($input); break;
            case 'shareinfo':    return $this->ShareInfo($input); break;
            case 'listshares':   return $this->ListShares($input); break;
            
            case 'getfilesystem':  return $this->GetFilesystem($input); break;
            case 'getfilesystems': return $this->GetFilesystems($input); break;
            case 'createfilesystem': return $this->CreateFilesystem($input); break;
            case 'deletefilesystem': return $this->DeleteFilesystem($input); break;
            case 'editfilesystem':   return $this->EditFilesystem($input); break;
            
            default: throw new UnknownActionException();
        }
    }
    
    private function AuthenticateFileAccess(Input $input, ?string $id = null) : ItemAccess {
        $id ??= $input->TryGetParam('fileid', SafeParam::TYPE_ID);
        return ItemAccess::Authenticate($this->database, $input, $this->authenticator, File::class, $id); }
        
    private function TryAuthenticateFileAccess(Input $input, ?string $id = null) : ?ItemAccess {
        $id ??= $input->TryGetParam('fileid', SafeParam::TYPE_ID);
        return ItemAccess::TryAuthenticate($this->database, $input, $this->authenticator, File::class, $id); }
            
    private function AuthenticateFolderAccess(Input $input, ?string $id = null) : ItemAccess { 
        $id ??= $input->TryGetParam('folder', SafeParam::TYPE_ID);
        return ItemAccess::Authenticate($this->database, $input, $this->authenticator, Folder::class, $id); }
        
    private function TryAuthenticateFolderAccess(Input $input, ?string $id = null) : ?ItemAccess {
        $id ??= $input->TryGetParam('folder', SafeParam::TYPE_ID);
        return ItemAccess::TryAuthenticate($this->database, $input, $this->authenticator, Folder::class, $id); }
        
    private function AuthenticateItemAccess(Input $input, string $class, string $id) : ItemAccess {
        return ItemAccess::Authenticate($this->database, $input, $this->authenticator, $class, $id); }
        
    private function TryAuthenticateItemAccess(Input $input, string $class, string $id) : ?ItemAccess {
        return ItemAccess::TryAuthenticate($this->database, $input, $this->authenticator, $class, $id); }

    protected function GetConfig(Input $input) : array
    {
        $account = $this->authenticator->GetAccount();
        $admin = $account !== null && $account->isAdmin();

        return $this->config->GetClientObject($admin);
    }
    
    protected function SetConfig(Input $input) : array
    {
        $account = $this->authenticator->GetAccount();
        $admin = $account !== null && $account->isAdmin();
        
        // not sure if there will be user-level config - very likely yes
        
        return $this->config->GetClientObject($admin);
    }
    
    protected function UploadFiles(Input $input) : array
    {
        if (!$this->config->GetAllowPublicUpload() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();
        
        $access = $this->AuthenticateFolderAccess($input, $input->TryGetParam('parent',SafeParam::TYPE_ID));
        $parent = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $return = array(); $files = $input->GetFiles();
        if (!count($files)) throw new InvalidFileWriteException();
        foreach ($files as $name => $path)
        {            
            $parent->CountBandwidth(filesize($path));
            
            $file = File::Import($this->database, $parent, $account, $name, $path, $overwrite);
            array_push($return, $file->GetClientObject());
        }        
        return $return;
    }
    
    protected function DownloadFile(Input $input) : array
    {
        $access = $this->AuthenticateFileAccess($input); 
        $file = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
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
        
        if (!($input->TryGetParam('debugdl',SafeParam::TYPE_BOOL) ?? false))
            $this->API->GetInterface()->SetOutmode(IOInterface::OUTPUT_NONE);
        
        if ($fstart != 0 || $flast != $fsize-1)
        {
            http_response_code(206);
            header("Content-Range: bytes $fstart-$flast/$fsize");     
        }
        else if (isset($share) && $share !== null) $file->CountDownload();
        
        header("Content-Length: ".($flast-$fstart+1));       

        header("Accept-Ranges: bytes");
        header("Cache-Control: max-age=0");
        header("Content-type: application/octet-stream");
        header('Content-Disposition: attachment; filename="'.$file->GetName().'"');
        header('Content-Transfer-Encoding: binary');       
        
        set_time_limit(0);

        try { while (@ob_end_flush()); } catch (\Throwable $e) { }        
        
        $fschunksize = $file->GetChunkSize();
        $chunksize = $this->config->GetRWChunkSize();     
        
        $align = ($fschunksize !== null);
        /* transfer chunk size must be an integer multiple of the FS chunk size */
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
        if (!$this->config->GetAllowPublicModify() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        
        if (!$this->config->GetAllowRandomWrite() && (!$this->authenticator || !$this->authenticator->isAdmin()))
            throw new RandomWriteDisabledException();
            
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();        

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
        $chunksize = $this->config->GetRWChunkSize();  
        
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
        if (!$this->config->GetAllowPublicModify() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        
        if (!$this->config->GetAllowRandomWrite() && (!$this->authenticator || !$this->authenticator->isAdmin()))
            throw new RandomWriteDisabledException();
            
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();      
    
        $size = $input->GetParam('size',SafeParam::TYPE_INT);
        
        if ($size < 0) throw new InvalidFileRangeException();
        
        $file->SetSize($size);
        
        return $file->GetClientObject();
    }
    
    protected function GetFileInfo(Input $input) : array
    {
        $access = $this->AuthenticateFileAccess($input);
        $file = $access->GetItem(); $share = $access->GetShare();

        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        $details = $share !== null ? Item::DETAILS_PUBLIC : Item::DETAILS_OWNER;
        
        return $file->GetClientObject($details);
    }

    protected function GetFolder(Input $input) : array
    {
        if ($input->TryGetParam('folder',SafeParam::TYPE_ID))
        {
            $access = $this->AuthenticateFolderAccess($input);
            $folder = $access->GetItem(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else
        {
            if ($this->authenticator === null) throw new AuthenticationFailedException();
            $account = $this->authenticator->GetAccount();
            
            $filesys = $input->TryGetParam('filesystem',SafeParam::TYPE_ID);
            if ($filesys !== null)
            {
                $filesys = FSManager::TryLoadByID($this->database, $filesys);  
                if ($filesys === null) throw new UnknownFilesystemException();
            }
                
            $folder = Folder::LoadRootByAccountAndFS($this->database, $account, $filesys);
        }

        if ($folder === null) throw new UnknownFolderException();
        
        $files = $input->TryGetParam('files',SafeParam::TYPE_BOOL) ?? true;
        $folders = $input->TryGetParam('folders',SafeParam::TYPE_BOOL) ?? true;
        $recursive = $input->TryGetParam('recursive',SafeParam::TYPE_BOOL) ?? false;
        
        $limit = $input->TryGetParam('limit',SafeParam::TYPE_INT);
        $offset = $input->TryGetParam('offset',SafeParam::TYPE_INT);
        $details = $input->TryGetParam('details',SafeParam::TYPE_BOOL) ?? false;
        
        $public = isset($share) && $share !== null;

        if ($public && ($files || $folders)) $folder->CountVisit();
        
        if ($details) $details = $public ? Item::DETAILS_PUBLIC : Item::DETAILS_OWNER;
        
        $return = $folder->GetClientObject($files,$folders,$recursive,$limit,$offset,$details);
        if ($return === null) throw new UnknownFolderException(); return $return;
    }
    
    protected function GetItemByPath(Input $input) : array
    {
        $rid = $input->TryGetParam('rootfolder',SafeParam::TYPE_ID);
        if (($raccess = $this->TryAuthenticateFolderAccess($input, $rid)) !== null)
        {
            $folder = $raccess->GetItem(); $share = $raccess->GetShare();
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else
        {
            if ($this->authenticator === null) throw new AuthenticationFailedException();
            $account = $this->authenticator->GetAccount();
            
            $filesys = $input->TryGetParam('filesystem',SafeParam::TYPE_ID);
            if ($filesys !== null)
            {
                $filesys = FSManager::TryLoadByID($this->database, $filesys);
                if ($filesys === null) throw new UnknownFilesystemException();
            }
            
            $folder = Folder::LoadRootByAccountAndFS($this->database, $account, $filesys);
        }
        
        if ($folder === null) throw new UnknownFolderException();
        
        $path = $input->TryGetParam('path',SafeParam::TYPE_TEXT) ?? '/';
        $path = array_filter(explode('/',$path)); $name = array_pop($path);

        foreach ($path as $subfolder)
        {
            $subfolder = Folder::TryLoadByParentAndName($this->database, $folder, $subfolder);
            if ($subfolder === null) throw new UnknownFolderException(); else $folder = $subfolder;
        }
        
        $item = null; $isfile = $input->TryGetParam('isfile',SafeParam::TYPE_BOOL);
        
        if ($name === null) $item = $isfile !== true ? $folder : null;
        else
        {
            if ($isfile === null || $isfile) $item = File::TryLoadByParentAndName($this->database, $folder, $name);
            if ($item === null && !$isfile)  $item = Folder::TryLoadByParentAndName($this->database, $folder, $name);
        }
        
        if ($item === null) throw new UnknownItemException();

        if (is_a($item, File::class)) $retval = $item->GetClientObject();
        else if (is_a($item, Folder::class))
        {
            if (isset($share) && $share !== null) $item->CountVisit();
            $retval = $item->GetClientObject(true,true);
        }
        
        $retval['isfile'] = is_a($item, File::class); return $retval;
    }
    
    protected function CreateFolder(Input $input) : array
    {
        if (!$this->config->GetAllowPublicUpload() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        $account = ($this->authenticator === null) ? null : $this->authenticator->GetAccount();
        
        $access = $this->AuthenticateFolderAccess($input, $input->TryGetParam('parent',SafeParam::TYPE_ID));
        $parent = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();

        $name = $input->GetParam('name',SafeParam::TYPE_TEXT);

        return Folder::Create($this->database, $parent, $account, $name)->GetClientObject();
    }
    
    protected function DeleteFile(Input $input) : array
    {
        return $this->DeleteItem(File::class, 'fileid', 'files', $input);
    }
    
    protected function DeleteFolder(Input $input) : array
    {
        return $this->DeleteItem(Folder::class, 'folder', 'folders', $input);
    }
    
    private function DeleteItem(string $class, string $key, string $keys, Input $input) : array
    {
        if (!$this->config->GetAllowPublicModify() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        
        $item = $input->TryGetParam($key,SafeParam::TYPE_ID);
        $items = $input->TryGetParam($keys,SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        if ($item !== null)
        {
            $access = static::AuthenticateItemAccess($input, $class, $item);
            $itemobj = $access->GetItem(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();

            $itemobj->Delete(); return array();
        }
        else if ($items !== null)
        {
            $retval = array();
            foreach ($items as $item)
            {
                $retval[$item] = false;
                
                $access = static::TryAuthenticateItemAccess($input, $class, $item); 
                if ($access === null) continue;                
                
                $itemobj = $access->GetItem(); $share = $access->GetShare();               
                if ($share !== null && !$share->CanModify()) continue;  
                
                try { $itemobj->Delete(); $retval[$item] = true; }
                catch (StorageException | ReadOnlyException $e){ }
            };
            return $retval;
        }
        else ItemAccess::ItemException($class);
    }
    
    protected function RenameFile(Input $input) : array
    {
        return $this->RenameItem(File::class, 'fileid', $input);
    }
    
    protected function RenameFolder(Input $input) : array
    {
        return $this->RenameItem(Folder::class, 'folder', $input);
    }
    
    private function RenameItem(string $class, string $key, Input $input)
    {
        if (!$this->config->GetAllowPublicModify() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        
        $id = $input->GetParam($key, SafeParam::TYPE_ID);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        $name = basename($input->GetParam('name',SafeParam::TYPE_TEXT));
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        return $item->SetName($name, $overwrite)->GetClientObject();
    }
    
    protected function MoveFile(Input $input) : array
    {
        return $this->MoveItem(File::class, 'fileid', 'files', $input);
    }
    
    protected function MoveFolder(Input $input) : array
    {
        return $this->MoveItem(Folder::class, 'folder', 'folders', $input);
    }
    
    private function MoveItem(string $class, string $key, string $keys, Input $input) : array
    {
        if (!$this->config->GetAllowPublicModify() && $this->authenticator === null)
            throw new AuthenticationFailedException();
        
        $item = $input->TryGetParam($key,SafeParam::TYPE_ID);
        $items = $input->TryGetParam($keys,SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        $access = $this->AuthenticateFolderAccess($input, $input->TryGetParam('parent',SafeParam::TYPE_ID));
        $parent = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();
        
        $overwrite = $input->TryGetParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        if ($item !== null)
        {
            $access = static::AuthenticateItemAccess($input, $class, $item);
            $itemobj = $access->GetItem(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
            
            return $itemobj->SetParent($parent, $overwrite)->GetClientObject();
        }
        else if ($items !== null)
        {
            $retval = array();
            foreach ($items as $item)
            {
                $retval[$item] = false;
                
                $access = static::TryAuthenticateItemAccess($input, $class, $item);
                if ($access === null) continue;
                
                $itemobj = $access->GetItem(); $share = $access->GetShare();
                if ($share !== null && !$share->CanModify()) continue;  
                
                try { $retval[$item] = $itemobj->SetParent($parent, $overwrite)->GetClientObject(); }
                catch(StorageException | ReadOnlyException | DuplicateItemException $e) { }
            };
            return $retval;
        }
        else ItemAccess::ItemException($class);
    }
        
    protected function LikeFile(Input $input) : array
    {
        return $this->LikeItem(File::class, 'fileid', $input);
    }
    
    protected function LikeFolder(Input $input) : array
    {
        return $this->LikeItem(Folder::class, 'folder', $input);
    }
    
    private function LikeItem(string $class, string $key, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_ID);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $value = $input->GetParam('value',SafeParam::TYPE_INT);
        if ($value > 0) $value = 1; else if ($value < 0) $value = -1;
        
        return Like::CreateOrUpdate($this->database, $account, $item, $value)->GetClientObject();
    }
    
    protected function TagFile(Input $input) : array
    {
        return $this->TagItem(File::class, 'fileid', 'files', $input);
    }
    
    protected function TagFolder(Input $input) : array
    {
        return $this->TagItem(Folder::class, 'folder', 'folders', $input);
    }
    
    private function TagItem(string $class, string $key, string $keys, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $tag = $input->GetParam('tag', SafeParam::TYPE_ALPHANUM);
        
        $item = $input->TryGetParam($key,SafeParam::TYPE_ID);
        $items = $input->TryGetParam($keys,SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        if ($item !== null)
        {
            $access = static::AuthenticateItemAccess($input, $class, $item);
            $itemobj = $access->GetItem(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
            
            return Tag::Create($this->database, $account, $itemobj, $tag)->GetClientObject();
        }
        else if ($items !== null)
        {
            $retval = array();
            foreach ($items as $item)
            {
                $retval[$item] = false;
                
                $access = static::TryAuthenticateItemAccess($input, $class, $item);
                if ($access === null) continue;
                
                $itemobj = $access->GetItem(); $share = $access->GetShare();
                if ($share !== null && !$share->CanModify()) continue;
                
                $retval[$item] = Tag::Create($this->database, $account, $itemobj, $tag)->GetClientObject();                
            };
            return $retval;
        }
        else ItemAccess::ItemException($class);
    }
    
    protected function DeleteTag(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $id = $input->GetParam('tag', SafeParam::TYPE_ID);
        $tag = Tag::TryLoadByID($this->database, $id);
        if ($tag === null) throw new UnknownItemException();

        $item = $tag->GetItem(); $access = ItemAccess::Authenticate(
            $this->database, $input, $this->authenticator, get_class($item), $item->ID());
        
        $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        $tag->Delete(); return array();
    }
    
    protected function CommentFile(Input $input) : array
    {
        return $this->CommentItem(File::class, 'fileid', $input);
    }
    
    protected function CommentFolder(Input $input) : array
    {
        return $this->CommentItem(Folder::class, 'folder', $input);
    }
    
    private function CommentItem(string $class, string $key, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_ID);
        $access = static::AuthenticateItemAccess($input, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $comment = $input->GetParam('comment', SafeParam::TYPE_TEXT);       
        $cobj = Comment::Create($this->database, $account, $item, $comment);
        
        $private = $input->TryGetParam('private', SafeParam::TYPE_BOOL);
        if ($private !== null && $share === null) 
            $cobj->SetPrivate($private);
        
        return $cobj->GetClientObject();
    }
    
    protected function EditComment(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $comment = $input->GetParam('comment', SafeParam::TYPE_TEXT);
        
        $id = $input->TryGetParam('commentid',SafeParam::TYPE_ID);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        return $cobj->SetComment($comment)->GetClientObject();
    }
    
    protected function DeleteComment(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->TryGetParam('commentid',SafeParam::TYPE_ID);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        $cobj->Delete(); return array();
    }

    protected function ShareFile(Input $input) : array
    {
        return $this->ShareItem(File::class, 'fileid', 'files', $input);
    }
    
    protected function ShareFolder(Input $input) : array
    {
        return $this->ShareItem(Folder::class, 'folder', 'folders', $input);
    }
    
    private function ShareItem(string $class, string $key, string $keys, Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $item = $input->TryGetParam($key,SafeParam::TYPE_ID);
        $items = $input->TryGetParam($keys,SafeParam::TYPE_ARRAY | SafeParam::TYPE_ID);
        
        $destacct = $input->TryGetParam('account',SafeParam::TYPE_ID);
        $destgroup = $input->TryGetParam('group',SafeParam::TYPE_ID);
        $everyone = $input->TryGetParam('everyone',SafeParam::TYPE_BOOL) ?? false;
        $islink = $input->TryGetParam('link',SafeParam::TYPE_BOOL) ?? false;

        $dest = null; if (!$islink)
        {
            if ($destacct !== null)       $dest = Account::TryLoadByID($this->database, $destacct);
            else if ($destgroup !== null) $dest = Group::TryLoadByID($this->database, $destgroup);
            if ($dest === null && !$everyone) throw new UnknownDestinationException();
        }
        
        $isadmin = $this->authenticator->isAdmin();
        if ($dest === null && !$this->config->GetAllowShareEveryone() && !$isadmin)
            throw new ShareEveryoneDisabledException();
        
        if ($item !== null)
        {
            $share = static::InnerShareItem($class, $input, $item, $account, $dest, $islink);
            if ($share === null) throw new UnknownItemException();
            $shares = array($share); $retval = $share->GetClientObject(false, $islink); 
        }
        else if ($items !== null)
        {
            $shares = array();
            foreach ($items as $item)
            {
                $shares[$item] = static::InnerShareItem($class, $input, $item, $account, $dest, $islink) ?? false;
            };
            $retval = array_map(function($share)use($islink){ return $share->GetClientObject(false, $islink); }, $shares);
        }
        else throw new UnknownItemException();
        
        if ($islink && ($email = $input->TryGetParam('email',SafeParam::TYPE_EMAIL)) !== null)
        {
            if (!$this->config->GetAllowEmailShare())
                throw new EmailShareDisabledException();
            
            $account = $this->authenticator->GetAccount();
            $subject = $account->GetDisplayName()." shared files with you"; 
            
            $body = implode("<br />",array_map(function($share){
                return "<a href=''>".$share->GetItem()->GetName()."</a>"; // TODO HTML
            }, $shares)); // TODO how to get URL? from client?
            
            $this->API->GetConfig()->GetMailer()->SendMail($subject, $body, 
                array(new EmailRecipient($email)), $account->GetMailFrom());
        }
        
        return $retval;
    }    
    
    private function InnerShareItem(string $class, Input $input, string $item, Account $account, ?AuthEntity $dest, bool $islink) : ?Share
    {
        $access = static::TryAuthenticateItemAccess($input, $class, $item);
        if ($access === null) return null; $oldshare = $access->GetShare();
        if ($oldshare !== null && !$oldshare->CanReshare()) return null;
        
        if ($islink) $newshare = Share::CreateLink($this->database, $account, $access->GetItem());
        else $newshare = Share::Create($this->database, $account, $item, $dest);
        
        return $newshare->SetShareOptions($input, $oldshare);
    }
  
    protected function EditShare(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->TryGetParam('share',SafeParam::TYPE_ID);
        
        $share = Share::TryLoadByOwnerAndID($this->database, $account, $id);
        if ($share === null) throw new UnknownItemException();

        $origshare = null; if ($share->GetItem()->GetOwner() !== $account)
        {
            $origshare = Share::TryAuthenticate($this->database, $share->GetItem(), $account);
            if ($origshare === null) throw new UnknownItemException();
        }
        
        return $share->SetShareOptions($input, $origshare)->GetClientObject();
    }
    
    protected function DeleteShare(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $id = $input->TryGetParam('share',SafeParam::TYPE_ID);

        $share = Share::TryLoadByOwnerAndID($this->database, $account, $id, true);
        if ($share === null) throw new UnknownItemException();
        
        $share->Delete(); return array();
    }
    
    protected function ShareInfo(Input $input) : array
    {
        $access = ItemAccess::Authenticate($this->database, $input, $this->authenticator);
        return $access->GetShare()->GetClientObject(true);
    }
    
    protected function ListShares(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $mine = $input->TryGetParam('mine',SafeParam::TYPE_BOOL) ?? false;
        
        if ($mine) $shares = Share::LoadByAccountOwner($this->database, $account);
        else $shares = Share::LoadByAccountDest($this->database, $account);
        
        return array_map(function($share){ return $share->GetClientObject(true); }, $shares);
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
        
        // TODO admin function to get everything
        
        $filesystems = FSManager::LoadByAccount($this->database, $account);
        return array_map(function($filesystem){ return $filesystem->GetClientObject(); }, $filesystems);
    }
    
    protected function CreateFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        $isadmin = $this->authenticator->isAdmin();
        
        if (!$this->config->GetAllowUserStorage() && !$isadmin)
            throw new UserStorageDisabledException();
        
        $global = ($input->TryGetParam('global', SafeParam::TYPE_BOOL) ?? false) && $isadmin;

        $filesystem = FSManager::Create($this->database, $input, $global ? null : $account);
        return $filesystem->GetClientObject(true);
    }
    
    protected function EditFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem', SafeParam::TYPE_ID);
        
        if ($this->authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->API->GetDatabase(), $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->API->GetDatabase(), $account, $fsid);
        
        if ($filesystem === null) throw new UnknownFilesystemException();

        return $filesystem->Edit($input)->GetClientObject(true);
    }

    protected function DeleteFilesystem(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        
        $this->authenticator->RequirePassword();
        $account = $this->authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem', SafeParam::TYPE_ID);
        
        if ($this->authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->API->GetDatabase(), $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->API->GetDatabase(), $account, $fsid);
        
        $force = $input->TryGetParam('force', SafeParam::TYPE_BOOL);

        if ($filesystem === null) throw new UnknownFilesystemException();
        if ($force) $filesystem->ForceDelete(); else $filesystem->Delete(); return array();
    }
 
}

