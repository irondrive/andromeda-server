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
use Andromeda\Apps\Files\Storage\{Storage, ActivateException, StorageException};

require_once(ROOT."/apps/files/filesystem/Shared.php");
require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/filesystem/NativeCrypt.php");

use Andromeda\Apps\Files\{Config, Folder};

/** Exception indicating that the stored filesystem type is not valid */
class InvalidFSTypeException extends Exceptions\ServerException { public $message = "UNKNOWN_FILESYSTEM_TYPE"; }

/** Exception indicating that the given filesystem name is invalid */
class InvalidNameException extends Exceptions\ClientErrorException { public $message = "INVALID_FILESYSTEM_NAME"; }

/** Exception indicating that the underlying storage connection failed */
class InvalidStorageException extends Exceptions\ClientErrorException { public $message = "STORAGE_ACTIVATION_FAILED"; }

/**
 * An object that manages and points to filesystems
 *
 * Filesystems are composed of a manager, which has an implementation, which has a storage.
 * Filesystems are generally referred to by the manager (the storage is internal only).
 * 
 * The manager stores metadata and allows filesystems of different types to be looked up from 
 * a single table.  It also handles creating/loading/deleting/etc. whole filesystems.  
 * 
 * The implementation (loaded by the manager) implements the actual disk functions, 
 * defining the structure and usage of the filesystem (how DB objects map to disk files).
 * 
 * The implementation calls down into a storage, which defines at a lower
 * level how the functions are actually mapped into the underlying storage.
 */
