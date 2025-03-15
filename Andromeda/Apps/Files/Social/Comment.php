<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Social; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Items\Item;

/** A user comment on an item */
class Comment extends BaseObject // TODO was StandardObject
{
    protected const IDLength = 16;
    
    use TableTypes\TableNoChildren;

    /** 
     * The account that created this comment
     * @var FieldTypes\ObjectRefT<Account>
     */
    protected FieldTypes\ObjectRefT $owner;
    /**
     * The item that this comment refers to
     * @var FieldTypes\ObjectRefT<Item>
     */
    protected FieldTypes\ObjectRefT $item;
    /** The string value of the comment text */
    protected FieldTypes\StringType $value;
    /** The date this comment was created */
    protected FieldTypes\Timestamp $date_created;
    /** The date this comment was last modified */
    protected FieldTypes\NullTimestamp $date_modified;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->owner = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'owner');
        $this->item = $fields[] = new FieldTypes\ObjectRefT(Item::class, 'item');
        $this->value = $fields[] = new FieldTypes\StringType('value');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->date_modified = $fields[] = new FieldTypes\NullTimestamp('date_modified');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** Sets the value of this comment and updates the modified date */
    public function SetComment(string $val) : void
    { 
        $this->value->SetValue($val);
        $this->date_modified->SetTimeNow();
    }
    
    /**
     * Creates a new comment on an item
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner creating the tag
     * @param Item $item item being tagged
     * @param string $comment the text value of the comment
     * @return static new comment object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $comment) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->owner->SetObject($owner);
        $obj->item->SetObject($item);
        $obj->value->SetValue($comment);
        return $obj;
    }
    
    /** Tries to load a comment object by the given account and comment ID */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?static
    {
        $q = new QueryBuilder(); 
        $q->Where($q->And($q->Equals('owner',$account->ID()),$q->Equals('id',$id)));
        return $database->TryLoadUniqueByQuery(static::class, $q);
    }

    /**
     * Load all comments for the given item
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, static>
     */
    public static function LoadByItem(ObjectDatabase $database, Item $item, ?int $limit = null, ?int $offset = null) : array
    {
        return $database->LoadObjectsByKey(static::class, 'item', $item->ID(), $limit, $offset);
    }
    
    /**
     * Returns a printable client object of this comment
     * @return array{} `{id:id,owner:id,item:id,comment:string,dates{created:float,modified:?float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            /*'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'comment' => $this->GetScalar('comment'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'modified' => $this->TryGetDate('modified')
            ),*/
        );
    }
}
