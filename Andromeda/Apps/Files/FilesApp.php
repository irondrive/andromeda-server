<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Apps/Files/AccessLog.php");
require_once(ROOT."/Apps/Files/Config.php");
require_once(ROOT."/Apps/Files/ItemAccess.php");
require_once(ROOT."/Apps/Files/Item.php");
require_once(ROOT."/Apps/Files/File.php");
require_once(ROOT."/Apps/Files/Folder.php");
require_once(ROOT."/Apps/Files/Comment.php");
require_once(ROOT."/Apps/Files/Tag.php");
require_once(ROOT."/Apps/Files/Like.php");
require_once(ROOT."/Apps/Files/Share.php");
require_once(ROOT."/Apps/Files/FileUtils.php");

require_once(ROOT."/Apps/Files/Limits/Filesystem.php");
require_once(ROOT."/Apps/Files/Limits/Account.php");

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/Apps/Files/Storage/Exceptions.php"); use Andromeda\Apps\Files\Storage\{FileReadFailedException, FileWriteFailedException};
require_once(ROOT."/Apps/Files/Storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;

require_once(ROOT."/Core/BaseApp.php"); use Andromeda\Core\{BaseApp, InstalledApp};
require_once(ROOT."/Core/Emailer.php"); use Andromeda\Core\EmailRecipient;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/IOFormat/Output.php"); use Andromeda\Core\IOFormat\Output;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/InputFile.php"); use Andromeda\Core\IOFormat\InputPath;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/IOFormat/IOInterface.php"); use Andromeda\Core\IOFormat\{IOInterface, OutputHandler};
require_once(ROOT."/Core/IOFormat/Interfaces/AJAX.php"); use Andromeda\Core\IOFormat\Interfaces\AJAX;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/Apps/Accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

use Andromeda\Core\UnknownActionException;

use Andromeda\Apps\Accounts\UnknownAccountException;
use Andromeda\Apps\Accounts\UnknownGroupException;

/** Exception indicating that the requested item does not exist */
class UnknownItemException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_ITEM"; }

/** Exception indicating that the requested file does not exist */
class UnknownFileException extends Exceptions\ClientNotFoundException       { public $message = "UNKNOWN_FILE"; }

/** Exception indicating that the requested folder does not exist */
class UnknownFolderException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_FOLDER"; }

/** Exception indicating that the requested object does not exist */
class UnknownObjectException extends Exceptions\ClientNotFoundException     { public $message = "UNKNOWN_OBJECT"; }

/** Exception indicating that the requested parent does not exist */
class UnknownParentException  extends Exceptions\ClientNotFoundException    { public $message = "UNKNOWN_PARENT"; }

/** Exception indicating that the requested destination folder does not exist */
class UnknownDestinationException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_DESTINATION"; }

/** Exception indicating that the requested filesystem does not exist */
class UnknownFilesystemException extends Exceptions\ClientNotFoundException  { public $message = "UNKNOWN_FILESYSTEM"; }

/** Exception indicating that the requested download byte range is invalid */
class InvalidDLRangeException extends Exceptions\ClientException { public $code = 416; public $message = "INVALID_BYTE_RANGE"; }

/** Exception indicating that access to the requested item is denied */
class ItemAccessDeniedException extends Exceptions\ClientDeniedException    { public $message = "ITEM_ACCESS_DENIED"; }

/** Exception indicating that user-added filesystems are not allowed */
class UserStorageDisabledException extends Exceptions\ClientDeniedException   { public $message = "USER_STORAGE_NOT_ALLOWED"; }

/** Exception indicating that random write access is not allowed */
class RandomWriteDisabledException extends Exceptions\ClientDeniedException   { public $message = "RANDOM_WRITE_NOT_ALLOWED"; }

/** Exception indicating that item sharing is not allowed */
class ItemSharingDisabledException extends Exceptions\ClientDeniedException   { public $message = "SHARING_DISABLED"; }

/** Exception indicating that emailing share links is not allowed */
class EmailShareDisabledException extends Exceptions\ClientDeniedException    { public $message = "EMAIL_SHARES_DISABLED"; }

/** Exception indicating that the absolute URL of a share cannot be determined */
class ShareURLGenerateException extends Exceptions\ClientErrorException       { public $message = "CANNOT_OBTAIN_SHARE_URI"; }

/** Exception indicating invalid share target params were given */
class InvalidShareTargetException extends Exceptions\ClientErrorException     { public $message = "INVALID_SHARE_TARGET_PARAMS"; }

/** Exception indicating that sharing to the given target is not allowed */
class ShareTargetDisabledException extends Exceptions\ClientDeniedException { public $message = "SHARE_TARGET_DISABLED"; }

/**
 * App that provides user-facing filesystem services.
 * 
 * Provides a general filesystem API for managing files and folders,
 * as well as admin-level functions like managing filesystems and config.
 * 
 * Supports features like random-level byte access, multiple (user or admin-added)
 * filesystems with various backend storage drivers, social features including
 * likes and comments, sharing of content via links or to users or groups,
 * configurable rules per-account or per-filesystem, and granular statistics
 * gathering and limiting for accounts/groups/filesystems.
 */
class FilesApp extends InstalledApp
{
    public static function getName() : string { return 'files'; }
    
    protected static function getLogClass() : string { return AccessLog::class; }
    
    protected static function getConfigClass() : string { return Config::class; }
    
    protected function GetConfig() : Config { return $this->config; }
    
