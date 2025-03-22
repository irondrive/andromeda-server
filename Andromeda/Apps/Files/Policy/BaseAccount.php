<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; //if (!defined('Andromeda')) die(); // TODO FUTURE phpstan bug

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Apps\Accounts\Account;

trait BaseAccount
{
    /** 
     * The account that this policy is for 
     * @var FieldTypes\ObjectRefT<Account>
     */
    protected FieldTypes\ObjectRefT $account;
    /** Whether or not tracking item counts is enabled (enum) */
    protected FieldTypes\NullBoolType $track_items;
    /** Whether or not tracking download stats is enabled (enum) */
    protected FieldTypes\NullBoolType $track_dlstats;

    protected function BaseAccountCreateFields() : void
    {
        $fields = array();
        $this->account = $fields[] = new FieldTypes\ObjectRefT(Account::class,'account');
        $this->track_items = $fields[] = new FieldTypes\NullBoolType('track_items');
        $this->track_dlstats = $fields[] = new FieldTypes\NullBoolType('track_dlstats');
        $this->RegisterChildFields($fields);
    }

    protected function canTrackItems() : bool { return $this->track_items->TryGetValue() ?? false; }
    protected function canTrackDLStats() : bool { return $this->track_dlstats->TryGetValue() ?? false; }
}
