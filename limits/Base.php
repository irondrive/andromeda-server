<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

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

    protected abstract function Initialize() : self;    
    
    public abstract static function GetBaseUsage() : string;
    protected abstract function SetBaseLimits(Input $input) : void;
    
    public abstract static function BaseConfigUsage() : string;
    protected abstract static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input);
    
    public function GetLimitedObject() : StandardObject { return $this->GetObject('object'); }

    protected function canTrackItems() : bool { return $this->TryGetFeature('track_items') ?? false; }
    protected function canTrackDLStats() : bool { return $this->TryGetFeature('track_dlstats') ?? false; }
    
    public function CountDownload() : self           { return $this->canTrackDLStats() ? $this->DeltaCounter('downloads') : $this; }
    public function CountBandwidth(int $size) : self { return $this->canTrackDLStats() ? $this->DeltaCounter('bandwidth',$size) : $this; }    
    public function CountSize(int $size) : self      { return $this->canTrackItems() ? $this->DeltaCounter('size',$size) : $this; }
    public function CountItem(bool $count = true) : self      { return $this->canTrackItems() ? $this->DeltaCounter('items',$count?1:-1) : $this; }
    public function CountShare(bool $count = true) : self     { return $this->canTrackItems() ? $this->DeltaCounter('shares',$count?1:-1) : $this; }
    
    protected function CountItems(int $items) : self   { return $this->canTrackItems() ? $this->DeltaCounter('items',$items) : $this; }
    protected function CountShares(int $shares) : self { return $this->canTrackItems() ? $this->DeltaCounter('shares',$shares) : $this; }
    protected function CountDownloads(int $dls) : self { return $this->canTrackItems() ? $this->DeltaCounter('downloads',$dls) : $this; }
    
    protected function GetDownloads() : int { return $this->GetCounter('downloads'); }
    protected function GetBandwidth() : int { return $this->GetCounter('bandwidth'); }
    protected function GetSize() : int   { return $this->GetCounter('size'); }
    protected function GetItems() : int  { return $this->GetCounter('items'); }
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
    
    protected abstract static function GetObjectClass() : string;
    
    public static function LoadAll(ObjectDatabase $database, ?int $limit = null, ?int $offset = null) : array
    {
        $q = new QueryBuilder(); $w = $q->Like('object','%'.FieldTypes\ObjectPoly::GetIDTypeDBValue("",static::GetObjectClass()),true);
        
        return static::LoadByQuery($database, $q->Where($w)->Limit($limit)->Offset($offset));
    }
}
