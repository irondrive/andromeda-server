<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Apps/Accounts/Group.php");
require_once(ROOT."/Apps/Accounts/Auth/Manager.php");

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\VersionInfo;
require_once(ROOT."/Core/BaseConfig.php"); use Andromeda\Core\BaseConfig;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** App config stored in the database */
final class Config extends BaseConfig
{
    public static function getAppname() : string { return 'accounts'; }
    
    public static function getVersion() : string { 
        return VersionInfo::toCompatVer(andromeda_version); }
    
    use TableNoChildren;
    
    /** The setting for public account creation */
    private FieldTypes\IntType $create_account;
    /** The setting for requiring account contact info */
    private FieldTypes\IntType $require_contact;
    /** True if usernames are contact info (e.g. emails) */
    private FieldTypes\BoolType $username_isContact;
    /** @var FieldTypes\NullObjectRefT<Group> default group for new accounts */
    private FieldTypes\NullObjectRefT $default_group;
    /** @var FieldTypes\NullObjectRefT<Auth\Manager> default auth source to use */
    private FieldTypes\NullObjectRefT $default_auth;

    protected function CreateFields() : void
    {
        $fields = array();

        $this->create_account = $fields[] =     new FieldTypes\IntType('createaccount');
        $this->require_contact = $fields[] =    new FieldTypes\IntType('requirecontact');
        $this->username_isContact = $fields[] = new FieldTypes\BoolType('usernameiscontact');
        $this->default_group = $fields[]      = new FieldTypes\NullObjectRefT(Group::class, 'default_group');
        $this->default_auth = $fields[]       = new FieldTypes\NullObjectRefT(Auth\Manager::class, 'default_auth');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : self 
    { 
        return parent::BaseCreate($database);
    }
    
    /** Returns the string detailing the CLI usage for SetConfig */
    public static function GetSetConfigUsage() : string { return "[--createaccount ".implode('|',array_keys(self::CREATE_TYPES))."] ".
                                                                 "[--requirecontact ".implode('|',array_keys(self::CONTACT_TYPES))."] ".
                                                                 "[--usernameiscontact bool] [--createdefgroup bool] [--default_auth ?id]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(SafeParams $params) : self
    {
        if ($params->HasParam('createaccount')) 
        {
            $param = $params->GetParam('createaccount')->FromWhitelist(array_keys(self::CREATE_TYPES));
            $this->create_account->SetValue(self::CREATE_TYPES[$param]);
        }
        
        if ($params->HasParam('requirecontact')) 
        {
            $param = $params->GetParam('requirecontact')->FromWhitelist(array_keys(self::CONTACT_TYPES));
            $this->require_contact->SetValue(self::CONTACT_TYPES[$param]);
        }
        
        if ($params->HasParam('usernameiscontact')) 
        {
            $param = $params->GetParam('usernameiscontact')->GetBool();
            $this->username_isContact->SetValue($param);
        }
        
        if ($params->GetOptParam('createdefgroup',false)->GetBool()) 
            $this->CreateDefaultGroup();
        
        if ($params->HasParam('default_auth'))
        {
            if (($id = $params->GetParam('default_auth')->GetNullRandstr()) !== null)
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
    public function GetDefaultGroup() : ?Group { return $this->default_group->TryGetObject(); }
    
    /** Returns the ID of the default group */
    public function GetDefaultGroupID() : ?string { return $this->default_group->TryGetObjectID(); }

    /** Creates a new default group whose implicit members are all accounts */
    private function CreateDefaultGroup() : self
    {
        if ($this->default_group->TryGetObjectID() !== null) return $this;
        
        $group = Group::Create($this->database, "Global Group");
        $this->default_group->SetObject($group); // TODO need to save here?
        
        $group->Initialize(); return $this;
    }
    
    /** Returns the auth manager that will be used by default */
    public function GetDefaultAuth() : ?Auth\Manager { return $this->default_auth->TryGetObject(); }
    
    /** Returns the ID of the auth manager that will be used by default */
    public function GetDefaultAuthID() : ?string { return $this->default_auth->TryGetObjectID(); }

    /** Sets the default auth manager to the given value */
    public function SetDefaultAuth(?Auth\Manager $manager) : self { $this->default_auth->SetObject($manager); return $this; }
    
    /** Only allow creating accounts with whitelisted usernames */
    public const CREATE_WHITELIST = 1; 
    /** Allow anyone to create a new account (public) */
    public const CREATE_PUBLIC = 2;
    
    private const CREATE_TYPES = array('disable'=>0, 'whitelist'=>self::CREATE_WHITELIST, 'public'=>self::CREATE_PUBLIC);
    
    /** Returns whether the API for creating new accounts is enabled */
    public function GetAllowCreateAccount() : int { return $this->create_account->GetValue(); }
    
    /** Returns whether emails should be used as usernames */
    public function GetUsernameIsContact() : bool { return $this->username_isContact->GetValue(); }
    
    /** Sets whether the API for creating new accounts is enabled */
    public function SetAllowCreateAccount(int $value) : self { $this->create_account->SetValue($value); return $this; }
    
    /** Sets whether emails should be used as usernames */
    public function SetUsernameIsContact(bool $value) : self { $this->username_isContact->SetValue($value); return $this; }
    
    /** Require that accounts have contact info */
    public const CONTACT_EXIST = 1; 
    /** Require that accounts have validated contact info */
    public const CONTACT_VALID = 2;
    
    private const CONTACT_TYPES = array('none'=>0, 'exist'=>self::CONTACT_EXIST, 'valid'=>self::CONTACT_VALID);
    
    /** Returns whether a contact for accounts is required or validated */
    public function GetRequireContact() : int { return $this->require_contact->GetValue(); }
    
    /* Sets whether a contact for accounts is required or validated */
    public function SetRequireContact(int $value) : self { $this->require_contact->SetValue($value); return $this; }
     
    /**
     * Gets the config as a printable client object
     * @param bool $admin if true, show sensitive admin-only values
     * @return array `{create_account:enum, username_iscontact:bool, require_contact:enum, default_auth:?id}` \
         if admin, add: `{default_group:?id}`
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $data = array(
            'username_iscontact' => $this->username_isContact->GetValue(),
            'create_account' => array_flip(self::CREATE_TYPES)[$this->create_account->GetValue()],
            'require_contact' => array_flip(self::CONTACT_TYPES)[$this->require_contact->GetValue()],
            'default_auth' => $this->default_auth->TryGetObjectID()
        );
        
        if ($admin) $data['default_group'] = $this->default_group->TryGetObjectID();
        
        return $data;
    }
}
