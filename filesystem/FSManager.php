<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;

require_once(ROOT."/apps/files/filesystem/Shared.php");
require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/filesystem/NativeCrypt.php");

class InvalidFSTypeException extends Exceptions\ServerException { public $message = "UNKNOWN_FILESYSTEM_TYPE"; }

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
    
    protected function SubConstruct() : void
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
    
    public function isReadOnly() : bool { return $this->TryGetScalar('readonly') ?? false; }
    public function isShared() : bool { return $this->GetType() === self::TYPE_SHARED; }
    public function isSecure() : bool { return $this->GetType() === self::TYPE_NATIVE_CRYPT; }
    
    public function GetName() : ?string { return $this->TryGetScalar('name'); }
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    private function GetType() : int { return $this->GetScalar('type'); }
    public function GetStorage() : Storage { return $this->GetObject('storage'); }
    public function GetStorageType() : string { return $this->GetObjectType('storage'); }
    
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    public function GetFSImpl() : FSImpl { return $this->interface; }

    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $account) : self
    {
        // TODO FUTURE maybe use a manual query to get this done in a single query        
        $found = static::LoadManyMatchingAll($database, array('owner'=>$account->ID(),'name'=>null));
        if (!count($found)) $found = static::LoadManyMatchingAll($database, array('owner'=>null,'name'=>null));
        return array_values($found)[0]; // TODO what if all their FSes have a name? should not be an error
        // I don't like using the name to determine the default, maybe we could even let the user change the default?
        // TODO - have a flag for default, don't use null name. Also make this a TRY - it's conceivable the admin could not configure a storage and leave it up to the users
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        return static::LoadManyMatchingAny($database, 'owner', array(null, $account->ID()));
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
            'chunksize' => $this->TryGetScalar('crypto_chunksize'),
            'storagetype' => Utilities::array_last(explode('\\',$this->GetStorageType()))
        );
        
        if ($priv) $data['storage'] = $this->GetStorage()->GetClientObject();
        
        return $data;
    }
}
