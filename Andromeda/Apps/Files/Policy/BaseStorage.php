<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; //if (!defined('Andromeda')) die(); // TODO FUTURE phpstan bug

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Apps\Files\Storage\Storage;

trait BaseStorage
{
    /** 
     * The storage that this policy is for 
     * @var FieldTypes\ObjectRefT<Storage>
     */
    protected FieldTypes\ObjectRefT $storage;
    /** Whether or not tracking item counts is enabled (enum) */
    protected FieldTypes\NullBoolType $track_items;
    /** Whether or not tracking download stats is enabled (enum) */
    protected FieldTypes\NullBoolType $track_dlstats;

    protected function BaseStorageCreateFields() : void
    {
        $fields = array();
        $this->storage = $fields[] = new FieldTypes\ObjectRefT(Storage::class,'storage');
        $this->track_items = $fields[] = new FieldTypes\NullBoolType('track_items');
        $this->track_dlstats = $fields[] = new FieldTypes\NullBoolType('track_dlstats');
        $this->RegisterChildFields($fields);
    }
    
    protected function canTrackItems() : bool { return $this->track_items->TryGetValue() ?? false; }
    protected function canTrackDLStats() : bool { return $this->track_dlstats->TryGetValue() ?? false; }
}
