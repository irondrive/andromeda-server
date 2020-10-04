<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php");

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\{SingletonObject, ClientObject};

class Config extends SingletonObject implements ClientObject
{
    public function GetDefaultGroup() : ?Group      { return $this->TryGetObject('default_group'); }
    public function GetAllowCreateAccount() : bool  { return $this->TryGetFeature('createaccount') ?? false; }
    public function GetUseEmailAsUsername() : bool  { return $this->TryGetFeature('emailasusername') ?? false; }
    
    public function SetDefaultGroup(?Group $group) : self     { return $this->SetObject('default_group', $group); }
    public function SetAllowCreateAccount(bool $allow) : self { return $this->SetFeature('createaccount', $allow); }
    public function SetUseEmailAsUsername(bool $useem) : self { return $this->SetFeature('emailasusername', $useem); }
    
    const CONTACT_NONE = 0; const CONTACT_EXIST = 1; const CONTACT_VALID = 2;
    
    public function GetRequireContact() : int          { return $this->TryGetFeature('requirecontact') ?? self::CONTACT_NONE; }
    public function SetRequireContact(int $req) : self { return $this->SetFeature('requirecontact', $req); }
    
    const OBJECT_SIMPLE = 0; const OBJECT_ADMIN = 1;
    
    public function GetClientObject(int $level = 0) : array
    {
        $data = array(
            'id' => $this->ID(),
            'features' => $this->GetAllFeatures(),
        );
        
        if ($level == self::OBJECT_ADMIN) $data['default_group'] = $this->GetDefaultGroup()->ID();
        
        return $data;
    }
}