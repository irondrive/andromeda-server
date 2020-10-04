<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoPublic};

class InheritedProperty 
{
    private $value; private $source;
    public function GetValue() { return $this->value; }
    public function GetSource() : ?AuthEntity { return $this->source; }
    public function __construct($value, ?AuthEntity $source){ 
        $this->value = $value; $this->source = $source; }
}

class GroupMembership extends StandardObject
{
    public function GetAccountID() : string { return $this->GetObjectID('account'); }
    public function GetGroupID() : string { return $this->GetObjectID('group'); }
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetGroup() : Group { return $this->GetObject('group'); }
    
    public static function TryLoadByAccountAndGroup(ObjectDatabase $database, Account $account, Group $group) : ?self
    {
        $found = array_values(self::LoadManyMatchingAll($database, array(
            'account*object*Apps\Accounts\Account*groups' => $account->ID(), // TODO framework should handle this
            'group*object*Apps\Accounts\Group*accounts' => $group->ID() )));
        
        if (count($found) >= 1) return $found[0]; else return null;
    }
    
    public static function Create(ObjectDatabase $database, Account $account, Group $group) : GroupMembership
    {
        $membership = parent::BaseCreate($database);
        
        $membership->SetObject('account',$account)->SetObject('group',$group);
        
        return $membership;
    }
    
}