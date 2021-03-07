<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php");

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

/** App config stored in the database */
class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__createaccount' => new FieldTypes\Scalar(false),
            'features__usernameiscontact' => new FieldTypes\Scalar(false),
            'features__requirecontact' => new FieldTypes\Scalar(0),
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        ));
    }
    
    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : self 
    { 
        return parent::BaseCreate($database);
    }

    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return "[--createaccount bool] [--usernameiscontact bool] [--requirecontact bool] [--createdefgroup bool]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('createaccount')) $this->SetFeature('createaccount',$input->GetParam('createaccount',SafeParam::TYPE_BOOL));
        if ($input->HasParam('usernameiscontact')) $this->SetFeature('usernameiscontact',$input->GetParam('usernameiscontact',SafeParam::TYPE_BOOL));
        if ($input->HasParam('requirecontact')) $this->SetFeature('requirecontact',$input->GetParam('requirecontact',SafeParam::TYPE_BOOL));
        
        if ($input->GetOptParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        return $this;
    }
    
    /** Returns the default group that all users are implicitly part of */
    public function GetDefaultGroup() : ?Group { return $this->TryGetObject('default_group'); }
    
    /** Returns the ID of the default group */
    public function GetDefaultGroupID() : ?string { return $this->TryGetObjectID('default_group'); }

    /** Creates a new default group whose implicit members are all accounts */
    private function CreateDefaultGroup() : self
    {
        if ($this->HasObject('default_group')) return $this;
        
        $group = Group::Create($this->database, "Global Group");
        $this->SetObject('default_group', $group); $group->Initialize(); return $this;
    }

    /** Returns whether the API for creating new accounts is enabled */
    public function GetAllowCreateAccount() : bool { return $this->GetFeature('createaccount'); }
    
    /** Returns whether emails should be used as usernames */
    public function GetUsernameIsContact() : bool  { return $this->GetFeature('usernameiscontact'); }
    
    /** Sets whether the API for creating new accounts is enabled */
    public function SetAllowCreateAccount(bool $value, bool $temp = false) : self { return $this->SetFeature('createaccount', $value, $temp); }
    
    /** Sets whether emails should be used as usernames */
    public function SetUsernameIsContact(bool $value, bool $temp = false) : self { return $this->SetFeature('usernameiscontact', $value, $temp); }
    
    const CONTACT_EXIST = 1; const CONTACT_VALID = 2;
    
    /** Returns whether a contact for accounts is required or validated */
    public function GetRequireContact() : int { return $this->GetFeature('requirecontact'); }
    
    /* Sets whether a contact for accounts is required or validated */
    public function SetRequireContact(int $value, bool $temp = false) : self { return $this->SetFeature('requirecontact', $value, $temp); }
     
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array `{features:{createaccount:bool, usernameiscontact:bool, requirecontact:bool}}` \
         if admin, add: `{default_group:?id}`
     */
    public function GetClientObject(bool $admin) : array
    {
        $data = array(
            'features' => $this->GetAllFeatures()
        );
        
        if ($admin) $data['default_group'] = $this->GetDefaultGroupID();
        
        return $data;
    }
}