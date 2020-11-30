<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
use Andromeda\Core\Exceptions\{ServerException, ClientException};

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\{Storage, Local, FTP, SFTP, ActivateException, StorageException};

require_once(ROOT."/apps/files/filesystem/Shared.php");
require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/filesystem/NativeCrypt.php");

use Andromeda\Apps\Files\{Config, Folder};

class InvalidFSTypeServerException extends Exceptions\ServerException { public $message = "UNKNOWN_FILESYSTEM_TYPE"; }
class InvalidFSTypeClientException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_FILESYSTEM_TYPE"; }
class InvalidSTTypeClientException extends Exceptions\ClientErrorException { public $message = "UNKNOWN_STORAGE_TYPE"; }
class InvalidNameException extends Exceptions\ClientErrorException { public $message = "INVALID_FILESYSTEM_NAME"; }

class InvalidStorageException extends Exceptions\ClientErrorException 
{ 
    public $message = "STORAGE_ACTIVATION_FAILED"; 
    public function __construct(\Throwable $e) 
    { 
        $this->message = $e->getMessage();
        if (is_a($e, ServerException::class))
            $this->message .= " ".$e->getDetails(); 
    }
}

class FSManager extends StandardObject
{
    const TYPE_NATIVE = 0; const TYPE_NATIVE_CRYPT = 1; const TYPE_SHARED = 2;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'type' => null,
            'readonly' => null,
            'storage' => new FieldTypes\ObjectPoly(Storage::class),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'crypto_masterkey' => null,
            'crypto_chunksize' => null
        ));
    }

    public function isShared() : bool { return $this->GetType() === self::TYPE_SHARED; }
    public function isSecure() : bool { return $this->GetType() === self::TYPE_NATIVE_CRYPT; }
    
    public function isReadOnly() : bool          { return $this->TryGetScalar('readonly') ?? false; }
    public function setReadOnly(bool $ro) : self { return $this->SetScalar('readonly', $ro); }
    
    public function GetName() : ?string          { return $this->TryGetScalar('name'); }
    
    public function SetName(?string $name) : self 
    { 
        if ($name === $this->GetName()) return $this;
        
        $dupfs = static::TryLoadByAccountAndName($this->database, $this->GetOwner(), $name);
        if ($dupfs !== null || $name === "default")
            throw new InvalidNameException();
        
        return $this->SetScalar('name',$name); 
    }
    
    public function GetOwner() : ?Account            { return $this->TryGetObject('owner'); }
    private function SetOwner(?Account $owner) : self { return $this->SetObject('owner',$owner); }
    
    private function GetType() : int { return $this->GetScalar('type'); }
    private function SetType(int $type) { unset($this->interface); return $this->SetScalar('type',$type); }
    
    public function GetStorage() : Storage { return $this->GetObject('storage')->Activate(); }    
    public function GetStorageType() : string { return $this->GetObjectType('storage'); }
    
    public function EditStorage(Input $input) : Storage { return $this->GetObject('storage')->Edit($input)->Test(); }
    private function SetStorage(Storage $st) : self { return $this->SetObject('storage',$st); }
    
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    private FSImpl $interface;
    
    public function GetFSImpl() : FSImpl 
    {
        if (!isset($this->interface))
        {
            if ($this->GetType() === self::TYPE_NATIVE)
            {
                $this->interface = new Native($this);
            }
            else if ($this->GetType() === self::TYPE_NATIVE_CRYPT)
            {
                $masterkey = $this->GetScalar('crypto_masterkey');
                $chunksize = $this->GetScalar('crypto_chunksize');
                $this->interface = new NativeCrypt($this, $masterkey, $chunksize);
            }
            else if ($this->GetType() === self::TYPE_SHARED)
            {
                $this->interface = new Shared($this);
            }
            else throw new InvalidFSTypeServerException();
        }
        
        return $this->interface; 
    }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account) : self
    {
        $name = $input->TryGetParam('name', SafeParam::TYPE_NAME);
        $sttype = $input->GetParam('sttype', SafeParam::TYPE_ALPHANUM);
        $fstype = $input->TryGetParam('fstype', SafeParam::TYPE_INT) ?? self::TYPE_NATIVE;
        $readonly = $input->TryGetParam('readonly', SafeParam::TYPE_BOOL) ?? false;

        if (!in_array($fstype,array(self::TYPE_NATIVE,self::TYPE_NATIVE_CRYPT,self::TYPE_SHARED)))
            throw new InvalidFSTypeClientException();
        
        $filesystem = parent::BaseCreate($database)
            ->SetName($name)->setReadOnly($readonly)
            ->SetType($fstype)->SetOwner($account);
        
        if ($filesystem->isSecure())
        {
            $filesystem->SetScalar('crypto_chunksize', Config::Load($database)->GetCryptoChunkSize());
            $filesystem->SetScalar('crypto_masterkey', CryptoSecret::GenerateKey());
        }

        try
        { 
            switch ($sttype)
            {
                case 'local': $filesystem->SetStorage(Local::Create($database, $input, $account, $filesystem)); break;
                case 'ftp':   $filesystem->SetStorage(FTP::Create($database, $input, $account, $filesystem));  break;
                case 'sftp':  $filesystem->SetStorage(SFTP::Create($database, $input, $account, $filesystem)); break;
                default: throw new InvalidSTTypeClientException();
            }
        
            $filesystem->GetStorage()->Test(); 
        }
        catch (ActivateException | ClientException $e){ throw new InvalidStorageException($e); }
        
        return $filesystem;
    }
    
    public function Edit(Input $input) : self
    {
        $ro = $input->TryGetParam('readonly', SafeParam::TYPE_BOOL);
        $name = $input->TryGetParam('name', SafeParam::TYPE_NAME);
        
        if ($name !== null) $this->SetName($name);
        if ($ro !== null) $this->setReadOnly($ro);
        
        $this->EditStorage($input)->Test(); return $this;
    }

    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $account) : ?self
    {
        $q1 = new QueryBuilder(); $q1->Where($q1->And($q1->IsNull('name'), $q1->Equals('owner',$account->ID())));
        $found = static::LoadOneByQuery($database, $q1);
        
        if ($found === null)
        {
            $q2 = new QueryBuilder(); $q2->Where($q2->And($q2->IsNull('name'), $q2->IsNull('owner')));
            $found = static::LoadOneByQuery($database, $q2);
        }
        
        return $found;
    }
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account->ID()),$q->Equals('id',$id));
        return self::LoadOneByQuery($database, $q->Where($w));
    }
    
    public static function TryLoadByAccountAndName(ObjectDatabase $database, ?Account $account, string $name) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account ? $account->ID() : null),$q->Equals('name',$name));
        return self::LoadOneByQuery($database, $q->Where($w));
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $w = $q->Or($q->Equals('owner',$account->ID()),$q->IsNull('owner'));
        return self::LoadByQuery($database, $q->Where($w));
    }
    
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        parent::DeleteByObject($database, 'owner', $account);
    }
    
    public function ForceDelete() : void
    {        
        $this->DeleteObject('storage'); parent::Delete();
    }
    
    public function Delete() : void
    {
        Folder::DeleteRootsByFSManager($this->database, $this);
        
        static::ForceDelete();
    }
    
    public function GetClientObject(bool $priv = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'owner' => $this->GetObjectID('owner'),
            'shared' => $this->isShared(),
            'secure' => $this->isSecure(),
            'readonly' => $this->isReadOnly(),
            'storagetype' => Utilities::array_last(explode('\\',$this->GetStorageType()))
        );
        
        if ($priv) 
        {
            $data['storage'] = $this->GetStorage()->GetClientObject();
            $data['chunksize'] = $this->TryGetScalar('crypto_chunksize');
        }
        
        return $data;
    }
}
