<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** 
 * A user-like (or dislike) on an item 
 * 
 * These are tracked per-like rather than as just counters
 * on items to prevent duplicates (and show who liked what)
 */
class Like extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'likes'),
            'value' => null // true if this is a like, false if a dislike
        ));
    }

    /**
     * Likes an item by creating or updating a like object
     * @param ObjectDatabase $database database reference
     * @param Account $owner the person doing the like
     * @param Item $item the item being liked
     * @param bool $value true if like, false if dislike, null to unset
     * @return self|NULL like object if $value is not null
     */
    public static function CreateOrUpdate(ObjectDatabase $database, Account $owner, Item $item, ?bool $value) : ?self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('owner',$owner->ID()),$q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)));
        
        // load an existing like (can only like an item once)
        $likeobj = static::TryLoadUniqueByQuery($database, $q->Where($where));
        
        // create a new one if it doesn't exist
        $likeobj ??= parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item);
                
        // "un-count" the old like first
        $item->CountLike($likeobj->TryGetScalar('value') ?? 0, true);
        
        if ($value !== null)
        {
            $item->CountLike($value);
            
            return $likeobj->SetScalar('value',$value)->SetDate('created');
        }
        else { $likeobj->Delete(); return null; }
    }
    
    /**
     * Returns a printable client object of this like
     * @return array `{owner:id, item:id, value:bool, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'value' => $this->GetScalar('value'),
            'dates' => $this->GetAllDates()
        );
    }
}
