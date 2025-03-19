<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, BaseApp, Emailer, EmailRecipient};
use Andromeda\Core\IOFormat\{Input, InputPath, IOInterface, Output, OutputHandler, SafeParams};
use Andromeda\Core\IOFormat\Interfaces\HTTP;
use Andromeda\Core\Exceptions\UnknownActionException;

use Andromeda\Apps\Accounts\{Account, Group, Authenticator};
use Andromeda\Apps\Accounts\Resource\EmailContact;
use Andromeda\Apps\Accounts\Exceptions\{AuthenticationFailedException, AdminRequiredException, UnknownAccountException, UnknownGroupException};

use Andromeda\Apps\Files\Items\{Item, File, Folder, RootFolder, SubFolder};
use Andromeda\Apps\Files\Social\{Like, Share, Comment, Tag};
use Andromeda\Apps\Files\Storage\Storage;
use Andromeda\Apps\Files\Storage\Exceptions\FileWriteFailedException;

// when an account is deleted, need to delete files-related stuff also
/*Account::RegisterDeleteHandler(function(ObjectDatabase $database, Account $account)
{
    Storage::DeleteByAccount($database, $account);
    RootFolder::DeleteRootsByAccount($database, $account);
});

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init)
{ 
    if (!$init) // don't auto-encrypt
    {
        FTP::DecryptAccount($database, $account); 
        S3::DecryptAccount($database, $account); 
        SFTP::DecryptAccount($database, $account); 
        SMB::DecryptAccount($database, $account); 
    }
}*/ // TODO add storage account handlers

// TODO add policy account handlers

/**
 * App that provides user-facing filesystem services.
 * 
 * Provides a general filesystem API for managing files and folders,
 * as well as admin-level functions like managing storages and config.
 * 
 * Supports features like random-level byte access, multiple (user or admin-added)
 * filesystems with various backend storage drivers, social features including
 * likes and comments, sharing of content via links or to users or groups,
 * configurable rules per-account or per-storage, and granular statistics
 * gathering and limiting for accounts/groups/storages.
 */
class FilesApp extends BaseApp
{
    private Config $config;
    
    public function getName() : string { return 'files'; }
    
    public function getVersion() : string { return andromeda_version; }
    
    /** @return class-string<ActionLog> */
    public function getLogClass() : string { return ActionLog::class; }
    
    public function getUsage() : array 
    { 
        return array(
            'getconfig',
            'setconfig '.Config::GetSetConfigUsage(),
            '- AUTH for shared items: --sid id [--skey randstr] [--spassword raw]',
            'upload (--file% path [name] | --file- name) --parent id [--overwrite bool]',
            'download --file id [--fstart uint] [--flast int] [--debugdl bool]',
            'ftruncate --file id --size uint',
            'writefile (--data% path | --data-) --file id [--offset uint]',
            'createfolder --parent id --name fsname',
            'filemeta --file id [--details bool]',
            'getfolder [--folder id | --storage id] [--files bool] [--folders bool] [--recursive bool] [--limit ?uint] [--offset ?uint] [--details bool]',
            'getitembypath --path fspath [--folder id | --storage id] [--isfile bool]',
            'editfilemeta --file id [--description ?text]',
            'editfoldermeta --folder id [--description ?text]',
            'ownfile --file id',
            'ownfolder --folder id',

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
            'getfilelikes --file id [--limit ?uint] [--offset ?uint]',
            'getfolderlikes --folder id [--limit ?uint] [--offset ?uint]',
            'getfilecomments --file id [--limit ?uint] [--offset ?uint]',
            'getfoldercomments --folder id [--limit ?uint] [--offset ?uint]',

            'sharefile --file id (--link bool [--email email] | --account id | --group id) '.Share::GetSetOptionsUsage(),
            'sharefolder --folder id (--link bool [--email email] | --account id | --group id) '.Share::GetSetOptionsUsage(),
            'editshare --share id '.Share::GetSetOptionsUsage(),
            'deleteshare --share id',
            'shareinfo --sid id [--skey randstr] [--spassword raw]',
            'getshares [--mine bool]',
            'getadopted',

            'getstorage [--storage id] [--activate bool]',
            'getstorages [--everyone bool [--limit ?uint] [--offset ?uint]]',
            'createstorage '.Storage::GetCreateUsage(),
            ...array_map(function($u){ return "(createstorage...) $u"; }, Storage::GetCreateUsages()),
            'deletestorage --storage id --auth_password raw [--unlink bool]',
            'editstorage --storage id '.Storage::GetEditUsage(),
            ...array_map(function($u){ return "(editstorage...) $u"; }, Storage::GetEditUsages()),

            /*'getlimits [--account ?id | --group ?id | --storage ?id] [--limit ?uint] [--offset ?uint]',
            'gettimedlimits [--account ?id | --group ?id | --storage ?id] [--limit ?uint] [--offset ?uint]',
            'gettimedstatsfor [--account id | --group id | --storage id] --timeperiod uint [--limit ?uint] [--offset ?uint]',
            'gettimedstatsat (--account ?id | --group ?id | --storage ?id) --timeperiod uint --matchtime uint [--limit ?uint] [--offset ?uint]',
            'configlimits (--account id | --group id | --storage id) '.Policy\Total::BaseConfigUsage(),
            "(configlimits) --account id ".Policy\StandardAccount::GetConfigUsage(),
            "(configlimits) --group id ".Policy\StandardGroup::GetConfigUsage(),
            "(configlimits) --storage id ".Policy\StandardStorage::GetConfigUsage(),
            'configtimedlimits (--account id | --group id | --storage id) '.Policy\Timed::BaseConfigUsage(),
            "(configtimedlimits) --account id ".Policy\PeriodicAccount::GetConfigUsage(),
            "(configtimedlimits) --group id ".Policy\PeriodicGroup::GetConfigUsage(),
            "(configtimedlimits) --storage id ".Policy\PeriodicStorage::GetConfigUsage(),
            'purgelimits (--account id | --group id | --storage id)',
            'purgetimedlimits (--account id | --group id | --storage id) --period uint',*/
        );
    }
    
    public function __construct(ApiPackage $api)
    {
        parent::__construct($api);
        
        $this->config = Config::GetInstance($this->database);
    }

    public function commit() : void { Storage::commitAll($this->database); }
    public function rollback() : void { Storage::rollbackAll($this->database); }
    
