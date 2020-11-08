<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/FWrapper.php");

class LocalNonAdminException extends ActivateException { public $message = "LOCAL_STORAGE_ADMIN_ONLY"; }

class Local extends FWrapper
{
    public function Activate() : self { return $this; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account, FSManager $filesystem) : self
    {
        if ($account && !$account->isAdmin()) throw new LocalNonAdminException();
        else return parent::Create($database, $input, $account, $filesystem);
    }
    
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'freespace' => $this->GetFreeSpace()
        ));
    }

    public function GetFreeSpace() : int
    {
        $space = disk_free_space($this->GetPath());
        if ($space === false) throw new FreeSpaceFailedException();
        else return $space;
    }

    protected function GetFullURL(string $path = "") : string
    {
        return $this->GetPath($path);
    }
    
    public function ImportFile(string $src, string $dest) : self
    {
        $this->CheckReadOnly();
        if (!rename($src, $this->GetFullURL($dest)))
            throw new FileCreateFailedException();
        return $this;
    }
}