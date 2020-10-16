<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/Item.php");
require_once(ROOT."/apps/files/File.php");
require_once(ROOT."/apps/files/Folder.php");
require_once(ROOT."/apps/files/Filesystem.php");

require_once(ROOT."/apps/files/storage/Storage.php");
require_once(ROOT."/apps/files/storage/Local.php");
require_once(ROOT."/apps/files/storage/FTP.php"); use Andromeda\Apps\Files\Storage;

require_once(ROOT."/core/AppBase.php"); use Andromeda\Core\AppBase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/IOInterface.php"); use Andromeda\Core\IOFormat\IOInterface;

require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\{Authenticator, AuthenticationFailedException};

use Andromeda\Core\UnknownActionException;

class UnknownFolderException  extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FOLDER"; }
class UnknownFilesystemException extends Exceptions\ClientNotFoundException { public $message = "UNKNOWN_FILESYSTEM"; }

class DuplicateFolderException extends Exceptions\ClientErrorException { public $message = "FOLDER_ALREADY_EXISTS"; }

class FilesApp extends AppBase
{
    public static function getVersion() : array { return array(0,0,1); } 
    
    public function Run(Input $input)
    {
        $this->database = $this->API->GetDatabase();
        
        $this->authenticator = Authenticator::TryAuthenticate($this->database, $input);
        
        switch($input->GetAction())
        {
            case 'getconfig': return $this->GetConfig($input); break;
            
            case 'upload':   return $this->UploadFile($input); break;  
            case 'download': return $this->DownloadFile($input); break;
            
            case 'getfolder':    return $this->GetFolder($input); break;
            case 'createfolder': return $this->CreateFolder($input); break;
            case 'deletefolder': return $this->DeleteFolder($input); break;
            
            // TODO get filesystems
            
            default: throw new UnknownActionException();
        }
    }
    
    protected function GetConfig(Input $input) : array
    {
        $account = $this->authenticator->GetAccount();
        $admin = $account !== null && $account->isAdmin();
    }
    
    protected function UploadFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByID($this->database, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();
        
        // TODO FILE CREATE
    }
    
    protected function DownloadFile(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        // TODO FILE DOWNLOAD
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
                $filesys = Filesystem::TryLoadByID($this->database, $filesys);  
                if ($filesys === null) throw new UnknownFilesystemException();
            }
                
            $folder = Folder::LoadRootByAccount($this->database, $account, $filesys);
        }

        if ($folder === null) throw new UnknownFolderException();

        // TODO user param to get only folders or only files

        $recursive = $input->TryGetParam('recursive',SafeParam::TYPE_BOOL) ?? false;
        $return = $folder->GetClientObject($recursive ? Folder::RECURSIVE : Folder::WITHCONTENT);
        if ($return === null) throw new UnknownFolderException(); return $return;
    }
    
    protected function CreateFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        $parent = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('parent',SafeParam::TYPE_ID));
        if ($parent === null) throw new UnknownFolderException();

        $name = $input->GetParam('name',SafeParam::TYPE_TEXT);
        
        if (Folder::TryLoadByParentAndName($this->database, $parent, $account, $name) !== null)
            throw new DuplicateFolderException();
 
        return Folder::Create($this->database, $parent, $account, $name)->GetClientObject();
    }
    
    protected function DeleteFolder(Input $input) : array
    {
        if ($this->authenticator === null) throw new AuthenticationFailedException();
        $account = $this->authenticator->GetAccount();
        
        // TODO DELETE allow sending an array of items to delete also...
        
        $folder = Folder::TryLoadByAccountAndID($this->database, $account, $input->GetParam('folder',SafeParam::TYPE_ID));
        if ($folder === null) throw new UnknownFolderException();
        
        $folder->Delete();
        
        return array();
    }
}

