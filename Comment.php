<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

class Comment extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner'  => new FieldTypes\ObjectRef(Account::class),
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'comments'),
            'comment' => null,
            'private' => null, // TODO not implemented
            'dates__modified' => null
        ));
    }
    
    public function SetComment(string $val){ return $this->SetScalar('comment', $val)->SetDate('modified'); }
    
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, string $comment, bool $private) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item)
            ->SetComment($comment)->SetScalar('private',$private);
    }
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $loaded = static::TryLoadByID($database, $id);
        if (!$loaded) return null;
        
        $owner = $loaded->GetObjectID('owner');
        return ($owner === null || $owner === $account->ID()) ? $loaded : null;
    }
    
    public function GetClientObject() : ?array
    {
        return array(
            'owner' => $this->GetObject('owner')->GetFullName(),
            'comment' => $this->GetScalar('comment'),
            'dates' => $this->GetAllDates()
        );
    }
}
