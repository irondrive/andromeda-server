<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Social; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Files\Items\Item;

/** 
 * A user-like (or dislike) on an item 
 * 
 * These are tracked per-like rather than as just counters
 * on items to prevent duplicates (and show who liked what)
 * 
 * @phpstan-import-type PublicAccountJ from Account
 * @phpstan-type LikeJ array{owner:PublicAccountJ, item:string, value:bool, date_created:float}
 */
class Like extends BaseObject
{
    protected const IDLength = 16;

    use TableTypes\TableNoChildren;

    /** 
     * The account that created this like
     * @var FieldTypes\ObjectRefT<Account>
     */
    protected FieldTypes\ObjectRefT $owner;
    /**
     * The item that this like refers to
     * @var FieldTypes\ObjectRefT<Item>
     */
    protected FieldTypes\ObjectRefT $item;
    /** True if it's a like, false if it's a dislike */
    protected FieldTypes\BoolType $value;
    /** The date this like was created */
    protected FieldTypes\Timestamp $date_created;

    protected function CreateFields(): void
    {
        $fields = array();
        $this->owner = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'owner');
        $this->item = $fields[] = new FieldTypes\ObjectRefT(Item::class, 'item');
        $this->value = $fields[] = new FieldTypes\BoolType('value');
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /**
     * Likes an item by creating or updating a like object
     * @param ObjectDatabase $database database reference
     * @param Account $owner the person doing the like
     * @param Item $item the item being liked
     * @param bool $value true if like, false if dislike, null to unset
     * @return ?static like object if $value is not null
     */
    public static function CreateOrUpdate(ObjectDatabase $database, Account $owner, Item $item, ?bool $value) : ?static
    {
        $q = new QueryBuilder(); 
        $q->Where($q->And($q->Equals('owner',$owner->ID()),$q->Equals('item',$item->ID())));
        
        // load an existing like (can only like an item once)
        $obj = $database->TryLoadUniqueByQuery(static::class, $q);
        
        // create a new one if it doesn't exist
        if ($obj === null)
        {
            $obj = $database->CreateObject(static::class);
            $obj->owner->SetObject($owner);
            $obj->item->SetObject($item);
        }
        
        if ($value !== null)
        {
            $obj->date_created->SetTimeNow();
            $obj->value->SetValue($value);
            return $obj;
        }
        else { $obj->Delete(); return null; }
    }
    
    /**
     * Counts all likes for the given item
     * @param ?bool $likeval if not null, count only this value (like vs. dislike)
     */
    public static function CountByItem(ObjectDatabase $database, Item $item, ?bool $likeval = null) : int
    {
        if ($likeval === null)
            return $database->CountObjectsByKey(static::class, 'item', $item->ID());
        else
        {
            $q = new QueryBuilder();
            $q->Where($q->And($q->Equals('item',$item->ID()),$q->Equals('value',$likeval)));
            return $database->CountObjectsByQuery(static::class, $q);
        }
    }

    /**
     * Load all likes for the given item
     * @param ?non-negative-int $limit the max number of files to load 
     * @param ?non-negative-int $offset the offset to start loading from
     * @return array<string, static>
     */
    public static function LoadByItem(ObjectDatabase $database, Item $item, ?int $limit = null, ?int $offset = null) : array
    {
        return $database->LoadObjectsByKey(static::class, 'item', $item->ID(), $limit, $offset);
    }

    /**
     * Delete all likes for the given item
     * @return int
     */
    public static function DeleteByItem(ObjectDatabase $database, Item $item) : int
    {
        return $database->DeleteObjectsByKey(static::class, 'item', $item->ID());
    }

    /**
     * Returns a printable client object of this like
     * @return LikeJ
     */
    public function GetClientObject() : array
    {
        return array(
            'owner' => $this->owner->GetObject()->GetPublicClientObject(),
            'item' => $this->item->GetObjectID(),
            'value' => $this->value->GetValue(),
            'date_created' => $this->date_created->GetValue()
        );
    }
}
