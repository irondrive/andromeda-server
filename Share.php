<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/AuthObject.php"); use Andromeda\Apps\Accounts\AuthObject;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\AuthEntity;

class Share extends AuthObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'shares'),
            'dest' => new FieldTypes\ObjectPoly(AuthEntity::class),
            'authkey_nonce' => null,
            'dates__accessed' => null
        ));
    }
    
}
