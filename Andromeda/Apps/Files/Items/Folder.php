<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Items; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\Database\Exceptions\BadPolyClassException;
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Policy;

/** 
 * Defines a user-stored folder which groups other items 
 *
 * Folders keep recursive running counts of most statistics like number 
 * of subfiles, total folder size, etc., so retrieving these values is fast.
 * 
 * Every folder must have a name and exactly one parent, other than roots which have neither.
 * 
 * @phpstan-import-type ItemJ from Item
 * @phpstan-import-type FileJ from File
 *     NOTE we use ItemJ for folders because phpstan doesn't like circular definitions
 * @phpstan-type FolderJ \Union<ItemJ, array{isdir:true, total_size:int, count_subfiles:int, count_subfolders:int, files?:array<string, FileJ>, folders?:array<string, ItemJ>}>
 */
abstract class Folder extends Item
{
    use TableTypes\HasTable;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(RootFolder::class, SubFolder::class); }

    public static function HasTypedRows() : bool { return true; }

    public static function GetWhereChild(ObjectDatabase $database, QueryBuilder $q, string $class) : string
    {
        $table = $database->GetClassTableName(Item::class);
        return match($class)
        {
            RootFolder::class => $q->IsNull("$table.parent",false),
            SubFolder::class => $q->Not($q->IsNull("$table.parent",false)),
            default => throw new BadPolyClassException($class)
        };
    }
    
    /** @return class-string<self> child class of row */
    public static function GetRowClass(ObjectDatabase $database, array $row) : string
    {
        return ($row['parent'] === null) ? RootFolder::class : SubFolder::class;
    }

    /** The total disk space size of the folder (bytes) */
    protected FieldTypes\Counter $count_size;
    /** Running count of subfiles in this folder */
    protected FieldTypes\Counter $count_subfiles;
    /** Running count of subfolders in this folder */
    protected FieldTypes\Counter $count_subfolders;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->count_size = $fields[] = new FieldTypes\Counter('count_size');
        $this->count_subfiles = $fields[] = new FieldTypes\Counter('count_subfiles');
        $this->count_subfolders = $fields[] = new FieldTypes\Counter('count_subfolders');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    public function PostConstruct() : void
    {
        if (!$this->isCreated())
            $this->GetFilesystem()->RefreshFolder($this, doContents:false);
    }
    
    /**
     * Returns an array of the files in this folder (not recursive)
     * @param ?non-negative-int $limit the max number of files to load
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, File> files indexed by ID
     */
    public function GetFiles(?int $limit = null, ?int $offset = null) : array
    { 
        return File::LoadByParent($this->database, $this, $limit, $offset);
    }
        
    /**
     * Returns an array of the folders in this folder (not recursive)
     * @param ?non-negative-int $limit the max number of folders to load
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, SubFolder> folders indexed by ID
     */
    public function GetFolders(?int $limit = null, ?int $offset = null) : array
    { 
        return SubFolder::LoadByParent($this->database, $this, $limit, $offset);
    }
    
    /** 
     * Returns the size of this folder in bytes 
     * @return non-negative-int
     */
    public function GetTotalSize() : int
    {
        $size = $this->count_size->GetValue();
        assert ($size >= 0); // DB CHECK CONSTRAINT
        return $size;
    }
    
    /** Returns the number of files in this folder (not recursive) (fast) */
    public function GetNumFiles() : int { return $this->count_subfiles->GetValue(); }
    
    /** Returns the number of folders in this folder (not recursive) (fast) */
    public function GetNumFolders() : int { return $this->count_subfolders->GetValue(); }
    
    /** Returns the number of items in this folder (not recursive) (fast) */
    public function GetNumItems() : int { return $this->GetNumFiles() + $this->GetNumFolders(); }
    
    /** Increments the size of this folder and parents by the given #bytes */
    public function DeltaSize(int $size) : void
    { 
        if (($parent = $this->TryGetParent()) !== null)
            $parent->DeltaSize($size);
        $this->count_size->DeltaValue($size); 
    }
    
    /** @param bool $recursive if true, set all children of this folder also */
    public function SetOwner(Account $account, bool $recursive = true) : bool
    {
        $retval = parent::SetOwner($account);
        if ($recursive)
        {
            foreach ($this->GetFiles() as $file) $file->SetOwner($account);
            foreach ($this->GetFolders() as $folder) $folder->SetOwner($account,recursive:true);
        }
        return $retval;
    }
    
    /** 
     * Asserts that $this is not the given folder, or any of its parents 
     * i.e. that $folder is not equal to $this, or any of its children
     * @throws Exceptions\InvalidFolderParentException if the check fails
     */
    protected function AssertNotChildOrSelf(Folder $folder) : void
    {
        do { if ($folder === $this)
                throw new Exceptions\InvalidFolderParentException(); }
        while (($folder = $folder->TryGetParent()) !== null);
    }
    
    /**
     * Adds the statistics from the given file to this folder
     * @param File $file the file to add stats from
     * @param bool $add if true add, else subtract
     */
    public function AddFileCounts(File $file, bool $add = true) : void
    {
        $this->SetModified(); // TODO RAY !! seems like a dumb place to do this?

        $val = $add ? 1 : -1;    
        $this->count_size->DeltaValue($file->GetSize() * $val);    
        $this->count_subfiles->DeltaValue($val);

        if (($parent = $this->TryGetParent()) !== null) 
            $parent->AddFileCounts($file, $add);
    }
    
    /**
     * Adds the statistics from the given folder to this folder (all substats)
     * @param Folder $folder the folder to add stats from
     * @param bool $add if true add, else subtract
     */
    public function AddFolderContentCounts(Folder $folder, bool $add = true) : void
    {
        $this->SetModified(); // TODO RAY !! seems like a dumb place to do this?
        
        $val = $add ? 1 : -1;    
        $this->count_size->DeltaValue($folder->GetTotalSize() * $val);    
        $this->count_subfiles->DeltaValue($folder->GetNumFiles()*$val);
        $this->count_subfolders->DeltaValue($folder->GetNumFolders()*$val + $val); // +self

        if (($parent = $this->TryGetParent()) !== null) 
            $parent->AddFolderContentCounts($folder, $add);
    }

    protected function AddCountsToPolicy(Policy\Base $policy, bool $add = true) : void { $policy->AddFolderContentCounts($this, $add); }

    protected function AddCountsToParent(Folder $folder, bool $add = true) : void { $folder->AddFolderContentCounts($this, $add); }
    
    /** True if the folder's contents have been refreshed */
    protected bool $subrefreshed = false;
    
    /**
     * Refreshes the folder's metadata from disk
     * @param bool $doContents if true, refresh all contents of the folder
     */
    protected function Refresh(bool $doContents = false) : void
    {
        if (!$this->refreshed || (!$this->subrefreshed && $doContents)) 
        {
            $this->refreshed = true;
            $this->subrefreshed = $doContents;
            $this->GetFilesystem()->RefreshFolder($this, doContents:$doContents);
        }
    }
    
    public function NotifyPreDeleted() : void
    {
        parent::NotifyPreDeleted();

        if (!$this->isFSDeleted())
            $this->Refresh(doContents:true);

        File::DeleteByParent($this->database, $this);
        SubFolder::DeleteByParent($this->database, $this);
    }

    /**
     * Recursively lists subitems in this folder
     * @param bool $files if true, load files
     * @param bool $folders if true, load folders
     * @param ?non-negative-int $limit max number of items to load
     * @param ?non-negative-int $offset offset of items to load
     * @return array<string, Item> items indexed by ID
     */
    private function RecursiveItems(?bool $files = true, ?bool $folders = true, ?int $limit = null, ?int $offset = null) : array
    {
        $items = array();
        // TODO FUTURE implement limit/offset somehow
        $items = $this->GetFiles();
        foreach ($this->GetFolders() as $sid=>$subfolder)
        {
            $items[$sid] = $subfolder;
            $items += $subfolder->RecursiveItems($files, $folders);
        }
        return $items;
    }

    /**
     * Returns a printable client object of this folder
     * @param bool $files if true, show subfiles
     * @param bool $folders if true, show subfolders
     * @param bool $recursive if true, show recursive contents
     * @param ?non-negative-int $limit max number of items to show
     * @param ?non-negative-int $offset offset of items to show
     * @return FolderJ
     */
    public function GetClientObject(bool $owner, bool $details = false,
        bool $files = false, bool $folders = false, bool $recursive = false, 
        ?int $limit = null, ?int $offset = null) : array
    {
        $data = parent::GetClientObject($owner,$details);
        if ($files || $folders) $this->SetAccessed();
        
        if ($recursive && ($files || $folders))
        {
            $items = $this->RecursiveItems($files,$folders,$limit,$offset);
            
            if ($folders) $subfolders = array_filter($items, function($item){ return ($item instanceof Folder); });            
            if ($files) $subfiles = array_filter($items, function($item){ return ($item instanceof File); });
        }
        else
        {
            if ($folders)
            {
                $subfolders = $this->GetFolders($limit,$offset);
                if ($offset !== null) $offset = max(0, $offset-count($subfolders));
                if ($limit !== null) $limit = max(0, $limit-count($subfolders));
            }
            
            if ($files)
                $subfiles = $this->GetFiles($limit,$offset);
        }        
        
        $data += array(
            'isdir' => true,
            'total_size' => $this->count_size->GetValue(),
            'count_subfolders' => $this->count_subfolders->GetValue(),
            'count_subfiles' => $this->count_subfiles->GetValue()
        );
        
        if (isset($subfolders)) $data['folders'] = array_filter(array_map(function(Folder $folder)use($owner){ 
            return $folder->GetClientObject(owner:$owner, details:false); }, $subfolders));
        
        if (isset($subfiles)) $data['files'] = array_filter(array_map(function(File $file)use($owner){ 
            return $file->GetClientObject(owner:$owner, details:false); }, $subfiles));

        return $data;
    }
}
