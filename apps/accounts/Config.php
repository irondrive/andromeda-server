<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php");
require_once(ROOT."/apps/accounts/auth/Manager.php");

require_once(ROOT."/core/Config.php"); use Andromeda\Core\DBVersion;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** App config stored in the database */
class Config extends DBVersion
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__createaccount' => new FieldTypes\Scalar(0),
            'features__requirecontact' => new FieldTypes\Scalar(0),
            'features__usernameiscontact' => new FieldTypes\Scalar(false),
            'default_group' => new FieldTypes\ObjectRef(Group::class),
            'default_auth' => new FieldTypes\ObjectRef(Auth\Manager::class)
        ));
    }
    
    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : self 
    { 
        return parent::BaseCreate($database)->setVersion(AccountsApp::getVersion());
    }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return "[--createaccount ".implode('|',array_keys(self::CREATE_TYPES))."] ".
                                                                 "[--requirecontact ".implode('|',array_keys(self::CONTACT_TYPES))."] ".
                                                                 "[--usernameiscontact bool] [--createdefgroup bool] [--default_auth ?id]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('createaccount')) 
        {
            $param = $input->GetParam('createaccount',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL, 
                function($v){ return array_key_exists($v, self::CREATE_TYPES); });

            $this->SetFeature('createaccount', self::CREATE_TYPES[$param]);
        }
        
        if ($input->HasParam('requirecontact')) 
        {
            $param = $input->GetParam('requirecontact',SafeParam::TYPE_ALPHANUM, SafeParams::PARAMLOG_ONLYFULL,
                function($v){ return array_key_exists($v, self::CONTACT_TYPES); });

            $this->SetFeature('requirecontact', self::CONTACT_TYPES[$param]);
        }
        
        if ($input->HasParam('usernameiscontact')) $this->SetFeature('usernameiscontact',$input->GetParam('usernameiscontact',SafeParam::TYPE_BOOL));
        
        if ($input->GetOptParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        if ($input->HasParam('default_auth'))
        {
            if (($id = $input->GetNullParam('default_auth',SafeParam::TYPE_RANDSTR)) !== null)
            {
                $manager = Auth\Manager::TryLoadByID($this->database, $id);
                if ($manager === null) throw new UnknownAuthSourceException();
            }
            else $manager = null;
            
            $this->SetDefaultAuth($manager);
        }
        
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
    
    /** Returns the auth manager that will be used by default */
    public function GetDefaultAuth() : ?Auth\Manager { return $this->TryGetObject('default_auth'); }
    
    /** Returns the ID of the auth manager that will be used by default */
    public function GetDefaultAuthID() : ?string { return $this->TryGetObjectID('default_auth'); }

    /** Sets the default auth manager to the given value */
    public function SetDefaultAuth(?Auth\Manager $manager) : self { return $this->SetObject('default_auth',$manager); }
    
    public const CREATE_WHITELIST = 1; public const CREATE_PUBLIC = 2;
    
    const CREATE_TYPES = array('disable'=>0, 'whitelist'=>self::CREATE_WHITELIST, 'public'=>self::CREATE_PUBLIC);
    
    /** Returns whether the API for creating new accounts is enabled */
    public function GetAllowCreateAccount() : int { return $this->GetFeature('createaccount'); }
    
    /** Returns whether emails should be used as usernames */
    public function GetUsernameIsContact() : bool  { return $this->GetFeature('usernameiscontact'); }
    
    /** Sets whether the API for creating new accounts is enabled */
    public function SetAllowCreateAccount(int $value, bool $temp = false) : self { return $this->SetFeature('createaccount', $value, $temp); }
    
    /** Sets whether emails should be used as usernames */
    public function SetUsernameIsContact(bool $value, bool $temp = false) : self { return $this->SetFeature('usernameiscontact', $value, $temp); }
    
    public const CONTACT_EXIST = 1; public const CONTACT_VALID = 2;
    
    const CONTACT_TYPES = array('none'=>0, 'exist'=>self::CONTACT_EXIST, 'valid'=>self::CONTACT_VALID);
    
    /** Returns whether a contact for accounts is required or validated */
    public function GetRequireContact() : int { return $this->GetFeature('requirecontact'); }
    
    /* Sets whether a contact for accounts is required or validated */
    public function SetRequireContact(int $value, bool $temp = false) : self { return $this->SetFeature('requirecontact', $value, $temp); }
     
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array `{features:{createaccount:string, usernameiscontact:bool, requirecontact:string}}` \
         if admin, add: `{default_group:?id}`
     */
    public function GetClientObject(bool $admin) : array
    {
        $data = array(
            'features' => $this->GetAllFeatures(),
            'default_auth' => $this->GetDefaultAuthID()
        );
        
        $data['features']['createaccount'] = array_flip(self::CREATE_TYPES)[$this->GetAllowCreateAccount()];
        $data['features']['requirecontact'] = array_flip(self::CONTACT_TYPES)[$this->GetRequireContact()];
        
        if ($admin) $data['default_group'] = $this->GetDefaultGroupID();
        
        return $data;
    }
}