    /**
     * {@inheritDoc}
     * @throws UnknownActionException if the given action is not valid
     * @see BaseApp::Run()
     */
    public function Run(Input $input) : mixed
    {
        $authenticator = Authenticator::TryAuthenticate(
            $this->database, $input, $this->API->GetInterface());

        $actionlog = null; if ($this->wantActionLog())
        {
            $actionlog = ActionLog::Create($this->database, $this->API->GetInterface(), $input);
            $actionlog->SetAuth($authenticator);
            $this->setActionLog($actionlog);
        }
        
        $params = $input->GetParams();
        switch($input->GetAction())
        {
            case 'getconfig': return $this->GetConfig($authenticator);
            case 'setconfig': return $this->SetConfig($params, $authenticator);
            
            case 'upload':     return $this->UploadFile($input, $authenticator, $actionlog);  
            case 'download':   $this->DownloadFile($params, $authenticator, $actionlog); return null;
            case 'ftruncate':  return $this->TruncateFile($params, $authenticator, $actionlog);
            case 'writefile':  return $this->WriteToFile($input, $authenticator, $actionlog);
            
            case 'createfolder':  return $this->CreateFolder($params, $authenticator, $actionlog);
            case 'filemeta':      return $this->GetFileMeta($params, $authenticator, $actionlog);
            case 'getfolder':     return $this->GetFolder($params, $authenticator, $actionlog);
            case 'getitembypath': return $this->GetItemByPath($params, $authenticator, $actionlog);
            case 'ownfile':   return $this->OwnFile($params, $authenticator, $actionlog);
            case 'ownfolder': return $this->OwnFolder($params, $authenticator, $actionlog);
            case 'editfilemeta':   return $this->EditFileMeta($params, $authenticator, $actionlog);
            case 'editfoldermeta': return $this->EditFolderMeta($params, $authenticator, $actionlog);
            // TODO ownfile/folder could be combined with edit metadata
           
            case 'deletefile':   $this->DeleteFile($params, $authenticator, $actionlog); return null;
            case 'deletefolder': $this->DeleteFolder($params, $authenticator, $actionlog); return null;
            case 'renamefile':   return $this->RenameFile($params, $authenticator, $actionlog);
            case 'renamefolder': return $this->RenameFolder($params, $authenticator, $actionlog);
            case 'movefile':     return $this->MoveFile($params, $authenticator, $actionlog);
            case 'movefolder':   return $this->MoveFolder($params, $authenticator, $actionlog);
            
            // TODO RAY most of these non-performance-critical ops can just be combined
            // to a single item operatino now that Item base load stuff works

            case 'likefile':      return $this->LikeFile($params, $authenticator, $actionlog);
            case 'likefolder':    return $this->LikeFolder($params, $authenticator, $actionlog);
            case 'tagfile':       return $this->TagFile($params, $authenticator, $actionlog);
            case 'tagfolder':     return $this->TagFolder($params, $authenticator, $actionlog);
            case 'deletetag':     $this->DeleteTag($params, $authenticator, $actionlog); return null;
            case 'commentfile':   return $this->CommentFile($params, $authenticator, $actionlog);
            case 'commentfolder': return $this->CommentFolder($params, $authenticator, $actionlog);
            case 'editcomment':   return $this->EditComment($params, $authenticator);
            case 'deletecomment': $this->DeleteComment($params, $authenticator, $actionlog); return null;
            case 'getfilelikes':   return $this->GetFileLikes($params, $authenticator, $actionlog);
            case 'getfolderlikes': return $this->GetFolderLikes($params, $authenticator, $actionlog);
            case 'getfilecomments':   return $this->GetFileComments($params, $authenticator, $actionlog);
            case 'getfoldercomments': return $this->GetFolderComments($params, $authenticator, $actionlog);
            // TODO RAY combine get likes and get comments into one action? or just use flags with getfolder/getfilemeta

            case 'sharefile':    return $this->ShareFile($params, $authenticator, $actionlog);
            case 'sharefolder':  return $this->ShareFolder($params, $authenticator, $actionlog);
            case 'editshare':    return $this->EditShare($params, $authenticator, $actionlog);
            case 'deleteshare':  $this->DeleteShare($params, $authenticator, $actionlog); return null;
            case 'shareinfo':    return $this->ShareInfo($params, $authenticator, $actionlog);
            case 'getshares':   return $this->GetShares($params, $authenticator);
            case 'getadopted':  return $this->GetAdopted($authenticator);
            
            case 'getstorage':  return $this->GetStorage($params, $authenticator, $actionlog);
            case 'getstorages': return $this->GetStorages($params, $authenticator);
            case 'createstorage': return $this->CreateStorage($input, $authenticator, $actionlog);
            case 'deletestorage': $this->DeleteStorage($params, $authenticator, $actionlog); return null;
            case 'editstorage':   return $this->EditStorage($input, $authenticator);
            
            //case 'getlimits':      return $this->GetLimits($params, $authenticator);
            //case 'gettimedlimits': return $this->GetTimedLimits($params, $authenticator);
            //case 'gettimedstatsfor': return $this->GetTimedStatsFor($params, $authenticator);
            //case 'gettimedstatsat':  return $this->GetTimedStatsAt($params, $authenticator);
            //case 'configlimits':      return $this->ConfigLimits($params, $authenticator);
            //case 'configtimedlimits': return $this->ConfigTimedLimits($params, $authenticator);
            //case 'purgelimits':      $this->PurgeLimits($params, $authenticator); return null;
            //case 'purgetimedlimits': $this->PurgeTimedLimits($params, $authenticator); return null; // LIMITS
            
            default: throw new UnknownActionException($input->GetAction());
        }
    }
    
    /** 
     * Throws an unknown file/folder exception if given, else item exception
     * @param ?class-string<Item> $class item class to load
     */
    private static function UnknownItemException(?string $class = null) : void
    {
        switch ($class)
        {
            case File::class: throw new Exceptions\UnknownFileException();
            case Folder::class: throw new Exceptions\UnknownFolderException();
            default: throw new Exceptions\UnknownItemException();
        }
    }
    
