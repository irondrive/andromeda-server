<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
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

    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $account) : ?self
    {
        $q1 = new QueryBuilder(); $q1->Where($q1->And($q1->IsNull('name'), $q1->Equals('owner',$account->ID())));
        $found = self::LoadByQuery($database, $q1);
        
        if (!count($found))
        {
            $q2 = new QueryBuilder(); $q2->Where($q2->And($q2->IsNull('name'), $q2->IsNull('owner')));
            $found = self::LoadByQuery($database, $q2);
        }
        
        return count($found) ? array_values($found)[0] : null;
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        return parent::LoadByObject($database, 'account', $account);
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