    public static function getUsage() : array 
    { 
        return array_merge(parent::getUsage(),array(
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            '- AUTH for shared items: --sid id [--skey alphanum] [--spassword raw]',
            'upload (--file% path [name] | --file- --name fsname) --parent id [--overwrite bool]',
            'download --file id [--fstart uint] [--flast int] [--debugdl bool]',
            'ftruncate --file id --size uint',
            'writefile (--data% path | --data-) --file id [--offset uint]',
            'getfilelikes --file id [--limit uint] [--offset uint]',
            'getfolderlikes --folder id [--limit uint] [--offset uint]',
            'getfilecomments --file id [--limit uint] [--offset uint]',
            'getfoldercomments --folder id [--limit uint] [--offset uint]',
            'fileinfo --file id [--details bool]',
            'getfolder [--folder id | --filesystem id] [--files bool] [--folders bool] [--recursive bool] [--limit uint] [--offset uint] [--details bool]',
            'getitembypath --path fspath [--folder id | --filesystem id] [--isfile bool]',
            'editfilemeta --file id [--description ?text]',
            'editfoldermeta --folder id [--description ?text]',
            'ownfile --file id',
            'ownfolder --folder id',
            'createfolder --parent id --name fsname',
            'deletefile --file id',
            'deletefolder --folder id',
            'renamefile --file id --name fsname [--overwrite bool] [--copy bool]',
            'renamefolder --folder id --name fsname [--overwrite bool] [--copy bool]',
            'movefile --parent id --file id  [--overwrite bool] [--copy bool]',
            'movefolder --parent id --folder id [--overwrite bool] [--copy bool]',
            'likefile --file id --value ?bool',
            'likefolder --folder id --value ?bool',
            'tagfile --file id --tag alphanum',
            'tagfolder --folder id --tag alphanum',
            'deletetag --tag id',
            'commentfile --file id --comment text',
            'commentfolder --folder id --comment text',
            'editcomment --commentid id [--comment text]',
            'deletecomment --commentid id',
            'sharefile --file id (--link bool [--email email] | --account id | --group id | --everyone bool) '.Share::GetSetOptionsUsage(),
            'sharefolder --folder id (--link bool [--email email] | --account id | --group id | --everyone bool) '.Share::GetSetOptionsUsage(),
            'editshare --share id '.Share::GetSetOptionsUsage(),
            'deleteshare --share id',
            'shareinfo --sid id [--skey alphanum] [--spassword raw]',
            'listshares [--mine bool]',
            'listadopted',
            'getfilesystem [--filesystem id] [--activate bool]',
            'getfilesystems [--everyone bool [--limit uint] [--offset uint]]',
            'createfilesystem '.FSManager::GetCreateUsage(),
            ...array_map(function($u){ return "(createfilesystem) $u"; }, FSManager::GetCreateUsages()),
            'deletefilesystem --filesystem id --auth_password raw [--unlink bool]',
            'editfilesystem --filesystem id '.FSManager::GetEditUsage(),
            ...array_map(function($u){ return "(editfilesystem) $u"; }, FSManager::GetEditUsages()),
            'getlimits [--account ?id | --group ?id | --filesystem ?id] [--limit uint] [--offset uint]',
            'gettimedlimits [--account ?id | --group ?id | --filesystem ?id] [--limit uint] [--offset uint]',
            'gettimedstatsfor [--account id | --group id | --filesystem id] --timeperiod uint [--limit uint] [--offset uint]',
            'gettimedstatsat (--account ?id | --group ?id | --filesystem ?id) --timeperiod uint --matchtime uint [--limit uint] [--offset uint]',
            'configlimits (--account id | --group id | --filesystem id) '.Limits\Total::BaseConfigUsage(),
            "(configlimits) --account id ".Limits\AccountTotal::GetConfigUsage(),
            "(configlimits) --group id ".Limits\GroupTotal::GetConfigUsage(),
            "(configlimits) --filesystem id ".Limits\FilesystemTotal::GetConfigUsage(),
            'configtimedlimits (--account id | --group id | --filesystem id) '.Limits\Timed::BaseConfigUsage(),
            "(configtimedlimits) --account id ".Limits\AccountTimed::GetConfigUsage(),
            "(configtimedlimits) --group id ".Limits\GroupTimed::GetConfigUsage(),
            "(configtimedlimits) --filesystem id ".Limits\FilesystemTimed::GetConfigUsage(),
            'purgelimits (--account id | --group id | --filesystem id)',
            'purgetimedlimits (--account id | --group id | --filesystem id) --period uint',
        )); 
    }
    
    public function commit() { Storage::commitAll(); }    
    public function rollback() { Storage::rollbackAll(); }
    