    /** 
     * Returns an ItemAccess authenticating the given item class/ID, throws exceptions on failure
     * @param class-string<Item> $class item class to load
     */
    private function AuthenticateItemAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, string $class, ?string $id) : ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            if ($item === null) self::UnknownItemException($class);
        }
        
        $access = ItemAccess::Authenticate($this->database, $params, $auth, $item); 

        if (!is_a($access->GetItem(), $class))
            self::UnknownItemException($class);
        
        if ($actionlog !== null)
            $actionlog->LogItemAccess($access->GetItem(), $access->TryGetShare()); 
        
        return $access;
    }
        
    /** 
     * Returns an ItemAccess authenticating the given item class/ID, returns null on failure 
     * @param class-string<Item> $class item class to load
     */
    private function TryAuthenticateItemAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, string $class, ?string $id) : ?ItemAccess 
    {
        $item = null; if ($id !== null)
        {
            $item = $class::TryLoadByID($this->database, $id);
            if ($item === null) self::UnknownItemException($class);
        }
        
        $access = ItemAccess::TryAuthenticate($this->database, $params, $auth, $item); 
        if ($access !== null && !is_a($access->GetItem(), $class)) return null;
        
        if ($actionlog !== null && $access !== null) 
            $actionlog->LogItemAccess($access->GetItem(), $access->TryGetShare());
        
        return $access;
    }
    
    /** Returns an ItemAccess authenticating the given already-loaded object */
    private function AuthenticateItemObjAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, Item $item) : ItemAccess
    {
        $access = ItemAccess::Authenticate($this->database, $params, $auth, $item);
        
        if ($actionlog !== null)
            $actionlog->LogItemAccess($access->GetItem(), $access->TryGetShare());
        
        return $access;
    }

    /** Returns an ItemAccess authenticating the given file ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFileAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, ?string $id = null) : ItemAccess 
    {
        $id ??= $params->GetParam('file',SafeParams::PARAMLOG_NEVER)->GetRandstr();
        return $this->AuthenticateItemAccess($params, $auth, $actionlog, File::class, $id);
    }

    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), throws exceptions on failure */
    private function AuthenticateFolderAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, ?string $id = null) : ItemAccess 
    { 
        $id ??= $params->GetParam('folder',SafeParams::PARAMLOG_NEVER)->GetRandstr();
        return $this->AuthenticateItemAccess($params, $auth, $actionlog, Folder::class, $id);
    }
        
    /** Returns an ItemAccess authenticating the given folder ID (or null to get from input), returns null on failure */
    private function TryAuthenticateFolderAccess(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog, ?string $id = null) : ?ItemAccess 
    {
        $id ??= $params->GetOptParam('folder',SafeParams::PARAMLOG_NEVER)->GetRandstr();
        return $this->TryAuthenticateItemAccess($params, $auth, $actionlog, Folder::class, $id);
    }

    /**
     * Gets config for this app
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function GetConfig(?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $admin = $authenticator !== null && $authenticator->isAdmin();
        
        return $this->config->GetClientObject($admin);
    }
    
    /**
     * Sets config for this app
     * @throws AdminRequiredException if not admin
     * @return array Config
     * @see Config::GetClientObject()
     */
    protected function SetConfig(SafeParams $params, ?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null)
            throw new AdminRequiredException();
        
        $authenticator->RequireAdmin();

        return $this->config->SetConfig($params)->GetClientObject(true);
    }
    
    /**
     * Uploads a new file to the given folder. Bandwidth is counted.
     * @throws AuthenticationFailedException if not signed in and public upload not allowed
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and share does not allow upload
     * @return array File newly created file
     * @see File::GetClientObject()
     */
    protected function UploadFile(Input $input, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $params = $input->GetParams();
        
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        $parentid = $params->HasParam('parent') ? $params->GetParam('parent',SafeParams::PARAMLOG_NEVER)->GetRandstr() : null;
        $paccess = $this->AuthenticateFolderAccess($params, $authenticator, $actionlog, $parentid, true); // TODO AuthenticateParentAccess
        $parent = $paccess->GetFolder(); $share = $paccess->TryGetShare();
        
        if ($authenticator === null && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        $overwrite = $params->GetOptParam('overwrite',false)->GetBool();
        
        if ($share !== null && (!$share->CanUpload() || ($overwrite && !$share->CanModify()))) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->TryGetOwner() : $account;
        
        $infile = $input->GetFile('file');
        if ($infile instanceof InputPath)
        {
            $fileobj = File::Import($this->database, $parent, $owner, $infile, $overwrite);
        }
        else // can't import handles directly
        {
            $name = $infile->GetName();
            $handle = $infile->GetHandle();
            
            $fileobj = File::Create($this->database, $parent, $owner, $name, $overwrite);
            
            FileUtils::ChunkedWrite($this->database, $handle, $fileobj, 0);
            fclose($handle);
        }
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('file',$fileobj->ID()); 
        
        return $fileobj->GetClientObject(($owner === $account));
    }
    
    /**
     * Downloads a file or part of a file
     * 
     * Can accept an input byte range. Also accepts the HTTP_RANGE header.
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and read is not allowed
     * @throws Exceptions\InvalidDLRangeException if the given byte range is invalid
     */
    protected function DownloadFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        // TODO CLIENT - since this is not AJAX, we might want to redirect to a page when doing a 404, etc. - better than plaintext - use appurl config
        
        $debugdl = $params->GetOptParam('debugdl',false)->GetBool() &&
            $this->API->GetDebugLevel(true) >= \Andromeda\Core\Config::ERRLOG_DETAILS;
        
        // debugdl disables file output printing and instead does a normal JSON return
        if (!$debugdl) $this->API->GetInterface()->SetOutputMode(IOInterface::OUTPUT_PLAIN);
        
        $access = $this->AuthenticateFileAccess($params, $authenticator, $actionlog); 
        $file = $access->GetFile(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanRead())
            throw new Exceptions\ItemAccessDeniedException();

        // first determine the byte range to read
        $fsize = $file->GetSize();
        $fstart = $params->GetOptParam('fstart',0,SafeParams::PARAMLOG_NEVER)->GetUint(); // logged below
        $flast  = $params->GetOptParam('flast',$fsize-1,SafeParams::PARAMLOG_NEVER)->GetInt(); // logged below
        
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if (is_string($range))
        {
            $ranges = explode('=',$range);
            if (count($ranges) !== 2 || trim($ranges[0]) !== "bytes")
                throw new Exceptions\InvalidDLRangeException();
            
            $ranges = explode('-',$ranges[1]);
            if (count($ranges) !== 2)
                throw new Exceptions\InvalidDLRangeException();
            
            $fstart = (int)$ranges[0];
            if ($ranges[1] !== "")
                $flast = (int)$ranges[1];
        }

        if ($fstart < 0 || $flast+1 < $fstart || $flast >= $fsize)
            throw new Exceptions\InvalidDLRangeException();

        if ($actionlog !== null) 
            $actionlog->LogDetails('fstart',$fstart)->LogDetails('flast',$flast);
        
        // check required bandwidth ahead of time
        $length = $flast-$fstart+1;
        assert($length >= 0); // see exception above
        $file->AssertBandwidth($length);

        if ($flast === $fsize-1) // the end of the file
            $file->CountDownload(($share !== null));
        
        // send necessary headers
        if (!$debugdl)
        {
            if ($fstart !== 0 || $flast !== $fsize-1)
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
        $this->API->GetInterface()->SetOutputHandler(new OutputHandler(
            function() use($debugdl,$length){ return $debugdl ? null : $length; },
            function(Output $output) use($file,$fstart,$length,$debugdl)
        {            
            set_time_limit(0); 
            ignore_user_abort(true);
            
            FileUtils::ChunkedRead($this->database,$file,$fstart,$length,$debugdl);
        }));
    }
        
    /**
     * Writes new data to an existing file - data is posted as a file
     * 
     * If no offset is given, the default is to append the file (offset = file size)
     * DO NOT use this in a multi-action transaction as the underlying FS cannot fully rollback writes.
     * The FS will restore the original size of the file but writes within the original size are permanent.
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws Exceptions\RandomWriteDisabledException if random write is not allowed on the file
     * @throws Exceptions\ItemAccessDeniedException if acessing via share and share doesn't allow modify
     * @return array File
     * @see File::GetClientObject()
     */
    protected function WriteToFile(Input $input, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $params = $input->GetParams();
        
        $access = $this->AuthenticateFileAccess($params, $authenticator, $actionlog);
        $file = $access->GetFile(); $share = $access->TryGetShare();
        
        $account = $authenticator?->GetAccount();

        $wstart = $params->GetOptParam('offset',$file->GetSize(),SafeParams::PARAMLOG_NEVER)->GetUint();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('wstart',$wstart);
        
        $infile = $input->GetFile('data');
        
        if ($account === null && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
            
        if ($share !== null && !$share->CanModify()) 
            throw new Exceptions\ItemAccessDeniedException();   

        if ($infile instanceof InputPath && ($wstart === 0) && $infile->GetSize() >= $file->GetSize())
        {
            // for a full overwrite, we can call SetContents for efficiency
            if ($share !== null && !$share->CanUpload())
                throw new Exceptions\ItemAccessDeniedException();
            
            $file->SetContents($infile);
            return $file->GetClientObject(($share === null));
        }
        else
        {
            // require randomWrite permission if not appending
            if ($wstart !== $file->GetSize() && !$file->GetAllowRandomWrite($account))
                throw new Exceptions\RandomWriteDisabledException();
            
            $handle = $infile->GetHandle();
            $wlength = FileUtils::ChunkedWrite($this->database, $handle, $file, $wstart); 
            fclose($handle);
            
            if ($infile instanceof InputPath && $wlength !== $infile->GetSize())
                throw new FileWriteFailedException();
            
            if ($actionlog !== null) 
                $actionlog->LogDetails('wlength',$wlength);
            
            return $file->GetClientObject(($share === null));
        }
    }

    /**
     * Truncates (resizes a file)
     * 
     * DO NOT use this in a multi-action transaction as the underlying FS cannot fully rollback truncates.
     * The FS will restore the original size of the file but if the file was shrunk, data will be zeroed.
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws Exceptions\RandomWriteDisabledException if random writes are not enabled on the file
     * @throws Exceptions\ItemAccessDeniedException if access via share and share does not allow modify
     * @return array File
     * @see File::GetClientObject()
     */
    protected function TruncateFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {        
        $access = $this->AuthenticateFileAccess($params, $authenticator, $actionlog);
        $file = $access->GetFile(); $share = $access->TryGetShare();

        $account = $authenticator?->GetAccount();
        
        if ($account === null && !$file->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if (!$file->GetAllowRandomWrite($account))
            throw new Exceptions\RandomWriteDisabledException();
            
        if ($share !== null && !$share->CanModify()) 
            throw new Exceptions\ItemAccessDeniedException();

        $file->SetSize($params->GetParam('size',SafeParams::PARAMLOG_ALWAYS)->GetUint()); 
        // TODO shouldn't really log this ... also remove logging of file write offsets, download offsets, etc. 
        
        return $file->GetClientObject(($share === null));
    }

    /**
     * Returns file metadata
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and reading is not allowed
     * @return array File
     * @see File::GetClientObject()
     */
    protected function GetFileMeta(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $access = $this->AuthenticateFileAccess($params, $authenticator, $actionlog);
        $file = $access->GetFile(); $share = $access->TryGetShare();

        if ($share !== null && !$share->CanRead()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $details = $params->GetOptParam('details',false)->GetBool();
        
        return $file->GetClientObject(($share === null), $details);
    }

    /**
     * Lists folder metadata and optionally the items in a folder (or storage root)
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and reading is not allowed
     * @throws AuthenticationFailedException if public access and no folder ID is given
     * @throws Exceptions\UnknownStorageException if the given storage does not exist
     * @throws Exceptions\UnknownFolderException if the given folder does not exist
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function GetFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $share = null;
        if ($params->HasParam('folder'))
        {
            $fid = $params->GetParam('folder',SafeParams::PARAMLOG_NEVER)->GetRandstr();
            $access = $this->AuthenticateFolderAccess($params, $authenticator, $actionlog, $fid);
            $folder = $access->GetFolder(); $share = $access->TryGetShare();
            
            if ($share !== null && !$share->CanRead()) 
                throw new Exceptions\ItemAccessDeniedException();
        }
        else
        {
            if ($authenticator === null) 
                throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();
            
            $storage = $params->HasParam('storage') ? $params->GetParam('storage')->GetRandstr() : null;
            
            if ($storage !== null)
            {
                $storage = Storage::TryLoadByAccountAndID($this->database, $account, $storage, true);  
                if ($storage === null) throw new Exceptions\UnknownStorageException();
            }
                
            $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $storage);
            
            if ($actionlog !== null && $folder !== null) 
                $actionlog->LogItemAccess($folder, null);
        }

        if ($folder === null) 
            throw new Exceptions\UnknownFolderException();
        
        $files = $params->GetOptParam('files',true)->GetBool();
        $folders = $params->GetOptParam('folders',true)->GetBool();
        $recursive = $params->GetOptParam('recursive',false)->GetBool();
        
        $limit = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
        
        $details = $params->GetOptParam('details',false)->GetBool();
        return $folder->GetClientObject(owner:($share === null),details:$details,
            files:$files,folders:$folders,recursive:$recursive,limit:$limit,offset:$offset);
    }

    /**
     * Reads an item by a path (rather than by ID) - can specify a root folder or storage
     * 
     * NOTE that /. and /.. have no special meaning - no traversal allowed
     * @throws Exceptions\ItemAccessDeniedException if access via share and read is not allowed
     * @throws AuthenticationFailedException if public access and no root is given
     * @throws Exceptions\UnknownStorageException if the given storage is not found
     * @throws Exceptions\UnknownFolderException if the given folder is not found
     * @throws Exceptions\UnknownItemException if the given item path is invalid
     * @return array File|Folder with {isfile:bool}
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function GetItemByPath(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $share = null; if (($raccess = $this->TryAuthenticateFolderAccess($params, $authenticator, $actionlog)) !== null)
        {
            $folder = $raccess->GetFolder(); $share = $raccess->TryGetShare();
            if ($share !== null && !$share->CanRead()) 
                throw new Exceptions\ItemAccessDeniedException();
        }
        else // no root folder given
        {
            if ($authenticator === null) 
                throw new AuthenticationFailedException();
            $account = $authenticator->GetAccount();

            $storage = $params->HasParam('storage') ? $params->GetParam('storage')->GetRandstr() : null;
            
            if ($storage !== null)
            {
                $storage = Storage::TryLoadByID($this->database, $storage);
                if ($storage === null) throw new Exceptions\UnknownStorageException();
            }
            
            $folder = RootFolder::GetRootByAccountAndFS($this->database, $account, $storage);

            if ($actionlog !== null && $folder !== null) 
                $actionlog->LogItemAccess($folder, null);
        }        
        
        if ($folder === null) 
            throw new Exceptions\UnknownFolderException();
        
        $path = $params->GetParam('path')->GetFSPath();
        $path = array_filter(explode('/',$path)); $name = array_pop($path);

        foreach ($path as $subfolder) // TODO specifically disallow . and ..
        {
            $subfolder = Folder::TryLoadByParentAndName($this->database, $folder, $subfolder);
            
            if ($subfolder === null) 
                throw new Exceptions\UnknownFolderException();
            else $folder = $subfolder;
        }
        
        $item = null;
        $isfile = $params->HasParam('isfile') ? $params->GetParam('isfile')->GetBool() : null;
        // TODO can just load item here and get rid of --isfile input
        
        if ($name === null) 
        {
            if ($isfile === true)
                throw new Exceptions\UnknownItemException();
            else $item = $folder; // trailing / for folder
        }
        else
        {
            if ($isfile !== false)
                $item = File::TryLoadByParentAndName($this->database, $folder, $name);
            if ($isfile !== true && $item === null)
                $item = Folder::TryLoadByParentAndName($this->database, $folder, $name);
        }
        
        if ($item === null) 
            throw new Exceptions\UnknownItemException();

        if ($item instanceof File) 
        {
            $retval = $item->GetClientObject(owner:($share === null));
        }
        else if ($item instanceof Folder) // @phpstan-ignore-line remove as per below comment // TODO
        {
            $retval = $item->GetClientObject(owner:($share === null),details:false,files:true,folders:true);
        }
        // TODO make folder client object default files/folders to true, just use Item->GetClientObject here
        
        $retval['isfile'] = ($item instanceof File);
        return $retval;
    }
    
    /**
     * Edits file metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFileMeta(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->EditItemMeta($this->AuthenticateFileAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Edits folder metadata
     * @see FilesApp::EditItemMeta()
     */
    protected function EditFolderMeta(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->EditItemMeta($this->AuthenticateFolderAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Edits item metadata
     * @param ItemAccess $access access object for item
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and can't modify
     * @return array Item
     * @see Item::GetClientObject()
     */
    private function EditItemMeta(ItemAccess $access, SafeParams $params) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanModify()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        if ($params->HasParam('description')) 
            $item->SetDescription($params->GetParam('description')->GetNullHTMLText());
        
        return $item->GetClientObject(($share === null));
    }    
    
    /**
     * Takes ownership of a file
     * @return array File
     * @see File::GetClientObject()
     */
    protected function OwnFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $params->GetParam('file',SafeParams::PARAMLOG_NEVER)->GetRandstr();
        
        $file = File::TryLoadByID($this->database, $id);
        if ($file === null) throw new Exceptions\UnknownFileException();
        
        if ($file->isWorldAccess() || $file->GetParent()->TryGetOwner() !== $account)
            throw new Exceptions\ItemAccessDeniedException();
            
        if ($actionlog !== null) 
            $actionlog->LogItemAccess($file, null);
            
        $file->SetOwner($account);
        return $file->GetClientObject(true);
    }
    
    /**
     * Takes ownership of a folder
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function OwnFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $params->GetParam('folder',SafeParams::PARAMLOG_NEVER)->GetRandstr();
        
        $folder = Folder::TryLoadByID($this->database, $id);
        if ($folder === null) throw new Exceptions\UnknownFolderException();
        
        if ($folder->isWorldAccess()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        if ($actionlog !== null) 
            $actionlog->LogItemAccess($folder, null);
        
        $parent = $folder->TryGetParent();
        if ($parent === null || $parent->TryGetOwner() !== $account)
            throw new Exceptions\ItemAccessDeniedException();
            
        if ($params->GetOptParam('recursive',false)->GetBool())
            $folder->SetOwnerRecursive($account);
        else $folder->SetOwner($account); // TODO use a bool for recursive instead?

        return $folder->GetClientObject(true);
    }    

    /**
     * Creates a folder in the given parent
     * @throws AuthenticationFailedException if public access and public upload not allowed
     * @throws Exceptions\ItemAccessDeniedException if accessing via share and share upload not allowed
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function CreateFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        $parentid = $params->HasParam('parent') ? $params->GetParam('parent',SafeParams::PARAMLOG_NEVER)->GetRandstr() : null;
        $access = $this->AuthenticateFolderAccess($params, $authenticator, $actionlog, $parentid, true); // TODO AuthenticateParentAccess
        $parent = $access->GetFolder(); $share = $access->TryGetShare();
        
        if ($authenticator === null && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanUpload()) 
            throw new Exceptions\ItemAccessDeniedException();

        $name = $params->GetParam('name')->GetFSName();
        
        $owner = ($share !== null && !$share->KeepOwner()) ? $parent->TryGetOwner() : $account;

        $folder = SubFolder::Create($this->database, $parent, $owner, $name);
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('folder',$folder->ID()); 
        
        return $folder->GetClientObject(($owner === $account));
    }
    
    /**
     * Deletes a file
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        $this->DeleteItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /**
     * Deletes a folder
     * @see FilesApp::DeleteItem()
     */
    protected function DeleteFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        $this->DeleteItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Deletes an item.
     * 
     * DO NOT use this in a multi-action transaction as the underlying FS cannot rollback deletes.
     * If you delete an item and then do another action that results in an error, the content
     * will still be deleted on disk though the database objects will remain.
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify is not allowed
     * @throws Exceptions\ItemAccessDeniedException if access via share and share modify is not allowed
     */
    private function DeleteItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {        
        $item = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();

        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($authenticator === null && !$itemobj->GetAllowPublicModify())
            throw new AuthenticationFailedException();
        
        if ($share !== null && !$share->CanModify())
            throw new Exceptions\ItemAccessDeniedException();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('item', $itemobj->TryGetClientObject(), onlyFull:true);

        $itemobj->Delete();
    }
    
    /**
     * Renames (or copies) a file
     * @see FilesApp::RenameItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function RenameFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->RenameItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /**
     * Renames (or copies) a folder
     * @see FilesApp::RenameItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function RenameFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->RenameItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Renames or copies an item
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws Exceptions\ItemAccessDeniedException if access via share and share upload/modify is not allowed
     * @throws AuthenticationFailedException if public access and public upload/modify is not allowed
     */
    private function RenameItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $copy = $params->GetOptParam('copy',false)->GetBool();

        $id = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();
        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $id);
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();
        
        if ($item instanceof RootFolder && ($account === null || !$item->isFSOwnedBy($account)))
            throw new Exceptions\ItemAccessDeniedException();
        
        $name = $params->GetParam('name')->GetFSName();
        $overwrite = $params->GetOptParam('overwrite',false)->GetBool();
        
        $paccess = $this->AuthenticateItemObjAccess($params, $authenticator, $actionlog, $item->GetParent(), true); // TODO AuthenticateParentAccess
        
        $parent = $paccess->GetFolder(); $pshare = $paccess->TryGetShare();
        
        if ($copy)
        {
            if ($authenticator === null && !$parent->GetAllowPublicUpload())
                throw new AuthenticationFailedException();
            
            if ($pshare !== null && !$pshare->CanUpload())
                throw new Exceptions\ItemAccessDeniedException();
            
            $owner = ($share !== null && !$share->KeepOwner()) ? $parent->TryGetOwner() : $account;
            
            $retitem = $item->CopyToName($owner, $name, $overwrite);
            return $retitem->GetClientObject(owner:($share === null));
        }
        else
        {
            if ($authenticator === null && !$parent->GetAllowPublicModify())
                throw new AuthenticationFailedException();
            
            if ($share !== null && !$share->CanModify()) 
                throw new Exceptions\ItemAccessDeniedException();
            
            $item->SetName($name, $overwrite);
            return $item->GetClientObject(owner:($share === null));
        }
    }
    
    /**
     * Moves (or copies) a file
     * @see FilesApp::MoveItem()
     * @return array File
     * @see File::GetClientObject()
     */
    protected function MoveFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->MoveItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /**
     * Moves (or copies) a folder
     * @see FilesApp::MoveItem()
     * @return array Folder
     * @see Folder::GetClientObject()
     */
    protected function MoveFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->MoveItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Moves or copies an item.
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws Exceptions\ItemAccessDeniedException if access via share and share modify/upload not allowed
     */
    private function MoveItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $copy = $params->GetOptParam('copy',false)->GetBool();
        
        $itemid = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();
        
        $parentid = $params->HasParam('parent') ? $params->GetParam('parent',SafeParams::PARAMLOG_NEVER)->GetRandstr() : null;
        $paccess = $this->AuthenticateFolderAccess($params, $authenticator, $actionlog, $parentid, true); // TODO AuthenticateParentAccess
        $parent = $paccess->GetFolder(); $pshare = $paccess->TryGetShare();
        
        if ($authenticator === null && !$parent->GetAllowPublicUpload())
            throw new AuthenticationFailedException();
            
        if ($pshare !== null && !$pshare->CanUpload()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $overwrite = $params->GetOptParam('overwrite',false)->GetBool();
        $account = ($authenticator === null) ? null : $authenticator->GetAccount();

        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $itemid);
        $item = $access->GetItem(); $share = $access->TryGetShare();

        if ($copy)
        {
            $owner = ($share !== null && !$share->KeepOwner()) ? $parent->TryGetOwner() : $account;
            
            $newitem = $item->CopyToParent($owner, $parent, $overwrite);
            return $newitem->GetClientObject(owner:($owner === $account));
        }
        else 
        {
            if ($authenticator === null && !$item->GetAllowPublicModify())
                throw new AuthenticationFailedException();
            
            if ($share !== null && !$share->CanModify()) 
                throw new Exceptions\ItemAccessDeniedException();
            
            $owner = $item->TryGetOwner();
            $item->SetParent($parent, $overwrite);
            return $item->GetClientObject(owner:($owner === $account));
        }
    }
    
    /** 
     * Likes or dislikes a file 
     * @see FilesApp::LikeItem()
     */
    protected function LikeFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : ?array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->LikeItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /** 
     * Likes or dislikes a folder
     * @see FilesApp::LikeItem()
     */
    protected function LikeFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : ?array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->LikeItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Likes or dislikes an item
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\ItemAccessDeniedException if access via share if social is not allowed
     * @return array ?Like
     * @see Like::GetClientObject()
     */
    private function LikeItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : ?array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();
        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $id);
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanSocial()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $value = $params->GetOptParam('value',true)->GetNullBool();
        
        $like = Like::CreateOrUpdate($this->database, $account, $item, $value);
        
        return ($like !== null) ? $like->GetClientObject() : null;
    }
    
    /** 
     * Adds a tag to a file
     * @see FilesApp::TagItem()
     */
    protected function TagFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->TagItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /** 
     * Adds a tag to a folder
     * @see FilesApp::TagItem() 
     */
    protected function TagFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->TagItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Adds a tag to an item
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\ItemAccessDeniedException if access via share and share modify is not allowed
     * @return array Tag
     * @see Tag::GetClientObject()
     */
    private function TagItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $tag = $params->GetParam('tag')->CheckLength(127)->GetAlphanum();
        $item = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();

        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $item);
        $itemobj = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanModify()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $tagobj = Tag::Create($this->database, $account, $itemobj, $tag);
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('tag',$tagobj->ID()); 
        
        return $tagobj->GetClientObject();
    }
    
    /**
     * Deletes an item tag
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownItemException if the given tag is not found
     * @throws Exceptions\ItemAccessDeniedException if access via share and share modify is not allowed
     */
    protected function DeleteTag(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $id = $params->GetParam('tag',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        $tag = Tag::TryLoadByID($this->database, $id);
        if ($tag === null) throw new Exceptions\UnknownItemException();

        $access = $this->AuthenticateItemObjAccess($params, $authenticator, $actionlog, $tag->GetItem());
        
        $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanModify()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('tag', $tag->GetClientObject(), onlyFull:true);
        
        $tag->Delete();
    }
    
    /**
     * Adds a comment to a file
     * @see FilesApp::CommentItem()
     */
    protected function CommentFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->CommentItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /**
     * Adds a comment to a folder
     * @see FilesApp::CommentFolder()
     */
    protected function CommentFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->CommentItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Adds a comment to an item
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\ItemAccessDeniedException if access via share and share social is not allowed
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function CommentItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();
        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $id);
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanSocial()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $comment = $params->GetParam('comment')->GetHTMLText();
        $cobj = Comment::Create($this->database, $account, $item, $comment);
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('comment',$cobj->ID()); 
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Edits an existing comment properties
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownItemException if the comment is not found
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    protected function EditComment(SafeParams $params, ?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
                
        $id = $params->GetParam('commentid',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new Exceptions\UnknownItemException();
        
        if ($params->HasParam('comment')) 
            $cobj->SetComment($params->GetParam('comment')->GetHTMLText());
        
        return $cobj->GetClientObject();
    }
    
    /**
     * Deletes a comment
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownItemException if the comment is not found
     */
    protected function DeleteComment(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $id = $params->GetParam('commentid',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        $cobj = Comment::TryLoadByAccountAndID($this->database, $account, $id);
        if ($cobj === null) throw new Exceptions\UnknownItemException();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('comment', $cobj->GetClientObject(), onlyFull:true);
        
        $cobj->Delete();
    }
    
    /**
     * Returns comments on a file
     * @see FilesApp::GetItemComments()
     */
    protected function GetFileComments(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->GetItemComments($this->AuthenticateFileAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Returns comments on a folder
     * @see FilesApp::GetItemComments()
     */
    protected function GetFolderComments(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->GetItemComments($this->AuthenticateFolderAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Returns comments on an item
     * @param ItemAccess $access file or folder access object
     * @throws Exceptions\ItemAccessDeniedException if access via share and can't read
     * @return array Comment
     * @see Comment::GetClientObject()
     */
    private function GetItemComments(ItemAccess $access, SafeParams $params) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanRead()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $limit = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
        
        $comments = $item->GetComments($limit, $offset);

        return array_map(function(Comment $c){ return $c->GetClientObject(); }, $comments);
    }
    
    /**
     * Returns likes on a file
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFileLikes(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->GetItemLikes($this->AuthenticateFileAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Returns likes on a folder
     * @see FilesApp::GetItemLikes()
     */
    protected function GetFolderLikes(SafeParams $params, ?Authenticator $auth, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->GetItemLikes($this->AuthenticateFolderAccess($params, $auth, $actionlog), $params);
    }
    
    /**
     * Returns likes on an item
     * @param ItemAccess $access file or folder access object
     * @throws Exceptions\ItemAccessDeniedException if access via share and can't read
     * @return array Like
     * @see Like::GetClientObject()
     */
    private function GetItemLikes(ItemAccess $access, SafeParams $params) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $item = $access->GetItem(); $share = $access->TryGetShare();
        
        if ($share !== null && !$share->CanRead()) 
            throw new Exceptions\ItemAccessDeniedException();
        
        $limit = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
    
        $likes = $item->GetLikes($limit, $offset);
        
        return array_map(function(Like $c){ return $c->GetClientObject(); }, $likes);
    }

    // TODO seems like comments, likes, etc. should be combined into just a file get metadata function
    
    /**
     * Creates shares for a file
     * @see FilesApp::ShareItem()
     */
    protected function ShareFile(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->ShareItem(File::class, 'file', $params, $authenticator, $actionlog);
    }
    
    /**
     * Creates shares for a folder
     * @see FilesApp::ShareItem()
     */
    protected function ShareFolder(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        return $this->ShareItem(Folder::class, 'folder', $params, $authenticator, $actionlog);
    }
    
    /**
     * Creates shares for an item
     * @param class-string<Item> $class item class
     * @param string $key input param for a single item
     * @throws AuthenticationFailedException if public access and public modify/upload not allowed
     * @throws UnknownAccountException if sharing to an account and it's not found
     * @throws UnknownGroupException if sharing to a group and it's not found
     * @throws Exceptions\UnknownItemException if the given item to share is not found
     * @throws Exceptions\EmailShareDisabledException if emailing shares is not enabled
     * @throws Exceptions\ShareURLGenerateException if the URL to email could be not determined
     * @return array Share
     * @see Share::GetClientObject()
     */
    private function ShareItem(string $class, string $key, SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $item = $params->GetParam($key,SafeParams::PARAMLOG_NEVER)->GetRandstr();
        $access = self::AuthenticateItemAccess($params, $authenticator, $actionlog, $class, $item);
        
        $oldshare = $access->TryGetShare(); $item = $access->GetItem();
        if ($oldshare !== null && !$oldshare->CanReshare())
            throw new Exceptions\ItemAccessDeniedException();
        
        if (!$item->GetAllowItemSharing($account))
            throw new Exceptions\ItemSharingDisabledException();
        
        $islink = $params->GetOptParam('link',false)->GetBool();
        
        if ($islink) $share = Share::CreateLink($this->database, $account, $item);
        else
        {
            if ($params->HasParam('account'))
            {
                $acctid = $params->GetParam('account')->GetRandstr();
                
                if (($dest = Account::TryLoadByID($this->database, $acctid)) === null)
                    throw new UnknownAccountException();
            }
            else if ($params->HasParam('group'))
            {
                $groupid = $params->GetParam('group')->GetRandstr();
                
                if (!$item->GetAllowShareToGroups($account))
                    throw new Exceptions\ShareTargetDisabledException();
                    
                if (($dest = Group::TryLoadByID($this->database, $groupid)) === null)
                    throw new UnknownGroupException();
            }
            else throw new Exceptions\InvalidShareTargetException();
            
            $share = Share::Create($this->database, $account, $item, $dest);
        }
        
        $share->SetOptions($params, $oldshare);
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('share',$share->ID()); 
        
        $shares = array($share); // maybe in future, send N items with 1 email?
        $retval = $share->GetClientObject(false, true, $islink);
        
        if ($islink && $params->HasParam('email'))
        {
            if (!Policy\StandardAccount::ForceLoadByAccount($this->database, $account)->GetAllowEmailShare())
                throw new Exceptions\EmailShareDisabledException();
            
            $email = $params->GetParam('email')->GetEmail();
                
            $account = $authenticator->GetAccount();
            $subject = $account->GetDisplayName()." shared files with you"; 
            
            $body = implode("<br />",array_map(function(Share $share)
            {                
                $url = $this->config->GetApiUrl();
                if ($url === null || $url === "") 
                    throw new Exceptions\ShareURLGenerateException();
                
                $cmdparams = (new SafeParams())->AddParam('sid',$share->ID())->AddParam('skey',$share->GetAuthKey()); // @phpstan-ignore-line make public or use GetClientObject?
                $cmdinput = (new Input('files','download',$cmdparams));
                
                return "<a href='".HTTP::GetRemoteURL($url, $cmdinput)."'>".$share->GetItem()->GetName()."</a>";
            }, $shares)); 
            
            // TODO CLIENT - param for the client to have the URL point at the client
            // TODO CLIENT - HTML - configure a directory where client templates reside

            $from = EmailContact::TryLoadFromByAccount($this->database, $account)?->GetAsEmailRecipient();

            Emailer::LoadAny($this->database)->SendMail($subject, $body, isHtml:true,
                recipients:array(new EmailRecipient($email)), usebcc:false, from:$from);
        }
        
        return $retval;
    }    

    /**
     * Edits properties of an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownItemException if the given share is not found
     * @throws Exceptions\ItemAccessDeniedException if not allowed
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function EditShare(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $share = Share::TryLoadByID($this->database, 
            $params->GetParam('share',SafeParams::PARAMLOG_ALWAYS)->GetRandstr());
        if ($share === null) throw new Exceptions\UnknownItemException();        
        
        // allowed to edit the share if you have owner level access to the item, or own the share
        $origshare = $this->AuthenticateItemObjAccess($params, $authenticator, $actionlog, $share->GetItem())->TryGetShare();
        if ($origshare !== null && $share->GetOwner() !== $account)
            throw new Exceptions\ItemAccessDeniedException();
        
        $share->SetOptions($params, $origshare);
        return $share->GetClientObject();
    }
    
    /**
     * Deletes an existing share
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownItemException if the given share is not found
     * @throws Exceptions\ItemAccessDeniedException if not allowed
     */
    protected function DeleteShare(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $share = Share::TryLoadByID($this->database,
            $params->GetParam('share',SafeParams::PARAMLOG_ALWAYS)->GetRandstr());
        if ($share === null) throw new Exceptions\UnknownItemException();

        // if you don't own the share, you must have owner-level access to the item        
        if ($share->GetOwner() !== $account)
        {
            $access = $this->AuthenticateItemObjAccess($params, $authenticator, $actionlog, $share->GetItem());
            if ($access->TryGetShare() !== null)
                throw new Exceptions\ItemAccessDeniedException();
        }
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('share', $share->GetClientObject(), onlyFull:true);
        
        $share->Delete();
    }
    
    /**
     * Retrieves metadata on a share object (from a link)
     * @return array Share
     * @see Share::GetClientObject()
     */
    protected function ShareInfo(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $access = ItemAccess::Authenticate($this->database, $params, $authenticator, null);
        
        if ($actionlog !== null) 
            $actionlog->LogItemAccess($access->GetItem(), $access->TryGetShare());
        
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
    protected function GetShares(SafeParams $params, ?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $mine = $params->GetOptParam('mine',false)->GetBool();
        
        if ($mine) $shares = Share::LoadByAccountOwner($this->database, $account);
        else $shares = Share::LoadByAccountDest($this->database, $account);
        
        if (!$mine) $shares = array_filter($shares, 
            function(Share $sh){ return !$sh->isExpired(); });
        
        return array_map(function($share)use($mine){ 
            return $share->GetClientObject(true, $mine); }, $shares);
    }
    
    /**
     * Returns a list of all items where the user owns the item but not the parent
     * 
     * These are items that the user uploaded into someone else's folder, but owns
     * @throws AuthenticationFailedException if not signed in 
     * @return array<mixed> `{files:[id:File],folders:[id:Folder]}`
     * @see File::GetClientObject()
     * @see Folder::GetClientObject()
     */
    protected function GetAdopted(?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $files = File::LoadAdoptedByOwner($this->database, $account);
        $folders = Folder::LoadAdoptedByOwner($this->database, $account);
        
        $files = array_map(function(File $file){ return $file->GetClientObject(true); }, $files);
        $folders = array_map(function(Folder $folder){ return $folder->GetClientObject(true); }, $folders);
        
        return array('files'=>$files, 'folders'=>$folders);
    }
    
    /**
     * Returns storage metadata (default if none specified)
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownStorageException if no storage was specified or is the default
     * @return array Storage
     * @see Storage::GetClientObject()
     */
    protected function GetStorage(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        if ($params->HasParam('storage'))
        {
            $storage = Storage::TryLoadByID($this->database, 
                $params->GetParam('storage',SafeParams::PARAMLOG_NEVER)->GetRandstr()); // logged below
        }
        else $storage = Storage::LoadDefaultByAccount($this->database, $account);
        
        if ($storage === null) 
            throw new Exceptions\UnknownStorageException();

        if ($actionlog !== null) 
            $actionlog->LogDetails('storage',$storage->ID());
        
        $ispriv = $authenticator->isAdmin() || ($account === $storage->TryGetOwner());
        
        $activate = $params->GetOptParam('activate',false)->GetBool();
        
        return [];//$storage->GetClientObject($ispriv, $activate);// TODO RAY !! GetClientObject
    }
    
    /**
     * Returns a list of all storages available
     * @throws AuthenticationFailedException if not signed in
     * @return array [id:Storage]
     * @see Storage::GetClientObject()
     */
    protected function GetStorages(SafeParams $params, ?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();

        if ($params->GetOptParam('everyone',false)->GetBool())
        {
            $authenticator->RequireAdmin();
            
            $limit = $params->GetOptParam('limit',null)->GetNullUint();
            $offset = $params->GetOptParam('offset',null)->GetNullUint();
            
            $storages = Storage::LoadAll($this->database, $limit, $offset);
        }
        else $storages = Storage::LoadByAccount($this->database, $account);
        
        return array_map(function($storage){ 
            return $storage->GetClientObject(); }, $storages);
    }
    
    /**
     * Creates a new storage
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UserStorageDisabledException if not admin and user storage is not allowed
     * @return array Storage
     * @see Storage::GetClientObject()
     */
    protected function CreateStorage(Input $input, ?Authenticator $authenticator, ?ActionLog $actionlog) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $params = $input->GetParams();
        
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $global = $params->GetOptParam('global',false)->GetBool();
        if ($global) $authenticator->RequireAdmin();

        if (!Policy\StandardAccount::ForceLoadByAccount($this->database, $account)->GetAllowUserStorage() && !$global)
            throw new Exceptions\UserStorageDisabledException();
            
        $storage = Storage::Create($this->database, $input, $global ? null : $account);
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('storage',$storage->ID()); 
        
        return $storage->GetClientObject(true);
    }

    /**
     * Edits an existing storage
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownStorageException if the given storage is not found
     * @return array Storage
     * @see Storage::GetClientObject()
     */
    protected function EditStorage(Input $input, ?Authenticator $authenticator) : array // @phpstan-ignore-line TODO GetClientObject
    {
        $params = $input->GetParams();
        
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        $account = $authenticator->GetAccount();
        
        $fsid = $params->GetParam('storage',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        if ($authenticator->isAdmin())
            $storage = Storage::TryLoadByID($this->database, $fsid);
        else $storage = Storage::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        if ($storage === null) 
            throw new Exceptions\UnknownStorageException();

        return $storage->Edit($input)->GetClientObject(true);
    }

    /**
     * Removes a storage (and potentially its content)
     * @throws AuthenticationFailedException if not signed in
     * @throws Exceptions\UnknownStorageException if the given storage is not found
     */
    protected function DeleteStorage(SafeParams $params, ?Authenticator $authenticator, ?ActionLog $actionlog) : void
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $authenticator->RequirePassword();
        $account = $authenticator->GetAccount();
        
        $fsid = $params->GetParam('storage',SafeParams::PARAMLOG_ALWAYS)->GetRandstr();
        
        if ($authenticator->isAdmin())
            $storage = Storage::TryLoadByID($this->database, $fsid);
        else $storage = Storage::TryLoadByAccountAndID($this->database, $account, $fsid);
        
        if ($storage === null)
            throw new Exceptions\UnknownStorageException();
        
        $unlink = $params->GetOptParam('unlink',false)->GetBool();
        
        if ($actionlog !== null) 
            $actionlog->LogDetails('storage', $storage->GetClientObject(true), onlyFull:true);
        
        $storage->Delete();// TODO RAY !! $unlink);
    }
    





    /**
     * Common function for loading and authenticating the limited object and limit class referred to by input
     * 
     * Groups can be viewed only by admin.  Accounts can be viewed by admin (any) or users (their own - not full)
     * Filesystems can be viewed by admin (any) or users (their own - full, or global - not full)
     * @param bool $allowAuto if true, return the current account if no object is specified
     * @param bool $allowMany if true, allow selecting all of the given type
     * @param bool $timed if true, return a timed limit class (not total)
     * @throws UnknownGroupException if the given group is not found
     * @throws UnknownAccountException if the given account is not found
     * @throws Exceptions\UnknownStorageException if the given storage is not found
     * @throws Exceptions\UnknownObjectException if nothing valid was specified
     * @return array<mixed> `{class:string, obj:object, full:bool}`
     */
    /*private function GetLimitObject(SafeParams $params, ?Authenticator $authenticator, bool $allowAuto, bool $allowMany, bool $timed) : array // TODO fix limits functions
    {
        $obj = null; $admin = $authenticator->isAdmin();

        if ($params->HasParam('group'))
        {
            if (($group = $params->GetParam('group',SafeParams::PARAMLOG_ALWAYS)->GetNullRandstr()) !== null)
            {
                $obj = Group::TryLoadByID($this->database, $group);
                if ($obj === null) throw new UnknownGroupException();
            }
            
            $class = $timed ? Policy\PeriodicGroup::class : Policy\StandardGroup::class; 
            
            $full = true;
            if (!$admin) throw new UnknownGroupException();
        }
        else if ($params->HasParam('account'))
        {
            if (($account = $params->GetParam('account',SafeParams::PARAMLOG_ALWAYS)->GetNullRandstr()) !== null)
            {
                $obj = Account::TryLoadByID($this->database, $account);
                if ($obj === null) throw new UnknownAccountException();
            }
            
            $class = $timed ? Policy\PeriodicAccount::class : Policy\StandardAccount::class;

            $full = $admin; if (!$admin && $obj !== $authenticator->GetAccount()) 
                throw new UnknownAccountException();
        }
        else if ($params->HasParam('storage'))
        {
            if (($storage = $params->GetParam('storage',SafeParams::PARAMLOG_ALWAYS)->GetNullRandstr()) !== null)
            {
                $obj = Storage::TryLoadByID($this->database, $storage);
                if ($obj === null) throw new Exceptions\UnknownStorageException();
            }
            
            $class = $timed ? Policy\PeriodicStorage::class : Policy\StandardStorage::class;
            
            $full = $admin || ($obj->GetOwnerID() === $authenticator->GetAccount()->ID());
            
            // non-admins can view a subset of total info (feature config) for global storages
            if (!$full && ($timed || ($obj->GetOwnerID() !== null))) 
                throw new Exceptions\UnknownStorageException();
        }
        else if ($allowAuto) 
        {
            $obj = $authenticator->GetAccount(); $full = $admin;
            
            $class = $timed ? Policy\PeriodicAccount::class : Policy\StandardAccount::class;
        }
        else throw new Exceptions\UnknownObjectException();
        
        // a null flag means admin wants to see all of that category
        if ($obj === null && (!$allowMany || !$admin))
            throw new Exceptions\UnknownObjectException();
        
        return array('obj'=>$obj, 'class'=>$class, 'full'=>$full);
    }*/ // LIMITS
    
    /**
     * Loads the total limit object or objects for the given objects
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return ?array Limit | [Limit] client object
     * @see FilesApp::GetLimitObject()
     * @see Policy\Total::GetClientObject()
     */
    /*protected function GetLimits(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $lobj = $this->GetLimitObject($params, $authenticator, true, true, false);
        $class = $lobj['class']; $obj = $lobj['obj']; $full = $lobj['full'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClient($this->database, $obj);
            return ($lim !== null) ? $lim->GetClientObject($full) : null;
        }
        else
        {
            $count = $params->GetOptParam('limit',null)->GetNullUint();
            $offset = $params->GetOptParam('offset',null)->GetNullUint();
            $lims = $class::LoadAll($this->database, $count, $offset);
            
            return array_map(function(Policy\Total $obj)use($full){ 
                return $obj->GetClientObject($full); }, array_values($lims));
        }
    }*/ // LIMITS
    
    /**
     * Loads the timed limit object or objects for the given objects
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return array [Limit] client objects
     * @see FilesApp::GetLimitObject()
     * @see Policy\Timed::GetClientObject()
     */
    /*protected function GetTimedLimits(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $lobj = $this->GetLimitObject($params, $authenticator, true, true, true);
        $class = $lobj['class']; $obj = $lobj['obj']; $full = $lobj['full']; // TODO replace this with a class
        
        if ($obj !== null)
        {
            $lims = $class::LoadAllForClient($this->database, $obj);
        }
        else
        {
            $count = $params->GetOptParam('limit',null)->GetNullUint();
            $offset = $params->GetOptParam('offset',null)->GetNullUint();
            $lims = $class::LoadAll($this->database, $count, $offset);
        }

        return array_map(function(Policy\Timed $lim)use($full){ 
            return $lim->GetClientObject($full); }, array_values($lims));
    }*/ // LIMITS
    
    /**
     * Returns all stored time stats for an object
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return ?array [id:TimedStats]
     * @see FilesApp::GetLimitObject()
     * @see Policy\TimedStats::GetClientObject()
     */
    /*protected function GetTimedStatsFor(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $lobj = $this->GetLimitObject($params, $authenticator, true, false, true);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        $period = $params->GetParam('timeperiod')->GetUint();
        $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
        
        if ($lim === null) return null;

        $count = $params->GetOptParam('limit',null)->GetNullUint();
        $offset = $params->GetOptParam('offset',null)->GetNullUint();
        
        return array_map(function(Policy\TimedStats $stats){ return $stats->GetClientObject(); },
            Policy\TimedStats::LoadAllByLimit($this->database, $lim, $count, $offset));        
    }*/ // LIMITS
    
    /**
     * Returns timed stats for the given object or objects at the given time
     * 
     * Defaults to the current account if none specified
     * @throws AuthenticationFailedException if not signed in
     * @return ?array TimedStats | [id:TimedStats]
     * @see FilesApp::GetLimitObject()
     * @see Policy\TimedStats::GetClientObject()
     */
    /*protected function GetTimedStatsAt(SafeParams $params, ?Authenticator $authenticator) : ?array
    {
        if ($authenticator === null) 
            throw new AuthenticationFailedException();
        
        $period = $params->GetParam('timeperiod')->GetUint();
        $attime = $params->GetParam('matchtime')->GetUint();
        
        $lobj = $this->GetLimitObject($params, $authenticator, true, true, true);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        if ($obj !== null)
        {
            $lim = $class::LoadByClientAndPeriod($this->database, $obj, $period);
            if ($lim === null) return null;
            
            $stats = Policy\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
            return ($stats !== null) ? $stats->GetClientObject() : null;
        }
        else
        {
            $count = $params->GetOptParam('limit',null)->GetNullUint();
            $offset = $params->GetOptParam('offset',null)->GetNullUint();
            
            $retval = array(); 
            
            foreach ($class::LoadAllForPeriod($this->database, $period, $count, $offset) as $lim)
            {
                $stats = Policy\TimedStats::LoadByLimitAtTime($this->database, $lim, $attime);
                if ($stats !== null) $retval[$lim->GetLimitedObject()->ID()] = $stats->GetClientObject();
            }
            
            return $retval;
        }   
    }*/ // LIMITS

    /**
     * Configures total limits for the given object
     * @throws AdminRequiredException if not admin
     * @return array Limits
     * @see FilesApp::GetLimitObject()
     * @see Policy\Total::GetClientObject()
     */
    /*protected function ConfigLimits(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null)
            throw new AdminRequiredException();
        $authenticator->RequireAdmin();
        
        $lobj = $this->GetLimitObject($params, $authenticator, false, false, false);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $params)->GetClientObject(true);
    }*/ // LIMITS
    
    /**
     * Configures timed limits for the given object
     * @throws AdminRequiredException if not admin
     * @return array Limits
     * @see FilesApp::GetLimitObject()
     * @see Policy\Timed::GetClientObject()
     */
    /*protected function ConfigTimedLimits(SafeParams $params, ?Authenticator $authenticator) : array
    {
        if ($authenticator === null) 
            throw new AdminRequiredException();
        $authenticator->RequireAdmin();
        
        $lobj = $this->GetLimitObject($params, $authenticator, false, false, true);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        return $class::ConfigLimits($this->database, $obj, $params)->GetClientObject(true);
    }*/ // LIMITS
    
    /**
     * Deletes all total limits for the given object
     * @throws AdminRequiredException if not admin
     * @see FilesApp::GetLimitObject()
     */
    /*protected function PurgeLimits(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AdminRequiredException();
        $authenticator->RequireAdmin();
        
        $lobj = $this->GetLimitObject($params, $authenticator, false, false, false);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        $class::DeleteByClient($this->database, $obj);
    }   */ // LIMITS 
    
    /**
     * Deletes all timed limits for the given object
     * @throws AdminRequiredException if not admin
     * @see FilesApp::GetLimitObject()
     */
    /*protected function PurgeTimedLimits(SafeParams $params, ?Authenticator $authenticator) : void
    {
        if ($authenticator === null) 
            throw new AdminRequiredException();
        $authenticator->RequireAdmin();
        
        $lobj = $this->GetLimitObject($params, $authenticator, false, false, true);
        $class = $lobj['class']; $obj = $lobj['obj'];
        
        $period = $params->GetParam('period')->GetUint();
        $class::DeleteClientAndPeriod($this->database, $obj, $period);
    }*/ // LIMITS
}

