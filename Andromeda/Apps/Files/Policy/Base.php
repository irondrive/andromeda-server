<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, ObjectDatabase, TableTypes, QueryBuilder};
use Andromeda\Apps\Files\Items\{File, Folder};

/** 
 * Common code for standard and periodic policy 
 * Not using an actual table for this, to reduce JOINs...  // TODO RAY copy over comments from old code as needed
 */
abstract class Base extends BaseObject
{
    use TableTypes\NoTypedChildren;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(Standard::class, Periodic::class); }

    /** 
     * Returns all available policy auth objects
     * @param ?non-negative-int $limit limit number of objects
     * @param ?non-negative-int $offset offset of limited result set
     * @return array<string, static>
     */
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array
    {
        $q = (new QueryBuilder())->Limit($limit)->Offset($offset);
        return $database->LoadObjectsByQuery(static::class, $q); // empty query
    }

    /** Returns true if we should track size, item count, and share count */
    protected abstract function canTrackItems() : bool;
    
    /** Returns true if we should count public downloads and bandwidth */
    protected abstract function canTrackDLStats() : bool;

    /** Returns the size counter for the limited object */
    public abstract function GetSize() : int;
    
    /** Checks if the given size delta would exceed the size limit */
    public abstract function AssertSize(int $delta) : void; 
    // TODO RAY add @throws to Check and Count functions
    
    /** Adds to the size counter, if item tracking is allowed */
    public abstract function CountSize(int $delta, bool $noLimit = false) : void;

    /** Returns the item counter for the limited object */
    public abstract function GetItems() : int;
    
    /** Increments the item counter by the given value, if item tracking is allowed */
    public abstract function CountItems(int $delta = 1, bool $noLimit = false) : void;
    
    /** Returns the public downloads counter for the limited object */
    public abstract function GetPublicDownloads() : int;
    
    /** Increments the public download counter by the given value, if DL tracking is allowed */
    public abstract function CountPublicDownloads(int $delta = 1, bool $noLimit = false) : void;
        
    /** Returns the bandwidth counter for the limited object */
    public abstract function GetBandwidth() : int;
    
    /** Checks if the given bandwidth would exceed the limit */
    public abstract function AssertBandwidth(int $delta) : void;  
    
    /** Adds to the bandwidth counter, if download tracking is allowed */
    public abstract function CountBandwidth(int $delta, bool $noLimit = false) : void;
    
    /** Adds stats from the given file to this limit object */
    public function AddFileCounts(File $file, bool $add = true, bool $noLimit = false) : void
    {
        $mul = $add ? 1 : -1;
        if ($this->canTrackItems())
        {
            $this->CountItems($mul,$noLimit);

            if (!$file->onOwnerStorage()) // TODO seems like we should not do this check? well, for filesystems it should apply, but for the account limit it should not
                $this->CountSize($mul*$file->GetSize(),$noLimit);
        }
    }
    
    /** Adds stats from the given folder to this limit object */
    public function AddFolderCounts(Folder $folder, bool $add = true, bool $noLimit = false) : void
    {
        $mul = $add ? 1 : -1;
        if ($this->canTrackItems())
        {
            $this->CountItems($mul,$noLimit); 
        }
    }
    
    /** Adds cumulative stats from the given folder's contents to this limit object */
    public function AddFolderContentCounts(Folder $folder, bool $add = true, bool $noLimit = false) : void
    {
        $mul = $add ? 1 : -1;
        if ($this->canTrackItems())
        {
            $this->CountSize($mul*$folder->GetSize(),$noLimit);
            $this->CountItems($mul*$folder->GetNumItems(),$noLimit);
        }
    }
}