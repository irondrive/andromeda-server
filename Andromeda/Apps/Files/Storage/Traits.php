<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Files/Filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;

require_once(ROOT."/Apps/Accounts/FieldCrypt.php"); use Andromeda\Apps\Accounts\OptFieldCrypt;

/** Exception indicating that this storage does not support folder functions */
class FoldersUnsupportedException extends Exceptions\ClientErrorException { public $message = "STORAGE_FOLDERS_UNSUPPORTED"; }

/** Trait for storage classes that store a possibly-encrypted username and password */
trait UserPass
{
    use OptFieldCrypt;
    
    protected static function getEncryptedFields() : array { return array('username','password'); }
    
    /** Gets the extra DB fields required for this trait */
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), self::GetFieldCryptFieldTemplate(), array(
            'username' => null, 'password' => null,
        ));
    }
    
    /**
     * Returns the printable client object of this trait
     * @return array `{username:?string, password:bool}`
     * @see Storage::GetClientObject()
     */
    public function GetClientObject(bool $activate = false) : array
    {
        return array_merge(parent::GetClientObject($activate), $this->GetFieldCryptClientObject(), array(
            'username' => $this->TryGetUsername(),
            'password' => (bool)($this->TryGetPassword()),
        ));
    }
    
    /** Returns the command usage for Create() */
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." ".self::GetFieldCryptCreateUsage()." [--username alphanum] [--password raw]"; }
    
    /** Performs cred-crypt level initialization on a new storage */
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : Storage
    {
        return parent::Create($database, $input, $filesystem)->FieldCryptCreate($input)
            ->SetPassword($input->GetOptParam('password', SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER))
            ->SetUsername($input->GetOptParam('username', SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, null, SafeParam::MaxLength(255)));
    }
    
    /** Returns the command usage for Edit() */
    public static function GetEditUsage() : string { return parent::GetEditUsage()." ".self::GetFieldCryptEditUsage()." [--username ?alphanum] [--password ?raw]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function Edit(Input $input) : Storage
    {
        if ($input->HasParam('password')) $this->SetPassword($input->GetNullParam('password', 
            SafeParam::TYPE_RAW, SafeParams::PARAMLOG_NEVER));
        if ($input->HasParam('username')) $this->SetUsername($input->GetNullParam('username', 
            SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, null, SafeParam::MaxLength(255)));

        $this->FieldCryptEdit($input); return parent::Edit($input);
    }
    
    /** Returns the decrypted username */
    protected function TryGetUsername() : ?string { return $this->TryGetEncryptedScalar('username'); }
    
    /** Returns the decrypted password */
    protected function TryGetPassword() : ?string { return $this->TryGetEncryptedScalar('password'); }
    
    /**
     * Sets the stored username
     * @param ?string $username username
     * @return $this
     */
    protected function SetUsername(?string $username) : self { 
        return $this->SetEncryptedScalar('username',$username); }
    
    /**
     * Sets the stored password
     * @param ?string $password password
     * @return $this
     */
    protected function SetPassword(?string $password) : self { 
        return $this->SetEncryptedScalar('password',$password); }
}

/** A storage that does not support subfolders */
trait NoFolders
{
    public function supportsFolders() : bool { return false; }   
    
    public function isFolder(string $path) : bool
    {
        return !count(array_filter(explode('/',$path)));
    }
    
    protected function SubCreateFolder(string $path) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubDeleteFolder(string $path) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubRenameFolder(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubMoveFile(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
    
    protected function SubMoveFolder(string $old, string $new) : Storage { throw new FoldersUnsupportedException(); }
}

/** A storage that has a base path */
trait BasePath
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'path' => null
        ));
    }
    
    public function GetClientObject(bool $activate = false) : array
    {
        return array_merge(parent::GetClientObject($activate), array(
            'path' => $this->GetPath()
        ));
    }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --path fspath"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : Storage
    {
        $path = $input->GetParam('path', SafeParam::TYPE_FSPATH);
        return parent::Create($database, $input, $filesystem)->SetPath($path);
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--path fspath]"; }
    
    public function Edit(Input $input) : Storage
    {
        $path = $input->GetOptParam('path', SafeParam::TYPE_FSPATH);
        if ($path !== null) $this->SetPath($path);
        return parent::Edit($input);
    }
    
    /** Returns the full storage level path for the given root-relative path */
    protected function GetPath(string $path = "") : string
    {
        return $this->GetScalar('path').'/'.$path;
    }
    
    /** Sets the path of the storage's root */
    private function SetPath(string $path) : Storage { return $this->SetScalar('path',$path); }
}