    /**
     * {@inheritDoc}
     * @throws UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input)
    {
        if (($retval = parent::Run($input)) !== false) return $retval;

        $authenticator = Authenticator::TryAuthenticate(
            $this->database, $input, $this->API->GetInterface());
        
        $accesslog = AccessLog::Create($this->database, $authenticator); $input->SetLogger($accesslog);

        switch($input->GetAction())
        {
            case 'getconfig': return $this->RunGetConfig($input, $authenticator);
            case 'setconfig': return $this->RunSetConfig($input, $authenticator);
            
            case 'upload':     return $this->UploadFile($input, $authenticator, $accesslog);  
            case 'download':   $this->DownloadFile($input, $authenticator, $accesslog); return;
            case 'ftruncate':  return $this->TruncateFile($input, $authenticator, $accesslog);
            case 'writefile':  return $this->WriteToFile($input, $authenticator, $accesslog);
            case 'createfolder':  return $this->CreateFolder($input, $authenticator, $accesslog);
            
            case 'getfilelikes':   return $this->GetFileLikes($input, $authenticator, $accesslog);
            case 'getfolderlikes': return $this->GetFolderLikes($input, $authenticator, $accesslog);
            case 'getfilecomments':   return $this->GetFileComments($input, $authenticator, $accesslog);
            case 'getfoldercomments': return $this->GetFolderComments($input, $authenticator, $accesslog);
            
            case 'filemeta':      return $this->GetFileMeta($input, $authenticator, $accesslog);
            case 'getfolder':     return $this->GetFolder($input, $authenticator, $accesslog);
            case 'getitembypath': return $this->GetItemByPath($input, $authenticator, $accesslog);
            
            case 'ownfile':   return $this->OwnFile($input, $authenticator, $accesslog);
            case 'ownfolder': return $this->OwnFolder($input, $authenticator, $accesslog);
            
            case 'editfilemeta':   return $this->EditFileMeta($input, $authenticator, $accesslog);
            case 'editfoldermeta': return $this->EditFolderMeta($input, $authenticator, $accesslog);
           
            case 'deletefile':   $this->DeleteFile($input, $authenticator, $accesslog); return;
            case 'deletefolder': $this->DeleteFolder($input, $authenticator, $accesslog); return;
            case 'renamefile':   return $this->RenameFile($input, $authenticator, $accesslog);
            case 'renamefolder': return $this->RenameFolder($input, $authenticator, $accesslog);
            case 'movefile':     return $this->MoveFile($input, $authenticator, $accesslog);
            case 'movefolder':   return $this->MoveFolder($input, $authenticator, $accesslog);
            
            case 'likefile':      return $this->LikeFile($input, $authenticator, $accesslog);
            case 'likefolder':    return $this->LikeFolder($input, $authenticator, $accesslog);
            case 'tagfile':       return $this->TagFile($input, $authenticator, $accesslog);
            case 'tagfolder':     return $this->TagFolder($input, $authenticator, $accesslog);
            case 'deletetag':     $this->DeleteTag($input, $authenticator, $accesslog); return;
            case 'commentfile':   return $this->CommentFile($input, $authenticator, $accesslog);
            case 'commentfolder': return $this->CommentFolder($input, $authenticator, $accesslog);
            case 'editcomment':   return $this->EditComment($input, $authenticator);
            case 'deletecomment': $this->DeleteComment($input, $authenticator, $accesslog); return;
            
            case 'sharefile':    return $this->ShareFile($input, $authenticator, $accesslog);
            case 'sharefolder':  return $this->ShareFolder($input, $authenticator, $accesslog);
            case 'editshare':    return $this->EditShare($input, $authenticator, $accesslog);
            case 'deleteshare':  $this->DeleteShare($input, $authenticator, $accesslog); return;
            case 'shareinfo':    return $this->ShareInfo($input, $authenticator, $accesslog);
            case 'listshares':   return $this->ListShares($input, $authenticator);
            case 'listadopted':  return $this->ListAdopted($input, $authenticator);
            
            case 'getfilesystem':  return $this->GetFilesystem($input, $authenticator, $accesslog);
            case 'getfilesystems': return $this->GetFilesystems($input, $authenticator);
            case 'createfilesystem': return $this->CreateFilesystem($input, $authenticator, $accesslog);
            case 'deletefilesystem': $this->DeleteFilesystem($input, $authenticator, $accesslog); return;
            case 'editfilesystem':   return $this->EditFilesystem($input, $authenticator);
            
            case 'getlimits':      return $this->GetLimits($input, $authenticator);
            case 'gettimedlimits': return $this->GetTimedLimits($input, $authenticator);
            case 'gettimedstatsfor': return $this->GetTimedStatsFor($input, $authenticator);
            case 'gettimedstatsat':  return $this->GetTimedStatsAt($input, $authenticator);
            case 'configlimits':      return $this->ConfigLimits($input, $authenticator);
            case 'configtimedlimits': return $this->ConfigTimedLimits($input, $authenticator);
            case 'purgelimits':      $this->PurgeLimits($input, $authenticator); return;
            case 'purgetimedlimits': $this->PurgeTimedLimits($input, $authenticator); return;
            
            default: throw new UnknownActionException();
        }
    }
    
    /** Returns an ItemAccess authenticating the given file ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFileAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, ?string $id = null) : ItemAccess 
    {
        $id ??= $input->GetParam('file', SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        return $this->AuthenticateItemAccess($input, $auth, $accesslog, File::class, $id);
    }

    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFolderAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, ?string $id = null, bool $isParent = false) : ItemAccess 
    { 
        $id ??= $input->GetParam('folder', SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        return $this->AuthenticateItemAccess($input, $auth, $accesslog, Folder::class, $id, $isParent);
    }
        
    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), returns null on failure */
    private function TryAuthenticateFolderAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, ?string $id = null, bool $isParent = false) : ?ItemAccess 
    {
        $id ??= $input->GetOptParam('folder', SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        return $this->TryAuthenticateItemAccess($input, $auth, $accesslog, Folder::class, $id, $isParent);
    }    
    
    /** Throws an unknown file/folder exception if given, else item exception */
    private static function UnknownItemException(?string $class = null) : void
    {
        switch ($class)
        {
            case File::class: throw new UnknownFileException();
            case Folder::class: throw new UnknownFolderException();
            default: throw new UnknownItemException();
        }
    }
    
    /** Returns an ItemAccess authenticating the given item class/ID, throws exceptions on failure */
    private function AuthenticateItemAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, string $class, ?string $id, bool $isParent = false) : ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            
            if ($item === null) self::UnknownItemException($class);
        }
        
        $access = ItemAccess::Authenticate($this->database, $input, $auth, $item); 

        if (!is_a($access->GetItem(), $class)) self::UnknownItemException($class);
        
        if ($accesslog) $accesslog->LogAccess($access->GetItem(), $access->GetShare(), $isParent); 
        
        return $access;
    }
        
    /** Returns an ItemAccess authenticating the given item class/ID, returns null on failure */
    private function TryAuthenticateItemAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, string $class, ?string $id, bool $isParent = false) : ?ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            
            if ($item === null) self::UnknownItemException($class);
        }
        
        $access = ItemAccess::TryAuthenticate($this->database, $input, $auth, $item); 
        
        if ($access !== null && !is_a($access->GetItem(), $class)) return null;
        
        if ($accesslog && $access !== null) 
            $accesslog->LogAccess($access->GetItem(), $access->GetShare(), $isParent);
        
        return $access;
    }
    
    /** Returns an ItemAccess authenticating the given already-loaded object */
    private function AuthenticateItemObjAccess(Input $input, ?Authenticator $auth, ?AccessLog $accesslog, Item $item, bool $isParent = false) : ItemAccess
    {
        $access = ItemAccess::Authenticate($this->database, $input, $auth, $item);
        
        if ($accesslog) $accesslog->LogAccess($access->GetItem(), $access->GetShare(), $isParent);
        
        return $access;
    }

    /**
     * Gets config for this app
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function RunGetConfig(Input $input, ?Authenticator $authenticator) : array
    {
        $admin = $authenticator !== null && $authenticator->isAdmin();
        
        return $this->GetConfig()->GetClientObject($admin);
    }
    
    /**
     * Sets config for this app
     * @throws AuthenticationFailedException if not admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function RunSetConfig(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequireAdmin();

        return $this->GetConfig()->SetConfig($input)->GetClientObject(true);
    }
    
    /**
     * Uploads a new file to the given folder. Bandwidth is counted.
     * @throws AuthenticationFailedException if not signed in and public upload not allowed
     * @throws ItemAccessDeniedException if accessing via share and share does not allow upload
     * @return array File newly created file
     * @see File::GetClientObject()
     */
    protected function UploadFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        $parentid = $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $paccess = $this->AuthenticateFolderAccess($input, $authenticator, $accesslog, $parentid, true);
        $parent = $paccess->GetFolder(); $share = $paccess->GetShare();
        
        if (!$authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        if ($share !== null && (!$share->CanUpload() || ($overwrite && !$share->CanModify()))) 
            throw new ItemAccessDeniedException();
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;
        
        $infile = $input->GetFile('file');
        
        if ($infile instanceof InputPath)
        {
            $parent->CountBandwidth($infile->GetSize());
            
            $fileobj = File::Import($this->database, $parent, $owner, $infile, $overwrite);
        }
        else // can't import handles directly
        {
            $name = $input->GetParam('name',SafeParam::TYPE_FSNAME);
            
            if (!($handle = $infile->GetHandle())) throw new FileReadFailedException();
            
            $fileobj = File::Create($this->database, $parent, $owner, $name, $overwrite);
            
            FileUtils::ChunkedWrite($this->database, $handle, $fileobj, 0); fclose($handle);
        }
        
        if ($accesslog) $accesslog->LogDetails('file',$fileobj->ID()); 
        
        return $fileobj->GetClientObject(($owner === $account));
    }
    
    /**
     * Downloads a file or part of a file
     * 
     * Can accept an input byte range. Also accepts the HTTP_RANGE header.
     * @throws ItemAccessDeniedException if accessing via share and read is not allowed
     * @throws InvalidDLRangeException if the given byte range is invalid
     */
    protected function DownloadFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        // TODO CLIENT - since this is not AJAX, we might want to redirect to a page when doing a 404, etc. - better than plaintext - use appurl config
        
        $debugdl = ($input->GetOptParam('debugdl',SafeParam::TYPE_BOOL) ?? false) &&
            $this->API->GetDebugLevel() >= \Andromeda\Core\Config::ERRLOG_DETAILS;
        
        // debugdl disables file output printing and instead does a normal JSON return
        if (!$debugdl) $this->API->GetInterface()->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        $access = $this->AuthenticateFileAccess($input, $authenticator, $accesslog); 
        $file = $access->GetFile(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();

        // first determine the byte range to read
        $fsize = $file->GetSize();
        $fstart = $input->GetOptNullParam('fstart',SafeParam::TYPE_UINT,SafeParams::PARAMLOG_NEVER) ?? 0;
        $flast  = $input->GetOptNullParam('flast',SafeParam::TYPE_INT,SafeParams::PARAMLOG_NEVER) ?? $fsize-1;
        
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

        if ($accesslog) $accesslog->LogDetails('fstart',$fstart)->LogDetails('flast',$flast);
        
        // check required bandwidth ahead of time
        $length = $flast-$fstart+1;
        $file->CheckBandwidth($length);

        if ($flast == $fsize-1) // the end of the file
            $file->CountDownload(($share !== null));
        
        // send necessary headers
        if (!$debugdl)
        {
            if ($fstart != 0 || $flast != $fsize-1)
            {
                http_response_code(206);
                header("Content-Range: bytes $fstart-$flast/$fsize");
            }
            
            header("Content-Length: $length");
            header("Accept-Ranges: bytes");
            header("Content-Type: application/octet-stream");
            header('Content-Disposition: attachment; filename="'.$file->GetName().'"');
            header('Content-Transfer-Encoding: binary');
        }

        // register the data output to happen after the main commit so that we don't get to the
        // end of the download and then fail to insert a stats row and miss counting bandwidth
        $this->API->GetInterface()->RegisterOutputHandler(new OutputHandler(
            function() use($debugdl,$length){ return $debugdl ? null : $length; },
            function(Output $output) use($file,$fstart,$flast,$debugdl)
        {            
            set_time_limit(0); ignore_user_abort(true);
            
            FileUtils::ChunkedRead($this->database,$file,$fstart,$flast,$debugdl);
        }));
    }
        
    /**
     * Writes new data to an existing file - data is posted as a file
     * 
     * If no offset is given, the default is to append the file (offset = file size)
     * DO NOT use this in a multi-action transaction as the underlying FS cannot fully rollback writes.
     * The FS will restore the original size of the file but writes within the original size are permanent.
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws RandomWriteDisabledException if random write is not allowed on the file
     * @throws ItemAccessDeniedException if acessing via share and share doesn't allow modify
     * @return array File
     * @see File::GetClientObject()
     */
    protected function WriteToFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {        
        $access = $this->AuthenticateFileAccess($input, $authenticator, $accesslog);
        $file = $access->GetFile(); $share = $access->GetShare();
        
        $account = $authenticator ? $authenticator->GetAccount() : null;
        
        $wstart = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT,SafeParams::PARAMLOG_NEVER) ?? $file->GetSize();
        
        if ($accesslog) $accesslog->LogDetails('wstart',$wstart);
        
        $infile = $input->GetFile('data');
        
        if ($infile instanceof InputPath)
        {
            $file->CountBandwidth($infile->GetSize());
        }
        
        if (!$account && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
            
        if ($share !== null && !$share->CanModify()) 
            throw new ItemAccessDeniedException();   

        if ($infile instanceof InputPath && !$wstart && $infile->GetSize() >= $file->GetSize())
        {
            // for a full overwrite, we can call SetContents for efficiency
            if ($share !== null && !$share->CanUpload())
                throw new ItemAccessDeniedException();
            
            return $file->SetContents($infile)->GetClientObject(($share === null));
        }
        else
        {
            // require randomWrite permission if not appending
            if ($wstart != $file->GetSize() && !$file->GetAllowRandomWrite($account))
                throw new RandomWriteDisabledException();
            
            if (!($handle = $infile->GetHandle())) throw new FileReadFailedException();
            
            $wlength = FileUtils::ChunkedWrite($this->database, $handle, $file, $wstart); fclose($handle);
            
            if ($infile instanceof InputPath && $wlength !== $infile->GetSize())
                throw new FileWriteFailedException();
            
            if ($accesslog) $accesslog->LogDetails('wlength',$wlength);
            
            return $file->GetClientObject(($share === null));
        }
    }

    /**
     * Truncates (resizes a file)
     * 
     * DO NOT use this in a multi-action transaction as the underlying FS cannot fully rollback truncates.
     * The FS will restore the original size of the file but if the file was shrunk, data will be zeroed.
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws RandomWriteDisabledException if random writes are not enabled on the file
     * @throws ItemAccessDeniedException if access via share and share does not allow modify
     * @return array File
     * @see File::GetClientObject()
     */
    protected function TruncateFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {        
        $access = $this->AuthenticateFileAccess($input, $authenticator, $accesslog);
        $file = $access->GetFile(); $share = $access->GetShare();

        $account = $authenticator ? $authenticator->GetAccount() : null;
        
        if (!$account && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if (!$file->GetAllowRandomWrite($account))
            throw new RandomWriteDisabledException();
            
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();

        $file->SetSize($input->GetParam('size',SafeParam::TYPE_UINT,SafeParams::PARAMLOG_ALWAYS));
        
        return $file->GetClientObject(($share === null));
    }

    /**
     * Returns file metadata
     * @throws ItemAccessDeniedException if accessing via share and reading is not allowed
     * @return array File
     * @see File::GetClientObject()
     */
    protected function GetFileMeta(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : ?array
    {
        $access = $this->AuthenticateFileAccess($input, $authenticator, $accesslog);
        $file = $access->GetFile(); $share = $access->GetShare();

        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $details = $input->GetOptParam('details',SafeParam::TYPE_BOOL) ?? false;
        
        return $file->GetClientObject(($share === null), $details);
    }

    /**
     * Lists folder metadata and optionally the items in a folder (or filesystem root)
     * @throws ItemAccessDeniedException if accessing via share and reading is not allowed
     * @throws AuthenticationFailedException if public access and no folder ID is given
     * @throws UnknownFilesystemException if the given filesystem does not exist
     * @throws UnknownFolderException if the given folder does not exist
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function GetFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : ?array
    {
        if (($fid = $input->GetOptParam('folder',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER)) !== null)
        {
            $access = $this->AuthenticateFolderAccess($input, $authenticator, $accesslog, $fid);
            $folder = $access->GetFolder(); $share = $access->GetShare();
            
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();
            
            $filesys = $input->GetOptParam('filesystem',SafeParam::TYPE_RANDSTR);
            if ($filesys !== null)
            {
                $filesys = FSManager::TryLoadByAccountAndID($this->database, $account, $filesys, true);  
                if ($filesys === null) throw new UnknownFilesystemException();
            }
                
            $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $filesys);
            
            if ($accesslog) $accesslog->LogAccess($folder, null);
        }

        if ($folder === null) throw new UnknownFolderException();
        
        $files = $input->GetOptParam('files',SafeParam::TYPE_BOOL) ?? true;
        $folders = $input->GetOptParam('folders',SafeParam::TYPE_BOOL) ?? true;
        $recursive = $input->GetOptParam('recursive',SafeParam::TYPE_BOOL) ?? false;
        
        $limit = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
        
        $details = $input->GetOptParam('details',SafeParam::TYPE_BOOL) ?? false;
        
        $public = isset($share) && $share !== null;

        if ($public && ($files || $folders)) $folder->CountPublicVisit();
        
        return $folder->GetClientObject(!$public,$details,$files,$folders,$recursive,$limit,$offset);
    }

    /**
     * Reads an item by a path (rather than by ID) - can specify a root folder or filesystem
     * 
     * NOTE that /. and /.. have no special meaning - no traversal allowed
     * @throws ItemAccessDeniedException if access via share and read is not allowed
     * @throws AuthenticationFailedException if public access and no root is given
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @throws UnknownFolderException if the given folder is not found
     * @throws UnknownItemException if the given item path is invalid
     * @return array File|Folder with {isfile:bool}
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function GetItemByPath(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $share = null; if (($raccess = $this->TryAuthenticateFolderAccess($input, $authenticator, $accesslog)) !== null)
        {
            $folder = $raccess->GetItem(); $share = $raccess->GetShare();
            if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        }
        else // no root folder given
        {
            if ($authenticator === null) throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();
            
            $filesystem = $input->GetOptParam('filesystem',SafeParam::TYPE_RANDSTR);
            if ($filesystem !== null)
            {
                $filesystem = FSManager::TryLoadByID($this->database, $filesystem);
                if ($filesystem === null) throw new UnknownFilesystemException();
            }
            
            $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $filesystem);

            if ($accesslog) $accesslog->LogAccess($folder, null);
        }        
        
        if ($folder === null) throw new UnknownFolderException();
        
        $path = $input->GetParam('path',SafeParam::TYPE_FSPATH);
        $path = array_filter(explode('/',$path)); $name = array_pop($path);

        foreach ($path as $subfolder)
        {
            $subfolder = Folder::TryLoadByParentAndName($this->database, $folder, $subfolder);
            if ($subfolder === null) throw new UnknownFolderException(); else $folder = $subfolder;
        }
        
        $item = null; $isfile = $input->GetOptParam('isfile',SafeParam::TYPE_BOOL);
        
        if ($name === null) 
        {
            $item = ($isfile !== true) ? $folder : null; // trailing / for folder
        }
        else
        {
            if ($isfile === null || $isfile) $item = File::TryLoadByParentAndName($this->database, $folder, $name);
            if ($item === null && !$isfile)  $item = Folder::TryLoadByParentAndName($this->database, $folder, $name);
        }
        
        if ($item === null) throw new UnknownItemException();

        if ($item instanceof File) 
        {
            $retval = $item->GetClientObject(($share === null));
        }
        else if ($item instanceof Folder)
        {
            if ($share !== null) $item->CountPublicVisit();
            $retval = $item->GetClientObject(($share === null),false,true,true);
        }
        
        $retval['isfile'] = ($item instanceof File); return $retval;
    }
    
    /**
     * Edits file metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFileMeta(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : ?array
    {
        return $this->EditItemMeta($this->AuthenticateFileAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Edits folder metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFolderMeta(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : ?array
    {
        return $this->EditItemMeta($this->AuthenticateFolderAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Edits item metadata
     * @param ItemAccess $access access object for item
     * @throws ItemAccessDeniedException if accessing via share and can't modify
     * @return array|NULL Item
     * @see Item::GetClientObject()
     */
    private function EditItemMeta(ItemAccess $access, Input $input) : ?array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        if ($input->HasParam('description')) $item->SetDescription($input->GetNullParam('description',SafeParam::TYPE_TEXT));
        
        return $item->GetClientObject(($share === null));
    }    
    
    /**
     * Takes ownership of a file
     * @return array File
     * @see File::GetClientObject()
     */
    protected function OwnFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetParam('file',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        
        $file = File::TryLoadByID($this->database, $id);
        if ($file === null) throw new UnknownFileException();
        
        if ($file->isWorldAccess() || $file->GetParent()->GetOwner() !== $account)
            throw new ItemAccessDeniedException();
            
        if ($accesslog) $accesslog->LogAccess($file, null);
            
        return $file->SetOwner($account)->GetClientObject(true);
    }
    
    /**
     * Takes ownership of a folder
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function OwnFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetParam('folder',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        
        $folder = Folder::TryLoadByID($this->database, $id);
        if ($folder === null) throw new UnknownFolderException();
        
        if ($folder->isWorldAccess()) throw new ItemAccessDeniedException();
        
        $accesslog->LogAccess($folder, null);
        
        $parent = $folder->GetParent();
        if ($parent === null || $parent->GetOwner() !== $account)
            throw new ItemAccessDeniedException();
            
        if ($input->GetOptParam('recursive',SafeParam::TYPE_BOOL))
        {
            $folder->SetOwnerRecursive($account);
        }
        else $folder->SetOwner($account);
        
        return $folder->SetOwner($account)->GetClientObject(true);
    }    

    /**
     * Creates a folder in the given parent
     * @throws AuthenticationFailedException if public access and public upload not allowed
     * @throws ItemAccessDeniedException if accessing via share and share upload not allowed
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function CreateFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        $parentid = $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $access = $this->AuthenticateFolderAccess($input, $authenticator, $accesslog, $parentid, true);
        $parent = $access->GetFolder(); $share = $access->GetShare();
        
        if (!$authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanUpload()) throw new ItemAccessDeniedException();

        $name = $input->GetParam('name',SafeParam::TYPE_FSNAME);
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;

        $folder = SubFolder::Create($this->database, $parent, $owner, $name);
        
        if ($accesslog) $accesslog->LogDetails('folder',$folder->ID()); 
        
        return $folder->GetClientObject(($owner === $account));
    }
    
    /**
     * Deletes a file
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        $this->DeleteItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /**
     * Deletes a folder
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        $this->DeleteItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Deletes an item.
     * 
     * DO NOT use this in a multi-action transaction as the underlying FS cannot rollback deletes.
     * If you delete an item and then do another action that results in an error, the content
     * will still be deleted on disk though the database objects will remain.
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     */
    private function DeleteItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);

        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->GetShare();
        
        if (!$authenticator && !$itemobj->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('item', $itemobj->TryGetClientObject());

        $itemobj->Delete();
    }
    
    /**
     * Renames (or copies) a file
     * @see FilesApp::RenameItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function RenameFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->RenameItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /**
     * Renames (or copies) a folder
     * @see FilesApp::RenameItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function RenameFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->RenameItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Renames or copies an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws ItemAccessDeniedException if access via share and share upload/modify is not allowed
     * @throws AuthenticationFailedException if public access and public upload/modify is not allowed
     */
    private function RenameItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $copy = $input->GetOptParam('copy',SafeParam::TYPE_BOOL) ?? false;

        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        if ($item instanceof RootFolder && (!$account || !$item->isFSOwnedBy($account)))
            throw new ItemAccessDeniedException();
        
        $name = $input->GetParam('name',SafeParam::TYPE_FSNAME);
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;
        
        $paccess = $this->AuthenticateItemObjAccess($input, $authenticator, $accesslog, $item->GetParent(), true);
        
        $parent = $paccess->GetItem(); $pshare = $paccess->GetShare();
        
        if ($copy)
        {
            if (!$authenticator && !$parent->GetAllowPublicUpload())
                throw new AuthenticationFailedException();
            
            if ($pshare !== null && !$pshare->CanUpload()) throw new ItemAccessDeniedException();           
            
            $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;            
            
            $retitem = $item->CopyToName($owner, $name, $overwrite);
        }
        else
        {
            if (!$authenticator && !$parent->GetAllowPublicModify())
                throw new AuthenticationFailedException();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
            
            $retitem = $item->SetName($name, $overwrite);
        }
        
        return $retitem->GetClientObject(($share === null));
    }
    
    /**
     * Moves (or copies) a file
     * @see FilesApp::MoveItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function MoveFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->MoveItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /**
     * Moves (or copies) a folder
     * @see FilesApp::MoveItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function MoveFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->MoveItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Moves or copies an item.
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws ItemAccessDeniedException if access via share and share modify/upload not allowed
     */
    private function MoveItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $copy = $input->GetOptParam('copy',SafeParam::TYPE_BOOL) ?? false;
        
        $itemid = $input->GetParam($key,SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        
        $parentid = $input->GetOptParam('parent',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $paccess = $this->AuthenticateFolderAccess($input, $authenticator, $accesslog, $parentid, true);
        $parent = $paccess->GetItem(); $pshare = $paccess->GetShare();
        
        if (!$authenticator && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
            
        if ($pshare !== null && !$pshare->CanUpload()) throw new ItemAccessDeniedException();
        
        $overwrite = $input->GetOptParam('overwrite',SafeParam::TYPE_BOOL) ?? false;        
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();

        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $itemid);
        $item = $access->GetItem(); $share = $access->GetShare();

        if ($copy)
        {
            $owner = ($share !== null && !$share->KeepOwner()) ? $parent->GetOwner() : $account;
            
            return $item->CopyToParent($owner, $parent, $overwrite);
        }
        else 
        {
            if (!$authenticator && !$item->GetAllowPublicModify())
                throw new AuthenticationFailedException();
            
            if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
            
            $owner = $item->GetOwner(); $retobj = $item->SetParent($parent, $overwrite);
        }
        
        return $retobj->GetClientObject(($owner === $account));
    }
    
    /** 
     * Likes or dislikes a file 
     * @see FilesApp::LikeItem()
     */
    protected function LikeFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->LikeItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /** 
     * Likes or dislikes a folder
     * @see FilesApp::LikeItem()
     */
    protected function LikeFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->LikeItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Likes or dislikes an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share if social is not allowed
     * @return array ?Like
     * @see Like::GetClientObject()
     */
    private function LikeItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $value = $input->GetOptParam('value',SafeParam::TYPE_BOOL);
        
        $like = Like::CreateOrUpdate($this->database, $account, $item, $value);
        
        return ($like !== null) ? $like->GetClientObject() : null;
    }
    
    /** 
     * Adds a tag to a file
     * @see FilesApp::TagItem()
     */
    protected function TagFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->TagItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /** 
     * Adds a tag to a folder
     * @see FilesApp::TagItem() 
     */
    protected function TagFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->TagItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Adds a tag to an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     * @return array Tag
     * @see Tag::GetClientObject()
     */
    private function TagItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $tag = $input->GetParam('tag', SafeParam::TYPE_ALPHANUM, 
            SafeParams::PARAMLOG_ONLYFULL, null, SafeParam::MaxLength(127));
        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);

        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        $tagobj = Tag::Create($this->database, $account, $itemobj, $tag);
        
        if ($accesslog) $accesslog->LogDetails('tag',$tagobj->ID()); 
        
        return $tagobj->GetClientObject();
    }
    
    /**
     * Deletes an item tag
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given tag is not found
     * @throws ItemAccessDeniedException if access via share and share modify is not allowed
     */
    protected function DeleteTag(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $id = $input->GetParam('tag', SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        $tag = Tag::TryLoadByID($this->database, $id);
        if ($tag === null) throw new UnknownItemException();

        $access = $this->AuthenticateItemObjAccess($input, $authenticator, $accesslog, $tag->GetItem());
        
        $share = $access->GetShare();
        
        if ($share !== null && !$share->CanModify()) throw new ItemAccessDeniedException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('tag', $tag->GetClientObject());
        
        $tag->Delete();
    }
    
    /**
     * Adds a comment to a file
     * @see FilesApp::CommentItem()
     */
    protected function CommentFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->CommentItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /**
     * Adds a comment to a folder
     * @see FilesApp::CommentFolder()
     */
    protected function CommentFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->CommentItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Adds a comment to an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws ItemAccessDeniedException if access via share and share social is not allowed
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function CommentItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetParam($key, SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $id);
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanSocial()) throw new ItemAccessDeniedException();
        
        $comment = $input->GetParam('comment', SafeParam::TYPE_TEXT);       
        $cobj = Comment::Create($this->database, $account, $item, $comment);
        
        if ($accesslog) $accesslog->LogDetails('comment',$cobj->ID()); 
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Edits an existing comment properties
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the comment is not found
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    protected function EditComment(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
                
        $id = $input->GetParam('commentid',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        $comment = $input->GetOptParam('comment', SafeParam::TYPE_TEXT);
        if ($comment !== null) $cobj->SetComment($comment);
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Deletes a comment
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the comment is not found
     */
    protected function DeleteComment(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetParam('commentid',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new UnknownItemException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('comment', $cobj->GetClientObject());
        
        $cobj->Delete();
    }
    
    /**
     * Returns comments on a file
     * @see FilesApp::GetItemComments()
     */
    protected function GetFileComments(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : array
    {
        return $this->GetItemComments($this->AuthenticateFileAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Returns comments on a folder
     * @see FilesApp::GetItemComments()
     */
    protected function GetFolderComments(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : array
    {
        return $this->GetItemComments($this->AuthenticateFolderAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Returns comments on an item
     * @param ItemAccess $access file or folder access object
     * @throws ItemAccessDeniedException if access via share and can't read
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function GetItemComments(ItemAccess $access, Input $input) : array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $limit = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
        
        $comments = $item->GetComments($limit, $offset);

        return array_map(function(Comment $c){ return $c->GetClientObject(); }, $comments);
    }
    
    /**
     * Returns likes on a file
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFileLikes(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : array
    {
        return $this->GetItemLikes($this->AuthenticateFileAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Returns likes on a folder
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFolderLikes(Input $input, ?Authenticator $auth, ?AccessLog $accesslog) : array
    {
        return $this->GetItemLikes($this->AuthenticateFolderAccess($input, $auth, $accesslog), $input);
    }
    
    /**
     * Returns likes on an item
     * @param ItemAccess $access file or folder access object
     * @throws ItemAccessDeniedException if access via share and can't read
     * @return array Like
     * @see Like::GetClientObject()
     */
    private function GetItemLikes(ItemAccess $access, Input $input) : array
    {
        $item = $access->GetItem(); $share = $access->GetShare();
        
        if ($share !== null && !$share->CanRead()) throw new ItemAccessDeniedException();
        
        $limit = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
        
        $likes = $item->GetLikes($limit, $offset);
        
        return array_map(function(Like $c){ return $c->GetClientObject(); }, $likes);
    }
    
    /**
     * Creates shares for a file
     * @see FilesApp::ShareItem()
     */
    protected function ShareFile(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->ShareItem(File::class, 'file', $input, $authenticator, $accesslog);
    }
    
    /**
     * Creates shares for a folder
     * @see FilesApp::ShareItem()
     */
    protected function ShareFolder(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        return $this->ShareItem(Folder::class, 'folder', $input, $authenticator, $accesslog);
    }
    
    /**
     * Creates shares for an item
     * @param string $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws UnknownDestinationException if the given share target is not found
     * @throws UnknownItemException if the given item to share is not found
     * @throws EmailShareDisabledException if emailing shares is not enabled
     * @throws ShareURLGenerateException if the URL to email could be not determined
     * @return array Share
     * @see Share::GetClientObject()
     */
    private function ShareItem(string $class, string $key, Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $item = $input->GetParam($key,SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER);
        
        $destacct = $input->GetOptParam('account',SafeParam::TYPE_RANDSTR);
        $destgroup = $input->GetOptParam('group',SafeParam::TYPE_RANDSTR);
        $everyone = $input->GetOptParam('everyone',SafeParam::TYPE_BOOL) ?? false;
        $islink = $input->GetOptParam('link',SafeParam::TYPE_BOOL) ?? false;
        
        if (count(array_filter(array($destacct,$destgroup,$everyone,$islink))) !== 1)
            throw new InvalidShareTargetException(); // only one dest allowed

        $access = self::AuthenticateItemAccess($input, $authenticator, $accesslog, $class, $item);
        
        $oldshare = $access->GetShare(); $item = $access->GetItem();
        if ($oldshare !== null && !$oldshare->CanReshare())
            throw new ItemAccessDeniedException();
        
        if (!$item->GetAllowItemSharing($account))
            throw new ItemSharingDisabledException();
        
        if ($islink) $share = Share::CreateLink($this->database, $account, $item);
        else
        {
            $dest = null;
            
            if ($destacct !== null)
            {
                if (($dest = Account::TryLoadByID($this->database, $destacct)) === null)
                    throw new UnknownAccountException();
            }
            else if ($destgroup !== null)
            {
                if (!$item->GetAllowShareToGroups($account))
                    throw new ShareTargetDisabledException();
                    
                if (($dest = Group::TryLoadByID($this->database, $destgroup)) === null)
                    throw new UnknownGroupException();
            }
            else if ($everyone && !$item->GetAllowShareToEveryone($account))
                throw new ShareTargetDisabledException();
            
            $share = Share::Create($this->database, $account, $item, $dest);
        }
        
        $share->SetOptions($input, $oldshare);
        
        if ($accesslog) $accesslog->LogDetails('share',$share->ID()); 
        
        $shares = array($share); $retval = $share->GetClientObject(false, true, $islink);
        
        if ($islink && ($email = $input->GetOptParam('email',SafeParam::TYPE_EMAIL)) !== null)
        {
            if (!Limits\AccountTotal::LoadByAccount($this->database, $account, true)->GetAllowEmailShare())
                throw new EmailShareDisabledException();
            
            $account = $authenticator->GetAccount();
            $subject = $account->GetDisplayName()." shared files with you"; 
            
            $body = implode("<br />",array_map(function(Share $share)
            {                
                $url = $this->GetConfig()->GetAPIUrl();
                if (!$url) throw new ShareURLGenerateException();
                
                $cmd = (new Input('files','download'))->AddParam('sid',$share->ID())->AddParam('skey',$share->GetAuthKey());
                
                return "<a href='".AJAX::GetRemoteURL($url, $cmd)."'>".$share->GetItem()->GetName()."</a>";
            }, $shares)); 
            
            // TODO CLIENT - param for the client to have the URL point at the client
            // TODO CLIENT - HTML - configure a directory where client templates reside

            $this->API->GetConfig()->GetMailer()->SendMail($subject, $body, true,
                array(new EmailRecipient($email)), $account->GetEmailFrom(), false);
        }
        
        return $retval;
    }    

    /**
     * Edits properties of an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given share is not found
     * @throws ItemAccessDeniedException if not allowed
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function EditShare(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetOptParam('share',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        $share = Share::TryLoadByID($this->database, $id);
        if ($share === null) throw new UnknownItemException();        
        
        // allowed to edit the share if you have owner level access to the item, or own the share
        $origshare = $this->AuthenticateItemObjAccess($input, $authenticator, $accesslog, $share->GetItem())->GetShare();
        if ($origshare !== null && $share->GetOwner() !== $account) throw new ItemAccessDeniedException();
        
        return $share->SetOptions($input, $origshare)->GetClientObject();
    }
    
    /**
     * Deletes an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownItemException if the given share is not found
     * @throws ItemAccessDeniedException if not allowed
     */
    protected function DeleteShare(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $input->GetOptParam('share',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        $share = Share::TryLoadByID($this->database, $id);
        if ($share === null) throw new UnknownItemException();

        // if you don't own the share, you must have owner-level access to the item        
        if ($share->GetOwner() !== $account)
        {
            if ($this->AuthenticateItemObjAccess($input, $authenticator, $accesslog,
                    $share->GetItem())->GetShare() !== null)
                throw new ItemAccessDeniedException();
        }
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('share', $share->GetClientObject());
        
        $share->Delete();
    }
    
    /**
     * Retrieves metadata on a share object (from a link)
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function ShareInfo(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        $access = ItemAccess::Authenticate($this->database, $input, $authenticator);
        
        if ($accesslog) $accesslog->LogAccess($access->GetItem(), $access->GetShare());
        
        return $access->GetShare()->GetClientObject(false, false);
    }
    
    /**
     * Returns a list of shares
     * 
     * if $mine, show all shares we created, else show all shares we're the target of
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:Share]
     * @see Share::GetClientObject()
     */
    protected function ListShares(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $mine = $input->GetOptParam('mine',SafeParam::TYPE_BOOL) ?? false;
        
        if ($mine) $shares = Share::LoadByAccountOwner($this->database, $account);
        else $shares = Share::LoadByAccountDest($this->database, $account);
        
        if (!$mine) $shares = array_filter(function(Share $sh){ return !$sh->isExpired(); });
        
        return array_map(function($share)use($mine){ return $share->GetClientObject(true, $mine); }, $shares);
    }
    
    /**
     * Returns a list of all items where the user owns the item but not the parent
     * 
     * These are items that the user uploaded into someone else's folder, but owns
     * @throws AuthenticationFailedException if not signed in 
     * @return array `{files:[id:File],folders:[id:Folder]}`
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function ListAdopted(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $files = File::LoadAdoptedByOwner($this->database, $account);
        $folders = Folder::LoadAdoptedByOwner($this->database, $account);
        
        $files = array_map(function(File $file){ return $file->GetClientObject(true); }, $files);
        $folders = array_map(function(Folder $folder){ return $folder->GetClientObject(true); }, $folders);
        
        return array('files'=>$files, 'folders'=>$folders);
    }
    
    /**
     * Returns filesystem metadata (default if none specified)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if no filesystem was specified or is the default
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function GetFilesystem(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        if (($filesystem = $input->GetOptParam('filesystem',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_NEVER)) !== null)
        {
            $filesystem = FSManager::TryLoadByID($this->database, $filesystem);
        }
        else $filesystem = FSManager::LoadDefaultByAccount($this->database, $account);
        
        if ($filesystem === null) throw new UnknownFilesystemException();

        if ($accesslog) $accesslog->LogDetails('filesystem',$filesystem->ID());
        
        $ispriv = $authenticator->isAdmin() || ($account === $filesystem->GetOwner());
        
        $activate = $input->GetOptParam('activate',SafeParam::TYPE_BOOL) ?? false;
        
        return $filesystem->GetClientObject($ispriv, $activate);
    }
    
    /**
     * Returns a list of all filesystems available
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:FSManager]
     * @see FSManager::GetClientObject()
     */
    protected function GetFilesystems(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();

        if ($authenticator->isAdmin() && $input->GetOptParam('everyone',SafeParam::TYPE_BOOL))
        {
            $limit = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
            $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
            
            $filesystems = FSManager::LoadAll($this->database, $limit, $offset);
        }
        else $filesystems = FSManager::LoadByAccount($this->database, $account);
        
        return array_map(function($filesystem){ return $filesystem->GetClientObject(); }, $filesystems);
    }
    
    /**
     * Creates a new filesystem
     * @throws AuthenticationFailedException if not signed in
     * @throws UserStorageDisabledException if not admin and user storage is not allowed
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function CreateFilesystem(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $global = ($input->GetOptParam('global', SafeParam::TYPE_BOOL) ?? false) && $authenticator->isAdmin();

        if (!Limits\AccountTotal::LoadByAccount($this->database, $account, true)->GetAllowUserStorage() && !$global)
            throw new UserStorageDisabledException();
            
        $filesystem = FSManager::Create($this->database, $input, $global ? null : $account);
        
        if ($accesslog) $accesslog->LogDetails('filesystem',$filesystem->ID()); 
        
        return $filesystem->GetClientObject(true);
    }

    /**
     * Edits an existing filesystem
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @return array FSManager
     * @see FSManager::GetClientObject()
     */
    protected function EditFilesystem(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem', SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        if ($authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->database, $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        if ($filesystem === null) throw new UnknownFilesystemException();

        return $filesystem->Edit($input)->GetClientObject(true);
    }

    /**
     * Removes a filesystem (and potentially its content)
     * @throws AuthenticationFailedException if not signed in
     * @throws UnknownFilesystemException if the given filesystem is not found
     */
    protected function DeleteFilesystem(Input $input, ?Authenticator $authenticator, ?AccessLog $accesslog) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequirePassword();
        $account = $authenticator->GetAccount();
        
        $fsid = $input->GetParam('filesystem',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS);
        
        if ($authenticator->isAdmin())
            $filesystem = FSManager::TryLoadByID($this->database, $fsid);
        else $filesystem = FSManager::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        $unlink = $input->GetOptParam('unlink', SafeParam::TYPE_BOOL) ?? false;

        if ($filesystem === null) throw new UnknownFilesystemException();
        
        if ($accesslog && AccessLog::isFullDetails()) 
            $accesslog->LogDetails('filesystem', $filesystem->GetClientObject(true));
        
        $filesystem->Delete($unlink);
    }
    
    /**
     * Common function for loading and authenticating the limited object and limit class referred to by input
     * @param bool $allowAuto if true, return the current account if no object is specified
     * @param bool $allowMany if true, allow selecting all of the given type
     * @param bool $timed if true, return a timed limit class (not total)
     * @throws UnknownGroupException if the given group is not found
     * @throws UnknownAccountException if the given account is not found
     * @throws UnknownFilesystemException if the given filesystem is not found
     * @throws UnknownObjectException if nothing valid was specified
     * @return array `{class:string, obj:object}`
     */
    private function GetLimitObject(Input $input, ?Authenticator $authenticator, bool $allowAuto, bool $allowMany, bool $timed) : array
    {
        $obj = null;
        
        $admin = $authenticator->isAdmin();
        $account = $authenticator->GetAccount();

        if ($input->HasParam('group'))
        {
            if (($group = $input->GetNullParam('group',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS)) !== null)
            {
                $obj = Group::TryLoadByID($this->database, $group);
                if ($obj === null) throw new UnknownGroupException();
            }
            
            $class = $timed ? Limits\GroupTimed::class : Limits\GroupTotal::class;
            
            if (!$admin) throw new UnknownGroupException();            
        }
        else if ($input->HasParam('account'))
        {
            if (($account = $input->GetNullParam('account',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS)) !== null)
            {
                $obj = Account::TryLoadByID($this->database, $account);
                if ($obj === null) throw new UnknownAccountException();
            }
            
            $class = $timed ? Limits\AccountTimed::class : Limits\AccountTotal::class;
            
            if (!$admin && ($obj === null || $obj !== $account)) throw new UnknownAccountException();
        }
        else if ($input->HasParam('filesystem'))
        {
            if (($filesystem = $input->GetNullParam('filesystem',SafeParam::TYPE_RANDSTR,SafeParams::PARAMLOG_ALWAYS)) !== null)
            {
                $obj = FSManager::TryLoadByID($this->database, $filesystem);
                if ($obj === null) throw new UnknownFilesystemException();
            }
            
            $class = $timed ? Limits\FilesystemTimed::class : Limits\FilesystemTotal::class;
            
            if (!$admin && ($obj === null || ($obj->GetOwnerID() !== null && $obj->GetOwnerID() !== $account->ID()))) throw new UnknownFilesystemException();
        }
        else if ($allowAuto) 
        {
            $obj = $authenticator->GetAccount(); 
            $class = $timed ? Limits\AccountTimed::class : Limits\AccountTotal::class;
        }
        else throw new UnknownObjectException();
        
        if (!$allowMany && $obj === null) throw new UnknownObjectException();
        
        return array('obj' => $obj, 'class' => $class);
    }
    
    /**
     * Loads the total limit object or objects for the given objects
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL Limit | [id:Limit] client object
     * @see Limits\Total::GetClientObject()
     */
    protected function GetLimits(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $isadmin = $authenticator->isAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, true, true, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClient($this->database, $obj);
            return ($lim !== null) ? $lim->GetClientObject($isadmin) : null;
        }
        else
        {
            $count = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
            $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
            $lims = $class::LoadAll($this->database, $count, $offset);
            return array_map(function(Limits\Total $obj)use($isadmin){ 
                return $obj->GetClientObject($isadmin); },$lims);
        }
    }
    
    /**
     * Loads the timed limit object or objects for the given objects
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:Limit] client objects
     * @see Limits\Timed::GetClientObject()
     */
    protected function GetTimedLimits(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        $isadmin = $authenticator->isAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, true, true, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lims = $class::LoadAllForClient($this->database, $obj);
        }
        else
        {
            $count = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
            $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
            $lims = $class::LoadAll($this->database, $count, $offset);
        }

        return array_map(function(Limits\Timed $lim)use($isadmin){ 
            return $lim->GetClientObject($isadmin); }, array_values($lims));
    }
    
    /**
     * Returns all stored time stats for an object
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL [id:TimedStats]
     * @see Limits\TimedStats::GetClientObject()
     */
    protected function GetTimedStatsFor(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $obj = $this->GetLimitObject($input, $authenticator, true, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_UINT);
        $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
        
        if ($lim === null) return null;

        $count = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
        $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
        
        return array_map(function(Limits\TimedStats $stats){ return $stats->GetClientObject(); },
            Limits\TimedStats::LoadAllByLimit($this->database, $lim, $count, $offset));        
    }
    
    /**
     * Returns timed stats for the given object or objects at the given time
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return array|NULL TimedStats | [id:TimedStats]
     * @see Limits\TimedStats::GetClientObject()
     */
    protected function GetTimedStatsAt(Input $input, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $period = $input->GetParam('timeperiod',SafeParam::TYPE_UINT);
        $attime = $input->GetParam('matchtime',SafeParam::TYPE_UINT);
        
        $obj = $this->GetLimitObject($input, $authenticator, true, true, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
            if ($lim === null) return null;
            
            $stats = Limits\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
            return ($stats !== null) ? $stats->GetClientObject() : null;
        }
        else
        {
            $count = $input->GetOptNullParam('limit',SafeParam::TYPE_UINT);
            $offset = $input->GetOptNullParam('offset',SafeParam::TYPE_UINT);
            
            $retval = array(); 
            
            foreach ($class::LoadAllForPeriod($this->database, $period, $count, $offset) as $lim)
            {
                $stats = Limits\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
                if ($stats !== null) $retval[$lim->GetLimitedObject()->ID()] = $stats->GetClientObject();
            }
            
            return $retval;
        }   
    }

    /**
     * Configures total limits for the given object
     * @throws AuthenticationFailedException if not admin
     * @return array Limits
     * @see Limits\Total::GetClientObject()
     */
    protected function ConfigLimits(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, false, false, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $input)->GetClientObject();
    }    
    
    /**
     * Configures timed limits for the given object
     * @throws AuthenticationFailedException if not admin
     * @return array Limits
     * @see Limits\Timed::GetClientObject()
     */
    protected function ConfigTimedLimits(Input $input, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, false, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $input)->GetClientObject();
    }
    
    /**
     * Deletes all total limits for the given object
     * @throws AuthenticationFailedException if not admin
     */
    protected function PurgeLimits(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, false, false, false);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $class::DeleteByClient($this->database, $obj);
    }    
    
    /**
     * Deletes all timed limits for the given object
     * @throws AuthenticationFailedException if not admin
     */
    protected function PurgeTimedLimits(Input $input, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) throw new AuthenticationFailedException();
        
        $authenticator->RequireAdmin();
        
        $obj = $this->GetLimitObject($input, $authenticator, false, false, true);
        $class = $obj['class']; $obj = $obj['obj'];
        
        $period = $input->GetParam('period', SafeParam::TYPE_UINT);
        $class::DeleteClientAndPeriod($this->database, $obj, $period);
    }
}

