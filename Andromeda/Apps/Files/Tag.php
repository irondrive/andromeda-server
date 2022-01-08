<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** A category tag placed on an item */
class Tag extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_owner' => new FieldTypes\ObjectRef(Account::class),
            'obj_item' => new FieldTypes\ObjectPoly(Item::Class, 'tags'),
            'tag' => null // the text value of the tag
        ));
    }
    
    /** Returns the item for this tag */
    public function GetItem() : Item { return $this->GetObject('item'); }

    /**
     * Creates a new tag on an item
     * @param ObjectDatabase $database database reference
     * @param Account $owner owner creating the tag
     * @param Item $item item being tagged
     * @param string $tag the text value of the tag
     * @return static new tag object
     */
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $tag) : self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('obj_item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),$q->Equals('tag',$tag));
        if (($ex = static::TryLoadUniqueByQuery($database, $q->Where($where))) !== null) return $ex;
        
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item)->SetScalar('tag',$tag);
    }

    /**
     * Returns a printable client object of this tag
     * @return array `{id:id, owner:id, item:id, tag:string, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'tag' => $this->GetScalar('tag'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
            )
        );
    }
}
