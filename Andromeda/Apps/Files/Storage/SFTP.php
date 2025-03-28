<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\Database\Exceptions\FieldDataNullException;
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

use \phpseclib3\Crypt\PublicKeyLoader;
use \phpseclib3\Net\SFTP as SFTPConnection;

/**
 * Allows using an SFTP server for backend storage using phpseclib
 * 
 * Uses credcrypt to allow encrypting the username/password.
 * 
 * @phpstan-import-type StorageJ from Storage
 * @phpstan-import-type PrivStorageJ from Storage
 * @phpstan-import-type BasePathJ from BasePath
 * @phpstan-import-type UserPassJ from UserPass
 * @phpstan-type SFTPJ \Union<PrivStorageJ, BasePathJ, UserPassJ, array{hostname:string, port:?int, hostkey:?string, privkey:bool, keypass:bool}>
 */
class SFTP extends FWrapper
{
    use BasePath, UserPass, TableTypes\TableNoChildren;

    /** Hostname of the server */
    protected FieldTypes\StringType $hostname;
    /** The port to connect to the server */
    protected FieldTypes\NullIntType $port;
    /** The last known host key of the server */
    protected FieldTypes\NullStringType $hostkey;
    /** The private key to use for authentication */
    protected CryptFields\NullCryptStringType $privkey;
    protected FieldTypes\NullStringType $privkey_nonce;
    /** The key to use to unlock the private key */
    protected CryptFields\NullCryptStringType $keypass;
    protected FieldTypes\NullStringType $keypass_nonce;

    protected function CreateFields() : void
    {
        $fields = array();
        $this->hostname = new FieldTypes\StringType('hostname');
        $this->port = new FieldTypes\NullIntType('port');
        $this->hostkey = new FieldTypes\NullStringType('hostkey');

        $this->privkey_nonce = new FieldTypes\NullStringType('privkey_nonce');
        $this->privkey = new CryptFields\NullCryptStringType('privkey',$this->owner,$this->privkey_nonce);
        $this->keypass_nonce = new FieldTypes\NullStringType('keypass_nonce');
        $this->keypass = new CryptFields\NullCryptStringType('keypass',$this->owner,$this->keypass_nonce);

        $this->RegisterFields($fields, self::class);
        $this->BasePathCreateFields();
        $this->UserPassCreateFields();
        parent::CreateFields();
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetCryptFields() : array { 
        return array_merge(array($this->privkey, $this->keypass),$this->GetUserPassCryptFields()); }

    /**
     * Returns a printable client object of this SFTP storage
     * @param bool $priv if true, show details for the owner
     * @param bool $activate if true, show details that require activation
     * @return ($priv is true ? SFTPJ : StorageJ)
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
                'port' => $this->port->TryGetValue(),
                'hostkey' => $this->hostkey->TryGetValue(),
                'privkey' => $this->privkey->GetDBValue() !== null,
                'keypass' => $this->keypass->GetDBValue() !== null
            );
        }
        return $ret;
    }

    public static function GetCreateUsage() : string { 
        return static::GetBasePathCreateUsage()." ".static::GetUserPassCreateUsage(requireUsername:true).
        " --hostname alphanum [--port ?uint16] [--privkey% path | --privkey-] [--keypass ?raw]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $owner) : static
    { 
        $params = $input->GetParams();
        $obj = parent::Create($database, $input, $owner);
        
        $obj->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        $obj->port->SetValue($params->GetOptParam('port',null)->GetNullUint16());
        
        if ($input->HasFile('privkey'))
            $obj->privkey->SetValue($input->GetFile('privkey')->GetData());
        $obj->keypass->SetValue($params->GetOptParam('keypass',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        
        $obj->BasePathCreate($params);
        $obj->UserPassCreate($params,requireUsername:true);
        return $obj;
    }

    public static function GetEditUsage() : string { 
        return static::GetBasePathEditUsage()." ".static::GetUserPassEditUsage(requireUsername:true).
        " [--hostname alphanum] [--port ?uint16] [--privkey% path | --privkey-] [--keypass ?raw] [--resethost bool]"; }
    
    public function Edit(Input $input) : self
    {
        $params = $input->GetParams();
        
        if ($params->HasParam('hostname'))
            $this->hostname->SetValue($params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('port'))
            $this->port->SetValue($params->GetParam('port')->GetNullUint16());
        
        if ($params->HasParam('keypass'))
            $this->keypass->SetValue($params->GetParam('keypass',SafeParams::PARAMLOG_NEVER)->GetNullRawString());
        if ($input->HasFile('privkey'))
            $this->privkey->SetValue($input->GetFile('privkey')->GetData());
        
        if ($params->GetOptParam('resethost',false)->GetBool())
            $this->hostkey->SetValue(null);
        
        $this->BasePathEdit($params);
        $this->UserPassEdit($params,requireUsername:true);
        return parent::Edit($input);
    }

    private ?SFTPConnection $sftp = null;

    public function Activate() : self { $this->GetConnection(); return $this; }

    /** @return SFTPConnection */
    protected function GetConnection() : SFTPConnection
    {
        if ($this->sftp !== null) return $this->sftp;
        
        SFTPConnection\Stream::register();

        $host = $this->hostname->GetValue(); 
        $port = $this->port->TryGetValue() ?? 22;
        
        try // connect
        {
            $sftp = new SFTPConnection($host, $port);

            $hostkey = $sftp->getServerPublicHostKey();
            $lastkey = $this->hostkey->TryGetValue();
            if ($lastkey === null && $hostkey !== false) 
                $this->hostkey->SetValue($hostkey);
            else if ($lastkey !== $hostkey) 
                throw new Exceptions\HostKeyMismatchException();
        }
        catch (\RuntimeException $e) {
            throw new Exceptions\SSHConnectionFailure($e); }

        try // authenticate
        {
            if (($username = $this->username->TryGetValue()) === null)
                throw new FieldDataNullException('username');
            
            $sftp->login($username);
    
            if (($password = $this->password->TryGetValue()) !== null) 
                $sftp->login($username, $password);
    
            if (($privkey = $this->privkey->TryGetValue()) !== null)
            {
                $keypass = $this->keypass->TryGetValue();
                $privkey2 = PublicKeyLoader::loadPrivateKey($privkey, $keypass ?? false); /** @phpstan-ignore-line doc is wrong */
                $sftp->login($username, $privkey2);
            }
    
            if (!$sftp->isAuthenticated()) 
                throw new Exceptions\SSHAuthenticationFailure();
        }
        catch (\RuntimeException $e) { 
            throw new Exceptions\SSHAuthenticationFailure($e); }
        
        return $this->sftp = $sftp;
    }    
    
    // WORKAROUND - is_writeable does not work on directories
    protected function assertWriteable() : void { $this->TestWriteable(); }

    protected function GetFullURL(string $path = "") : string
    {
        return "sftp://".$this->GetConnection()."/".$this->GetPath($path);
    }
    
    protected function SubImportFile(string $src, string $dest, bool $istemp) : self
    {
        $mode = SFTPConnection::SOURCE_LOCAL_FILE;
        
        if (!$this->GetConnection()->put($this->GetPath($dest), $src, $mode))
            throw new Exceptions\FileCreateFailedException();
            
        return $this;
    }
}