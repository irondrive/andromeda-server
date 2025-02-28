<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes, FieldTypes};
use Andromeda\Apps\Accounts\Group;

class StandardGroup extends Standard implements IBaseGroup
{
    use BaseGroup, TableTypes\TableNoChildren;

    /** True if sending shares via server email is allowed */
    protected FieldTypes\NullBoolType $can_emailshare;
    /** True if creating user-defined storages is allowed */
    protected FieldTypes\NullBoolType $can_userstorage;

    public static function GetUniqueKeys() : array
    {
        $ret = parent::GetUniqueKeys();
        $ret[self::class][] = 'group';
        return $ret;
    }
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->can_emailshare = $fields[] = new FieldTypes\NullBoolType('can_emailshare');
        $this->can_userstorage = $fields[] = new FieldTypes\NullBoolType('can_userstorage');

        $this->RegisterFields($fields, self::class);
        $this->BaseGroupCreateFields();
        parent::CreateFields();
    }

    /** Loads the policy object corresponding to the given group */
    public static function TryLoadByGroup(ObjectDatabase $database, Group $group) : ?static
    {
        return $database->TryLoadUniqueByKey(static::class, 'group', $group->ID());
    }
    
    /** Deletes the policy object corresponding to the given group */
    public static function DeleteByGroup(ObjectDatabase $database, Group $group) : bool
    {
        return $database->TryDeleteUniqueByKey(static::class, 'group', $group->ID());
    }
}