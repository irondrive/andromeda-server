<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\Input;
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

/**
 * Allows using an SMB/CIFS server for backend storage
 * 
 * Uses PHP libsmbclient.  Mostly uses the PHP fwrapper
 * functions but some manual workarounds are needed.
 * Uses credcrypt to allow encrypting the username/password.
 * 
 * @phpstan-import-type StorageJ from Storage
 * @phpstan-import-type PrivStorageJ from Storage
 * @phpstan-import-type BasePathJ from BasePath
 * @phpstan-import-type UserPassJ from UserPass
 * @phpstan-type SMBJ \Union<PrivStorageJ, BasePathJ, UserPassJ, array{hostname:string, workgroup:?string}>
 */
class SMB extends FWrapper
{
    use BasePath, UserPass, TableTypes\TableNoChildren;

    /** Hostname of the server */
    protected FieldTypes\StringType $hostname;
    /** Optional SMB workgroup */
    protected FieldTypes\NullStringType $workgroup;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->hostname = new FieldTypes\StringType('hostname');
        $this->workgroup = new FieldTypes\NullStringType('workgroup');

        $this->RegisterFields($fields, self::class);
        $this->BasePathCreateFields();
        $this->UserPassCreateFields();
        parent::CreateFields();
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetCryptFields() : array { return $this->GetUserPassCryptFields(); }

    /**
     * Returns a printable client object of this SMB storage
     * @param bool $priv if true, show details for the owner
     * @param bool $activate if true, show details that require activation
     * @return ($priv is true ? SMBJ : StorageJ)
     */
    public function GetClientObject(bool $priv, bool $activate = false) : array
    {
        $ret = parent::GetClientObject($priv,$activate);
        if ($priv)
        {
            $ret += $this->GetBasePathClientObject();
            $ret += $this->GetUserPassClientObject();
            $ret += array(
                'hostname' => $this->hostname->GetValue(),
                'workgroup' => $this->workgroup->TryGetValue()
            );
        }
        return $ret;
    }
    
    public static function GetCreateUsage() : string { 
        return static::GetBasePathCreateUsage()." ".static::GetUserPassCreateUsage(requireUsername:false).
        " --hostname alphanum [--workgroup ?alphanum]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : static
    {
        $params = $input->GetParams();
        $obj = parent::Create($database, $input, $owner);

        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->workgroup->SetValue($params->GetOptParam('workgroup',null)->CheckLength(255)->GetNullAlphanum());

        $obj->BasePathCreate($params);
        $obj->UserPassCreate($params,requireUsername:false);
        return $obj;
    }
    
    public static function GetEditUsage() : string { 
        return static::GetBasePathEditUsage()." ".static::GetUserPassEditUsage(requireUsername:false).
        " [--hostname alphanum] [--workgroup ?alphanum]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
    
        if ($params->HasParam('hostname')) 
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        
        if ($params->HasParam('workgroup')) 
            $this->workgroup->SetValue($params->GetParam('workgroup')->CheckLength(255)->GetNullAlphanum());
        
        $this->BasePathEdit($params);
        $this->UserPassEdit($params,requireUsername:false);
        return parent::Edit($input);
    }
    
    /** Checks for the SMB client extension */
    public function PostConstruct() : void
    {
        if (!function_exists('smbclient_version')) 
            throw new Exceptions\SMBExtensionException();
    }
    
    /** @var ?resource */
    private $state = null;
    
    public function Activate() : self { $this->GetState(); return $this; }

    /** @return resource */
    protected function GetState()
    {
        if ($this->state !== null) return $this->state;
        $state = smbclient_state_new();
        
        if (!is_resource($state) || smbclient_state_init($state) !== true)
            throw new Exceptions\SMBStateInitException();
        
        if (!smbclient_option_set($state, 
            SMBCLIENT_OPT_ENCRYPT_LEVEL, 
            SMBCLIENT_ENCRYPTLEVEL_REQUEST))
            throw new Exceptions\SMBStateInitException();
        
        if (!is_readable($this->GetFullURL()))
            throw new Exceptions\SMBConnectException();
        
        return $this->state = $state;
    }

    public function canGetFreeSpace() : bool { return true; }
    
    public function GetFreeSpace() : int
    {
        if (($data = smbclient_statvfs($this->GetState(), $this->GetFullURL())) === false)
            throw new Exceptions\FreeSpaceFailedException();

        return $data['frsize'] * $data['bsize'] * $data['bavail']; // @phpstan-ignore-line
    }

    protected function GetFullURL(string $path = "") : string
    {
        $username = rawurlencode($this->username->TryGetValue() ?? "");
        $password = rawurlencode($this->password->TryGetValue() ?? "");
        $workgroup = rawurlencode($this->workgroup->TryGetValue() ?? "");
                
        $connstr = "";
        if ($workgroup !== "") $connstr .= "$workgroup;";
        if ($username !== "")  $connstr .= $username;
        if ($password !== "")  $connstr .= ":$password";
        if ($connstr !== "")   $connstr .= "@";
        
        $connstr = "smb://".$connstr.$this->hostname->GetValue();
                
        return $connstr.'/'.$this->GetPath($path);
    }
    
    // WORKAROUND: php-smbclient does not support b fopen flag
    protected function OpenHandle(string $path, bool $isWrite){ 
        return fopen($this->GetFullURL($path), $isWrite?'r+':'r'); }
    
    // WORKAROUND: php-smbclient <= 3.0.5 does not implement stream ftruncate
    protected function SubTruncate(string $path, int $length) : self
    {
        $this->ClosePath($path); // close existing handles
        $state = $this->GetState();
        
        $handle = smbclient_open($state, $this->GetFullURL($path), 'r+');
        if (!$handle) throw new Exceptions\FileWriteFailedException();
            
        if (!smbclient_ftruncate($state, $handle, $length))
            throw new Exceptions\FileWriteFailedException();
        
        if (!smbclient_close($state, $handle))
            throw new Exceptions\FileWriteFailedException();

        return $this;
    }
}
