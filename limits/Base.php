<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/apps/files/Folder.php"); use Andromeda\Apps\Files\Folder;

/**
 * The base type that all limits inherit from.
 * 
 * Limits are a highly-configurable generic way to place restrictions on,
 * and gather statistics about, filesystem-related objects.  The object
 * that is the subject of the limits is referred to as the limited object.
 * 
 * The base class merely tracks some statistics (e.g. download count).
 * 
 * While not currently implemented, this infrastructure could be extended
 * to, for example, set limits on individual files and folders also.
 */
abstract class Base extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'object' => new FieldTypes\ObjectPoly(StandardObject::class),
            'features__track_items' => null,
            'features__track_dlstats' => null
        ));
    }

    /** Initializes the limit by gathering statistics */
    protected abstract function Initialize() : self;    
    
    /** Returns the command usage for SetBaseLimits() */
    public abstract static function GetBaseUsage() : string;
    
    /**
     * Sets the properties that apply to Base limits
     *
     * This is abstract since different concrete limit types may use them differently.
     */
    protected abstract function SetBaseLimits(Input $input) : void;
    
    /** Returns the command usage for BaseConfigLimits() */
    public abstract static function BaseConfigUsage() : string;
    
    /** Configures the common (at some level) limit properites for the given object with the given input */
    protected abstract static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input);
    
    /** Returns the object that is subject to the limits */
    public function GetLimitedObject() : StandardObject { return $this->GetObject('object'); }

    /** Returns true if we should track size, item count, and share count */
    protected function canTrackItems() : bool { return $this->TryGetFeature('track_items') ?? false; }
    
    /** Returns true if we should count downloads and bandwidth */
    protected function canTrackDLStats() : bool { return $this->TryGetFeature('track_dlstats') ?? false; }
    
    /** Increments the download counter, if download tracking is allowed */
    public function CountDownload() : self { return $this->canTrackDLStats() ? $this->DeltaCounter('downloads') : $this; }
    
    /** Adds to the bandwidth counter, if download tracking is allowed */
    public function CountBandwidth(int $delta) : self { return $this->canTrackDLStats() ? $this->DeltaCounter('bandwidth',$delta) : $this; }
    
    /**
     * Checks if the given bandwidth would exceed the limit
     * @see StandardObject::CheckCounter()
     */
    public function CheckBandwidth(int $delta) : void { $this->CheckCounter('bandwidth',$delta); }
    
    /** Adds to the size counter, if item tracking is allowed */
    public function CountSize(int $delta) : self { return $this->canTrackItems() ? $this->DeltaCounter('size',$delta) : $this; }
    
    /** 
     * Checks if the given size delta would exceed the size limit 
     * @see StandardObject::CheckCounter()
     */
    public function CheckSize(int $delta) : void { $this->CheckCounter('size',$delta); }
    
    /** Increments the item counter, if item tracking is allowed. Decrements if not $count */
    public function CountItem(bool $count = true) : self { return $this->canTrackItems() ? $this->DeltaCounter('items',$count?1:-1) : $this; }
    
    /** Increments the share counter, if item tracking is allowed. Decrements if not $count */
    public function CountShare(bool $count = true) : self { return $this->canTrackItems() ? $this->DeltaCounter('shares',$count?1:-1) : $this; }
    
    /** Increments the item counter by the given value, if item tracking is allowed */
    public function CountItems(int $items) : self   { return $this->canTrackItems() ? $this->DeltaCounter('items',$items) : $this; }
    
    /** Increments the share counter by the given value, if item tracking is allowed */
    public function CountShares(int $shares) : self { return $this->canTrackItems() ? $this->DeltaCounter('shares',$shares) : $this; }
    
    /** Increments the download counter by the given value, if item tracking is allowed */
    public function CountDownloads(int $dls) : self { return $this->canTrackItems() ? $this->DeltaCounter('downloads',$dls) : $this; }
    
    /** Adds stats from the given file to this limit object */
    public function AddFileCounts(File $file, bool $sub = false) : self
    {
        if (!$this->canTrackItems()) return $this;
        
        $mul = $sub ? -1 : 1;
        
        $this->CountItems($mul);
        $this->CountSize($mul*$file->GetSize());
        $this->CountShares($mul*$file->GetNumShares());
        return $this;
    }
    
    /** Adds stats from the given folder to this limit object */
    public function AddFolderCounts(Folder $folder, bool $sub = false) : self
    {
        if (!$this->canTrackItems()) return $this;
        
        $mul = $sub ? -1 : 1;
        
        $this->CountSize($mul*$folder->GetSize());
        $this->CountItems($mul*$folder->GetNumItems());
        $this->CountShares($mul*$folder->GetTotalShares());        
        return $this;
    }
    
    /** Returns the downloads counter for the limited object */
    protected function GetDownloads() : int { return $this->GetCounter('downloads'); }
    
    /** Returns the bandwidth counter for the limited object */
    protected function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    
    /** Returns the size counter for the limited object */
    protected function GetSize() : int   { return $this->GetCounter('size'); }
    
    /** Returns the item counter for the limited object */
    protected function GetItems() : int  { return $this->GetCounter('items'); }
    
    /** Returns the share counter for the limited object */
    protected function GetShares() : int { return $this->GetCounter('shares'); }  

    public function GetClientObject() : array
    {
        return array(
            'dates' => $this->GetAllDates(),
            'features' => $this->GetAllFeatures(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
        );
    }
    
    /** Returns the class of the limited object */
    protected abstract static function GetObjectClass() : string;

    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array
    {
        // since different limit types are stored in the same table, load rows matching the correct type of limited object
        $q = new QueryBuilder(); $w = $q->Like('object','%'.FieldTypes\ObjectPoly::GetIDTypeDBValue("",static::GetObjectClass()),true);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($limit)->Offset($offset));
    }
}
