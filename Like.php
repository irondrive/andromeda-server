<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

class Like extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'likes'),
            'value' => null
        ));
    }
    
    private static function Create(ObjectDatabase $database, Account $owner, Item $item) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item);
    }
    
    public static function CreateOrUpdate(ObjectDatabase $database, Account $owner, Item $item, int $value) : self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('owner',$owner->ID()),$q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)));
        $likeobj = static::LoadOneByQuery($database, $q->Where($where)) ?? static::Create($database, $owner, $item);
                
        $item->DiscountLike($likeobj->TryGetScalar('value')??0)->CountLike($value);

        $likeobj->SetScalar('value',$value)->SetDate('created');
        
        if (!$value) $likeobj->Delete();
        
        return $likeobj;
    }
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObject('owner')->GetDisplayName(),
            'item' => $this->GetObjectID('item'),
            'value' => $this->GetScalar('value'),
            'dates' => $this->GetAllDates()
        );
    }
}
