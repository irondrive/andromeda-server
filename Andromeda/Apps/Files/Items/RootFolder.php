<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Storage\Storage;

/** A root folder has no parent or name */
class RootFolder extends Folder
{    
    use TableTypes\NoChildren;

    public function GetName() : string { return $this->storage->GetObject()->GetName(); }
    
    public function SetName(string $name, bool $overwrite = false) : bool
    { 
        return $this->storage->GetObject()->SetName($name);
    }

    /** Returns the owner of the underlying storage */
    public function TryGetStorageOwner() : ?Account
    {
        return $this->storage->GetObject()->TryGetOwner();
    }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : bool                       { throw new Exceptions\InvalidRootOpException(); }
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static     { throw new Exceptions\InvalidRootOpException(); }
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : static { throw new Exceptions\InvalidRootOpException(); }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name, bool $refresh = false) : static { throw new Exceptions\InvalidRootOpException(); }

    /**
     * Loads the root folder for given account and FS, creating it if it doesn't exist
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner of the root folder
     * @param Storage $storage the storage of the root, or null to get the default
     * @return ?static loaded folder or null if a default FS does not exist and none is given
     */
    public static function GetRootByAccountAndFS(ObjectDatabase $database, Account $account, ?Storage $storage = null) : ?static
    {
        $storage ??= Storage::LoadDefaultByAccount($database, $account); 
        if ($storage === null) return null;

        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('storage',$storage->ID()),
            $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = $database->TryLoadUniqueByQuery(static::class, $q->Where($where));
        if ($loaded !== null) return $loaded;
        else
        {
            $root = $database->CreateObject(static::class); // insert new root
            $root->isroot->SetValue(true);
            $root->date_created->SetTimeNow();
            $root->storage->SetObject($storage);

            $owner = $storage->isExternal() ? $storage->TryGetOwner() : $account;
            $root->owner->SetObject($owner);

            if ($owner === null)
                $root->ispublic->SetValue(true);

            $root->Refresh();
            return $root;
        }
    }
    
    /**
     * Loads all root folders on the given filesystem
     * @param ObjectDatabase $database database reference
     * @param Storage $storage the filesystem
     * @return array<string, RootFolder> folders indexed by ID
     */
    public static function LoadByStorage(ObjectDatabase $database, Storage $storage) : array
    {
        $q = new QueryBuilder(); 
        $q->Where($q->And($q->Equals('storage',$storage->ID())));
        return $database->LoadObjectsByQuery(static::class, $q);
    }
    
    /**
     * Load all root folders for the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account folder owner
     * @return array<string, RootFolder> folders indexed by ID
     */
    public static function LoadByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        $q->Where($q->And($q->Equals('owner',$account->ID())));
        return $database->LoadObjectsByQuery(static::class, $q);
    }

    /** 
     * Deletes all root folders on the given filesystem
     * @param bool $unlink if true, force not deleting filesystem content (automatic for external storage)
     */
    public static function DeleteByStorage(ObjectDatabase $database, Storage $storage, bool $unlink) : void
    {
        $unlink = $unlink || $storage->isExternal();
        foreach (static::LoadByStorage($database, $storage) as $folder)
        {
            if ($unlink) $folder->NotifyFSDeleted(); 
            else $folder->Delete();
        }
    }
    
    /** 
     * Deletes all root folders for the given account
     * if the storage is external, don't delete filesystem content
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        foreach (static::LoadByAccount($database, $account) as $folder)
        {
            $unlink = $folder->storage->GetObject()->isExternal();
            if ($unlink) $folder->NotifyFSDeleted(); 
            else $folder->Delete();
        }
    }
}
