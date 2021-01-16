<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/storage/Storage.php"); 
use Andromeda\Apps\Files\Storage\{Storage, ActivateException};

require_once(ROOT."/apps/files/filesystem/Shared.php");
require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/filesystem/NativeCrypt.php");

use Andromeda\Apps\Files\{Config, Folder};

class InvalidFSTypeException extends Exceptions\ServerException { public $message = "UNKNOWN_FILESYSTEM_TYPE"; }
class InvalidNameException extends Exceptions\ClientErrorException { public $message = "INVALID_FILESYSTEM_NAME"; }
class InvalidStorageException extends Exceptions\ClientErrorException { public $message = "STORAGE_ACTIVATION_FAILED"; }

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

    public function isGlobal() : bool { return !$this->HasObject('owner'); }
    public function isShared() : bool { return $this->GetType() === self::TYPE_SHARED; }
    public function isSecure() : bool { return $this->GetType() === self::TYPE_NATIVE_CRYPT; }
    
    public function isReadOnly() : bool          { return $this->TryGetScalar('readonly') ?? false; }
    public function setReadOnly(bool $ro) : self { return $this->SetScalar('readonly', $ro); }
    
    public function GetName() : ?string          { return $this->TryGetScalar('name'); }
    
    public function SetName(?string $name) : self 
    {
        $dupfs = static::TryLoadByAccountAndName($this->database, $this->GetOwner(), $name);
        if ($dupfs !== null || $name === "default")
            throw new InvalidNameException();
        
        return $this->SetScalar('name',$name); 
    }
    
    public function GetOwner() : ?Account            { return $this->TryGetObject('owner'); }
    public function GetOwnerID() : ?string           { return $this->TryGetObjectID('owner'); }
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
            else throw new InvalidFSTypeException();
        }
        
        return $this->interface; 
    }
    
    private static $storage_types = array();

    public static function RegisterStorageType(string $class) : void
    {
        self::$storage_types[strtolower(Utilities::ShortClassName($class))] = $class;
    }
    
    public static function GetCreateUsage() : string { return "--sttype ".implode('|',array_keys(self::$storage_types))." [--fstype native|crypt|shared] [--name name] [--global bool] [--readonly bool]"; }
    
    public static function GetCreateUsages() : array 
    { 
        $retval = array();
        foreach (self::$storage_types as $name=>$class)
            array_push($retval, "\t --sttype $name ".$class::GetCreateUsage());
        return $retval;
    }
    
    public static function Create(ObjectDatabase $database, Input $input, ?Account $account) : self
    {
        $name = $input->TryGetParam('name', SafeParam::TYPE_NAME, SafeParam::MaxLength(127));
        $readonly = $input->TryGetParam('readonly', SafeParam::TYPE_BOOL) ?? false;
        
        $sttype = $input->GetParam('sttype', SafeParam::TYPE_ALPHANUM,
            function($sttype){ return array_key_exists($sttype, self::$storage_types); });
        
        $fstype = $input->TryGetParam('fstype', SafeParam::TYPE_ALPHANUM,
            function($fstype){ return in_array($fstype, array('native','crypt','shared')); });        
        
        switch ($fstype ?? 'native')
        {
            case 'native': $fstype = self::TYPE_NATIVE; break;
            case 'crypt':  $fstype = self::TYPE_NATIVE_CRYPT; break;
            case 'shared': $fstype = self::TYPE_SHARED; break;
        }
        
        $filesystem = parent::BaseCreate($database)
            ->SetOwner($account)->SetName($name)
            ->SetType($fstype)->setReadOnly($readonly);
        
        if ($filesystem->isSecure())
        {
            $filesystem->SetScalar('crypto_chunksize', Config::GetInstance($database)->GetCryptoChunkSize());
            $filesystem->SetScalar('crypto_masterkey', CryptoSecret::GenerateKey());
        }

        try
        {            
            $filesystem->SetStorage(self::$storage_types[$sttype]::Create($database, $input, $account, $filesystem));
        
            $filesystem->GetStorage()->Test(); 
        }
        catch (ActivateException | Exceptions\ClientException $e){ throw InvalidStorageException::Copy($e); }
        
        return $filesystem;
    }
    
    public static function GetEditUsage() : string { return "[--name name] [--readonly bool]"; } // TODO GetEditUsages
    
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
        $found = static::TryLoadUniqueByQuery($database, $q1);
        
        if ($found === null)
        {
            $q2 = new QueryBuilder(); $q2->Where($q2->And($q2->IsNull('name'), $q2->IsNull('owner')));
            $found = static::TryLoadUniqueByQuery($database, $q2);
        }
        
        return $found;
    }
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account->ID()),$q->Equals('id',$id));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    public static function TryLoadByAccountAndName(ObjectDatabase $database, ?Account $account, ?string $name) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account ? $account->ID() : null),$q->Equals('name',$name));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
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
    
    public function GetClientObject(bool $admin = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'name' => $this->TryGetScalar('name'),
            'owner' => $this->TryGetObjectID('owner'),
            'shared' => $this->isShared(),
            'secure' => $this->isSecure(),
            'readonly' => $this->isReadOnly(),
            'storagetype' => Utilities::ShortClassName($this->GetStorageType())
        );
        
        if ($admin) 
        {
            $data['storage'] = $this->GetStorage()->GetClientObject();
            $data['chunksize'] = $this->TryGetScalar('crypto_chunksize');
        }
        
        return $data;
    }
}

require_once(ROOT."/apps/files/storage/Local.php");
require_once(ROOT."/apps/files/storage/FTP.php");
require_once(ROOT."/apps/files/storage/SFTP.php");
require_once(ROOT."/apps/files/storage/SMB.php");
