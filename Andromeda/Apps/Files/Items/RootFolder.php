<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Storage\Storage;

/** A root folder has no parent or name */
class RootFolder extends Folder
{    use TableTypes\NoChildren;

    public function GetName() : string { return $this->storage->GetName(); }
    
    public function SetName(string $name, bool $overwrite = false) : bool
    { 
        return $this->storage->GetObject()->SetName($name);
    }
    
    /** Returned if this root's filesystem is owned by $account */
    public function isFSOwnedBy(Account $account) : bool
    {
        return $this->storage->GetObject()->TryGetOwnerID() === $account->ID();
    }
    
    public function SetParent(Folder $folder, bool $overwrite = false) : bool                       { throw new Exceptions\InvalidRootOpException(); }
    public function CopyToName(?Account $owner, string $name, bool $overwrite = false) : static     { throw new Exceptions\InvalidRootOpException(); }
    public function CopyToParent(?Account $owner, Folder $folder, bool $overwrite = false) : static { throw new Exceptions\InvalidRootOpException(); }
    
    public static function NotifyCreate(ObjectDatabase $database, Folder $parent, ?Account $account, string $name) : static { throw new Exceptions\InvalidRootOpException(); }

    /**
     * Loads the root folder for given account and FS, creating it if it doesn't exist
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner of the root folder
     * @param Storage $storage the filesystem of the root, or null to get the default
     * @return ?static loaded folder or null if a default FS does not exist and none is given
     */
    public static function GetRootByAccountAndFS(ObjectDatabase $database, Account $account, ?Storage $storage = null) : ?static
    {
        $storage ??= Storage::LoadDefaultByAccount($database, $account); 
        if ($storage === null) return null;

        // TODO RAY check this still makes sense with the new schema
        $q = new QueryBuilder(); 
        $where = $q->And($q->Equals('storage',$storage->ID()), $q->IsNull('parent'),
            $q->Or($q->IsNull('owner'),$q->Equals('owner',$account->ID())));
        
        $loaded = $database->TryLoadUniqueByQuery(static::class, $q->Where($where));
        if ($loaded !== null) return $loaded;
        else
        {
            $obj = $database->CreateObject(static::class); // insert new root
            $obj->storage->SetObject($storage);

            $owner = $storage->isExternal() ? $storage->TryGetOwner() : $account;
            $obj->owner->SetObject($owner);

            $obj->Refresh();
            return $obj;
        }
    }
    
    /**
     * Loads all root folders on the given filesystem
     * @param ObjectDatabase $database database reference
     * @param Storage $storage the filesystem
     * @return array<string, RootFolder> folders indexed by ID
     */
    public static function LoadRootsByStorage(ObjectDatabase $database, Storage $storage) : array
    {
        $q = new QueryBuilder(); 
        $q->Where($q->And($q->Equals('storage',$storage->ID()), $q->IsNull('parent')));
        return $database->LoadObjectsByQuery(static::class, $q);
    }
    
    /**
     * Load all root folders for the given owner
     * @param ObjectDatabase $database database reference
     * @param Account $account folder owner
     * @return array<string, RootFolder> folders indexed by ID
     */
    public static function LoadRootsByAccount(ObjectDatabase $database, Account $account) : array
    {
        $q = new QueryBuilder();
        $q->Where($q->And($q->Equals('owner',$account->ID()), $q->IsNull('parent')));
        return $database->LoadObjectsByQuery(static::class, $q);
    }

    //TODO RootFolder - DeleteRoots is dumb, shouldn't load first, also Delete() should be the one to decide notify/not based on external!
    /** Deletes all root folders on the given filesystem - if the FS is external or $unlink, only remove DB objects */
    public static function DeleteRootsByStorage(ObjectDatabase $database, Storage $storage, bool $unlink = false) : void
    {
        $unlink = $storage->isExternal() || $unlink;
        $roots = static::LoadRootsByStorage($database, $storage);
        
        foreach ($roots as $folder)
        {
            if ($unlink) 
                $folder->NotifyFSDeleted(); 
            else $folder->Delete();
        }
    }
    
    /** Deletes all root folders for the given owner - if the FS is external, only remove DB objects */
    public static function DeleteRootsByAccount(ObjectDatabase $database, Account $account) : void
    {
        foreach (static::LoadRootsByAccount($database, $account) as $folder)
        {
            if ($folder->storage->GetObject()->isExternal())
                $folder->NotifyFSDeleted(); 
            else $folder->Delete();
        }
    }
    
    /** Deletes the folder and its contents from DB and disk */
    public function Delete() : void
    {
        $this->DeleteChildren();
        // no FS DeleteFolder on root

        parent::Delete();
    }
}
