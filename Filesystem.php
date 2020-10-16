<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;

require_once(ROOT."/apps/files/Shared.php");
require_once(ROOT."/apps/files/Native.php");

class Filesystem extends StandardObject
{
    const TYPE_NATIVE = 0; const TYPE_SHARED = 1;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'shared' => null,
            'readonly' => null,
            'storage' => new FieldTypes\ObjectPoly(Storage::class),
            'owner' => new FieldTypes\ObjectRef(Account::class)
        ));
    }
    
    protected function SubConstruct() : void
    {
        $this->interface = $this->isShared() ? new Shared($this) : new Native($this);
    }
    
    public function isShared() : bool { return $this->TryGetScalar('shared') ?? false; }
    public function isReadOnly() : bool { return $this->TryGetScalar('readonly') ?? false; } // TODO read-only
    
    public function GetName() : ?string { return $this->TryGetScalar('name'); }
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    public function GetStorage() : Storage { return $this->GetObject('storage'); }
    
    public function GetDatabase() : ObjectDatabase { return $this->database; }
    public function GetIface() : FilesystemImpl { return $this->interface; }
    
    public static function LoadDefaultByAccount(ObjectDatabase $database, Account $account) : self
    {
        // TODO FUTURE maybe use a manual query to get this done in a single query        
        $found = static::LoadManyMatchingAll($database, array('owner'=>$account->ID(),'name'=>null));
        if (!count($found)) $found = static::LoadManyMatchingAll($database, array('owner'=>null,'name'=>null));
        return array_values($found)[0];
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        // TODO FUTURE maybe use a manual query to get this done in a single query
        $mine = static::LoadManyMatchingAll($database, array('owner'=>$account->ID()));
        $global = static::LoadManyMatchingAll($database, array('owner'=>null));
        return array_merge($mine, $global);
    }
}
