<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; //if (!defined('Andromeda')) die(); // TODO FUTURE phpstan bug

interface IBaseGroup
{ 
    /** Track stats for component accounts by inheriting this property */
    public const TRACK_ACCOUNTS = 1;
    
    /** Track stats for components accounts and also the group as a whole */
    public const TRACK_WHOLE_GROUP = 2;
    
    public const TRACK_TYPES = array('none'=>0,
        'accounts'=>self::TRACK_ACCOUNTS, 
        'wholegroup'=>self::TRACK_WHOLE_GROUP);  
}

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Apps\Accounts\Group;

trait BaseGroup
{
    /** 
     * The group that this policy is for 
     * @var FieldTypes\ObjectRefT<Group>
     */
    protected FieldTypes\ObjectRefT $group;
    /** Whether or not tracking item counts is enabled (enum) */
    protected FieldTypes\NullIntType $track_items;
    /** Whether or not tracking download stats is enabled (enum) */
    protected FieldTypes\NullIntType $track_dlstats;

    protected function BaseGroupCreateFields() : void
    {
        $fields = array();
        $this->group = $fields[] = new FieldTypes\ObjectRefT(Group::class,'group');
        $this->track_items = $fields[] = new FieldTypes\NullIntType('track_items');
        $this->track_dlstats = $fields[] = new FieldTypes\NullIntType('track_dlstats');
        $this->RegisterChildFields($fields);
    }

    protected function canTrackItems() : bool { return ($this->track_items->TryGetValue() ?? 0) > 0; }
    protected function canTrackDLStats() : bool { return ($this->track_dlstats->TryGetValue() ?? 0) > 0; }
}
