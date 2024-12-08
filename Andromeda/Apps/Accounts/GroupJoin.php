<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{JoinObject, FieldTypes, ObjectDatabase, TableTypes};

/** 
 * Class representing a group membership, joining an account and a group
 * @phpstan-type GroupJoinJ array{date_created:float}
 */
class GroupJoin extends JoinObject
{
    use TableTypes\TableNoChildren;
    
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;

    /** @var FieldTypes\ObjectRefT<Account> */
    private FieldTypes\ObjectRefT $account;

    /** @var FieldTypes\ObjectRefT<Group> */
    private FieldTypes\ObjectRefT $group;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->account = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        $this->group = $fields[] = new FieldTypes\ObjectRefT(Group::class, 'group');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    public function GetAccount() : Account { return $this->account->GetObject(); }
    public function GetGroup() : Group { return $this->group->GetObject(); }

    /** @return array<string, Account> */
    public static function LoadAccounts(ObjectDatabase $database, Group $group)
    {
        return static::LoadFromJoin($database, Account::class, 'account', array('group'=>$group));
    }

    /** @return array<string, Group> */
    public static function LoadGroups(ObjectDatabase $database, Account $account)
    {
        return static::LoadFromJoin($database, Group::class, 'group', array('account'=>$account));
    }

    /** 
     * Deletes all group joins for the given account 
     * @return int the number of deleted group joins
     */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : int
    {
        return 0; // TODO RAY !! implement me
        //return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
    
    /** 
     * Deletes all group joins for the given group
     * @return int the number of deleted group joins
     */
    public static function DeleteByGroup(ObjectDatabase $database, Group $group) : int
    {
        return 0; // TODO RAY !! implement me
        //return $database->DeleteObjectsByKey(static::class, 'account', $account->ID());
    }
    
    // TODO RAY !! need to set date created in create

    /**
     * Returns a printable client object of this group membership
     * @return GroupJoinJ
     */
    public function GetClientObject()
    {
        return array(
            'date_created' => $this->date_created->GetValue()
        );
    }
}
