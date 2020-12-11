<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Group.php");

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__createaccount' => new FieldTypes\Scalar(false),
            'features__emailasusername' => new FieldTypes\Scalar(false),
            'features__requirecontact' => new FieldTypes\Scalar(0),
            'features__allowcrypto' => new FieldTypes\Scalar(true),
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        ));
    }
    
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database)->CreateDefaultGroup(); }

    public static function GetSetConfigUsage() : string { return "[--createaccount bool] [--emailasusername bool] [--requirecontact bool] [--allowcrypto bool] [--createdefgroup bool]"; }
    
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('createaccount')) $this->SetFeature('createaccount',$input->GetParam('createaccount',SafeParam::TYPE_BOOL));
        if ($input->HasParam('emailasusername')) $this->SetFeature('randomwrite',$input->GetParam('emailasusername',SafeParam::TYPE_BOOL));
        if ($input->HasParam('requirecontact')) $this->SetFeature('requirecontact',$input->GetParam('requirecontact',SafeParam::TYPE_BOOL));
        if ($input->HasParam('allowcrypto')) $this->SetFeature('allowcrypto',$input->GetParam('allowcrypto',SafeParam::TYPE_BOOL));
        
        if ($input->TryGetParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        return $this;
    }
    
    public function GetDefaultGroup() : ?Group      { return $this->TryGetObject('default_group'); }
    public function GetDefaultGroupID() : ?string   { return $this->TryGetObjectID('default_group'); }
    
    public function CreateDefaultGroup() : self
    {
        if ($this->HasObject('default_group')) return $this;
        
        $group = Group::Create($this->database, "Global Group");
        return $this->SetObject('default_group', $group);
    }
    
    public function GetAllowCreateAccount() : bool  { return $this->GetFeature('createaccount'); }
    public function GetUseEmailAsUsername() : bool  { return $this->GetFeature('emailasusername'); }
    public function GetAllowCrypto() : bool         { return $this->GetFeature('allowcrypto'); }
    
    public function SetAllowCreateAccount(bool $allow) : self { return $this->SetFeature('createaccount', $allow); }
    public function SetUseEmailAsUsername(bool $useem) : self { return $this->SetFeature('emailasusername', $useem); }
    public function SetAllowCrypto(bool $allow) : self        { return $this->SetFeature('allowcrypto', $allow); }
    
    const CONTACT_EXIST = 1; const CONTACT_VALID = 2;
    
    public function GetRequireContact() : int          { return $this->GetFeature('requirecontact'); }
    public function SetRequireContact(int $req) : self { return $this->SetFeature('requirecontact', $req); }
     
    public function GetClientObject(bool $admin) : array
    {
        $data = array(
            'features' => $this->GetAllFeatures()
        );
        
        if ($admin) $data['default_group'] = $this->GetDefaultGroup()->ID();
        
        return $data;
    }
}