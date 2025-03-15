<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Social; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Items\Item;

/** A category tag placed on an item */
class Tag extends BaseObject
{
    protected const IDLength = 16;

    use TableTypes\TableNoChildren;

    /** 
     * The account that created this tag
     * @var FieldTypes\ObjectRefT<Account>
     */
    protected FieldTypes\ObjectRefT $owner;
    /**
     * The item that this tag refers to
     * @var FieldTypes\ObjectRefT<Item>
     */
    protected FieldTypes\ObjectRefT $item;
    /** The string value of the tag */
    protected FieldTypes\StringType $value;
    /** The date this tag was created */
    protected FieldTypes\Timestamp $date_created;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->owner = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'owner');
        $this->item = $fields[] = new FieldTypes\ObjectRefT(Item::class, 'item');
        $this->value = $fields[] = new FieldTypes\StringType('value');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** Returns the owner for this tag */
    public function GetOwner() : Account { return $this->owner->GetObject(); }

    /** Returns the item for this tag */
    public function GetItem() : Item { return $this->item->GetObject(); }

    /** Returns the string value of this tag */
    public function GetValue() : string { return $this->value->GetValue(); }

    /**
     * Creates a new tag on an item
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner creating the tag
     * @param Item $item item being tagged
     * @param string $tag the text value of the tag
     * @return static new tag object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $tag) : static
    {
        $q = new QueryBuilder(); 
        $q->Where($q->And($q->Equals('item',$item->ID()),$q->Equals('value',$tag)));
        if (($obj = $database->TryLoadUniqueByQuery(static::class, $q)) !== null) return $obj;

        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->owner->SetObject($owner);
        $obj->item->SetObject($item);
        $obj->value->SetValue($tag);
        return $obj;
    }

    /**
     * Load all tags for the given item
     * @return array<string, static>
     */
    public static function LoadByItem(ObjectDatabase $database, Item $item) : array
    {
        return $database->LoadObjectsByKey(static::class, 'item', $item->ID());
    }

    /**
     * Returns a printable client object of this tag
     * @return array{} `{id:id, owner:id, item:id, tag:string, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            /*'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'tag' => $this->GetScalar('tag'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
            )*/
        );
    }
}
