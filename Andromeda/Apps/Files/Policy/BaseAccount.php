<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

//use Andromeda\Core\Database\FieldTypes;
//use Andromeda\Apps\Accounts\Account;
// TODO RAY see phpstan bug about use

trait BaseAccount
{
    /** 
     * The account that this policy is for 
     * @var \Andromeda\Core\Database\FieldTypes\ObjectRefT<\Andromeda\Apps\Accounts\Account>
     */
    protected \Andromeda\Core\Database\FieldTypes\ObjectRefT $account;
    /** Whether or not tracking item counts is enabled (enum) */
    protected \Andromeda\Core\Database\FieldTypes\NullBoolType $track_items;
    /** Whether or not tracking download stats is enabled (enum) */
    protected \Andromeda\Core\Database\FieldTypes\NullBoolType $track_dlstats;

    protected function BaseAccountCreateFields() : void
    {
        $fields = array();
        $this->account = $fields[] = new \Andromeda\Core\Database\FieldTypes\ObjectRefT(\Andromeda\Apps\Accounts\Account::class,'account');
        $this->track_items = $fields[] = new \Andromeda\Core\Database\FieldTypes\NullBoolType('track_items');
        $this->track_dlstats = $fields[] = new \Andromeda\Core\Database\FieldTypes\NullBoolType('track_dlstats');
        $this->RegisterChildFields($fields);
    }

    protected function canTrackItems() : bool { return $this->track_items->TryGetValue() ?? false; }
    protected function canTrackDLStats() : bool { return $this->track_dlstats->TryGetValue() ?? false; }
}
