<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

/** A user comment on an item */
class Comment extends StandardObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner'  => new FieldTypes\ObjectRef(Account::class),
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'comments'),
            'comment' => null,
            'dates__modified' => null
        ));
    }
    
    /** Sets the value of this comment and updates the modified date */
    public function SetComment(string $val){ return $this->SetScalar('comment', $val)->SetDate('modified'); }
    
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $comment) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item)->SetComment($comment);
    }
    
    /** Tries to load a comment object by the given account and comment ID */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $where = $q->And($q->Equals('owner',FieldTypes\ObjectPoly::GetObjectDBValue($account)),$q->Equals('id',$id));
        return static::TryLoadUniqueByQuery($database, $q->Where($where));
    }
    
    /**
     * Returns a printable client object of this comment
     * @return array `{id:id,owner:id,item:id,comment:string,dates{created:float,modified:?float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'owner' => $this->GetObject('owner'),
            'item' => $this->GetObjectID('item'),
            'comment' => $this->GetScalar('comment'),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'modified' => $this->TryGetDate('modified')
            ),
        );
    }
}