class FSManager extends StandardObject
{
    const TYPE_NATIVE = 0; const TYPE_NATIVE_CRYPT = 1; const TYPE_SHARED = 2;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null, // name of the FS, null if it's the default
            'type' => null, // enum of the type of FS impl
            'readonly' => null,
            'storage' => new FieldTypes\ObjectPoly(Storage::class),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'crypto_masterkey' => null,
            'crypto_chunksize' => null
        ));
    }

    /** Returns true if the data in this filesystem is shared with the filesystem itself, false if Andromeda owns it */
    public function isShared() : bool { return $this->GetType() === self::TYPE_SHARED; }
    
    /** Returns true if the data is encrypted before sending to the filesystem */
    public function isSecure() : bool { return $this->GetType() === self::TYPE_NATIVE_CRYPT; }
    
    /** Returns true if the filesystem is read-only */
    public function isReadOnly() : bool { return $this->TryGetScalar('readonly') ?? false; }
    
    /** Sets whether this filesystem is read-only */
    public function setReadOnly(bool $ro) : self { return $this->SetScalar('readonly', $ro); }
    
    /** Returns the name (or null) of this filesystem */
    public function GetName() : ?string { return $this->TryGetScalar('name'); }
    
    /** Sets the name of this filesystem, checks uniqueness */
    public function SetName(?string $name) : self 
    {
        $dupfs = static::TryLoadByAccountAndName($this->database, $this->GetOwner(), $name);
        if ($dupfs !== null || $name === "default")
            throw new InvalidNameException();
        
        return $this->SetScalar('name',$name); 
    }
    
    /** Returns true if this filesystem has an owner (not global) */
    public function isUserOwned() : bool { return $this->HasObject('owner'); }
    
    /** Returns the owner of this filesystem (or null) */
    public function GetOwner() : ?Account  { return $this->TryGetObject('owner'); }
    
    /** Returns the owner ID of this filesystem (or null) */
    public function GetOwnerID() : ?string { return $this->TryGetObjectID('owner'); }
    
    /** Sets the owner of this filesystem to the given account */
    private function SetOwner(?Account $owner) : self { return $this->SetObject('owner',$owner); }
        
    /** Returns the filesystem impl enum type */
    private function GetType() : int { return $this->GetScalar('type'); }
    
    /** Changes the filesystem impl enum type to the given value */
    private function SetType(int $type) { unset($this->interface); return $this->SetScalar('type',$type); }
    
    /** Activates and returns the underlying storage */
    public function GetStorage() : Storage { return $this->GetObject('storage')->Activate(); }  
    
    /** Returns the type of the underlying storage */
    public function GetStorageType() : string { return $this->GetObjectType('storage'); }
    
    /** Edits properites of the underlying storage and runs test */
    public function EditStorage(Input $input) : Storage { return $this->GetObject('storage')->Edit($input)->Test(); }
    
    /** Sets the underlying storage object for the filesystem */
    private function SetStorage(Storage $st) : self { return $this->SetObject('storage',$st); }
    
    /** Returns a reference to the global database */
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    
    private FSImpl $interface;
    
    /**
     * Rreturns the filesystem impl interface (items use this)
     * @throws InvalidFSTypeException if the stored FS is invalid
     */
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

    /** Registers an available type of underlying storage */
    public static function RegisterStorageType(string $class) : void
    {
        self::$storage_types[strtolower(Utilities::ShortClassName($class))] = $class;
    }
    
    /** Returns the common command usage of Create() */
    public static function GetCreateUsage() : string { return "--sttype ".implode('|',array_keys(self::$storage_types))." [--fstype native|crypt|shared] [--name name] [--global bool] [--readonly bool]"; }
    
    /** Returns the command usage of Create() specific to each storage type */
    public static function GetCreateUsages() : array 
    { 
        $retval = array();
        foreach (self::$storage_types as $name=>$class)
            array_push($retval, "\t --sttype $name ".$class::GetCreateUsage());
        return $retval;
    }
    
    /**
     * Creates and tests a new filesystem
     * @param ObjectDatabase $database database reference
     * @param Account $account owner of the filesystem
     * @throws InvalidStorageException if the storage fails
     * @return self
     */
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
    
    /** Returns the command usage of Edit() */
    public static function GetEditUsage() : string { return "[--name name] [--readonly bool]"; } // TODO GetEditUsages
    
    /** Edits an existing filesystem with the given values, and tests it */
    public function Edit(Input $input) : self
    {
        $ro = $input->TryGetParam('readonly', SafeParam::TYPE_BOOL);
        $name = $input->TryGetParam('name', SafeParam::TYPE_NAME);
        
        if ($name !== null) $this->SetName($name);
        if ($ro !== null) $this->setReadOnly($ro);
        
        $this->EditStorage($input)->Test(); return $this;
    }

    /**
     * Attempts to load the default filesystem (no name)
     * @param ObjectDatabase $database database reference
     * @param Account $account account to get the default for
     * @return self|NULL loaded FS or null if not available
     */
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
    
    /** Attempts to load a filesystem with the given owner (not null) and ID */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account->ID()),$q->Equals('id',$id));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /** Attempts to load a filesystem with the given owner (or null) and name */
    public static function TryLoadByAccountAndName(ObjectDatabase $database, ?Account $account, ?string $name) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('owner',$account ? $account->ID() : null),$q->Equals('name',$name));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /**
     * Loads an array of all filesystems owned by the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account owner of filesystems
     * @return array<string, FSManager> managers indexed by ID
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder(); $w = $q->Or($q->Equals('owner',$account->ID()),$q->IsNull('owner'));
        return self::LoadByQuery($database, $q->Where($w));
    }
    
    /** Deletes all filesystems owned by the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        parent::DeleteByObject($database, 'owner', $account);
    }
    
    /** Deletes this filesystem and all folder roots on it, from DB only */
    public function ForceDelete() : void 
    {
        // TODO test that this works when the underlying storage can't activate
        Folder::DeleteRootsByFSManager($this->database, $this, true);
        
        $this->DeleteObject('storage'); parent::Delete();
    }
    
    /** Deletes this filesystem and all folder roots on it */
    public function Delete() : void
    {
        Folder::DeleteRootsByFSManager($this->database, $this);
        
        $this->DeleteObject('storage'); parent::Delete();
    }
    
    /**
     * Gets a printable client object of this filesystem
     * @param bool $admin if true, show details for the owner
     * @return array `{id:id, name:?string, owner:?id, shared:bool, secure:bool, readonly:bool, storagetype:string}` \  
        if admin, add `{storage:Storage, chunksize:?int}`
     * @see Storage::GetClientObject()
     */
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

// when an account is deleted, need to delete files-related stuff also
Account::RegisterDeleteHandler(function(ObjectDatabase $database, Account $account)
{
    FSManager::DeleteByAccount($database, $account);
    Folder::DeleteRootsByAccount($database, $account);
});

// Load the registered storage types 
require_once(ROOT."/apps/files/storage/Local.php");
require_once(ROOT."/apps/files/storage/FTP.php");
require_once(ROOT."/apps/files/storage/SFTP.php");
require_once(ROOT."/apps/files/storage/SMB.php");
