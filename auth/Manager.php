<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;

class InvalidAuthSourceException extends Exceptions\ClientErrorException { public $message = "AUTHSOURCE_FAILED"; }

interface ISource
{
    public function VerifyAccountPassword(Account $account, string $password) : bool;
}

abstract class External extends BaseObject implements ISource 
{ 
    public abstract function VerifyPassword(string $username, string $password) : bool;
    
    public function VerifyAccountPassword(Account $account, string $password) : bool
    {
        return $this->VerifyPassword($account->GetUsername(), $password);
    }
    
    public static function GetFieldTemplate() : array
    {
        return array(
            'manager' => new FieldTypes\ObjectRef(Manager::class, 'authsource', false)
        );
    }
    
    public function GetManager() : Manager { return $this->GetObject('manager'); }

    public static function Create(ObjectDatabase $database, Input $input)
    {
        return parent::BaseCreate($database)->Edit($input);
    }
    
    public abstract static function GetPropUsage() : string;
    
    public abstract function Edit(Input $input);
    
    public abstract function GetClientObject() : array;
}

class Manager extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'description' => null,
            'authsource' => new FieldTypes\ObjectPoly(External::class, 'manager', false),
            'default_group' => new FieldTypes\ObjectRef(Group::class)
        ));
    }
    
    public static function GetPropUsage() : string { return "--type ".implode('|',array_keys(self::$auth_types))." [--description text] [--createdefgroup bool]"; }
    
    private static $auth_types = array();
    
    public static function RegisterAuthType(string $class) : void
    {
        self::$auth_types[strtolower(Utilities::ShortClassName($class))] = $class;
    }
    
    public static function GetPropUsages() : array
    {
        $retval = array();
        foreach (self::$auth_types as $name=>$class)
            array_push($retval, "\t --type $name ".$class::GetPropUsage());
        return $retval;
    }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM,
            function($val){ return array_key_exists($val, self::$auth_types); });
        
        $descr = $input->TryGetParam('description', SafeParam::TYPE_TEXT);
        
        try { $authsource = self::$auth_types[$type]::Create($database, $input)->Activate(); }
        catch (Exceptions\ServerException $e){ throw InvalidAuthSourceException::Copy($e); }
        
        $manager = parent::BaseCreate($database);
        
        $manager->SetObject('authsource',$authsource)->SetScalar('description',$descr);
        
        if ($input->TryGetParam('createdefgroup',SafeParam::TYPE_BOOL) ?? true) $manager->CreateDefaultGroup();
        
        return $manager;
    }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('description')) $this->SetScalar('description',$this->TryGetParam('description',SafeParam::TYPE_TEXT));
        
        if ($input->TryGetParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        $this->GetAuthSource()->Edit($input); return $this;
    }
    
    public function Delete() : void
    {
        Account::DeleteByAuthSource($this->database, $this);
        
        $this->DeleteObject('authsource');
        $this->DeleteObject('default_group');
        
        parent::Delete();
    }
    
    public function GetDefaultGroup() : ?Group { return $this->TryGetObject('default_group'); }
    public function GetDefaultGroupID() : ?string { return $this->TryGetObjectID('default_group'); }
    
    public function CreateDefaultGroup() : self
    {
        if ($this->HasObject('default_group')) return $this;
        
        $name = $this->GetShortSourceType();
        $group = Group::Create($this->database, "$name Accounts (".$this->ID().")");
        return $this->SetObject('default_group', $group);
    }
    
    public function GetAuthSource() : External { return $this->GetObject('authsource'); }    
    public function GetAuthSourceType() : string { return $this->GetObjectType('authsource'); }
    
    private function GetShortSourceType() : string { return Utilities::ShortClassName($this->GetAuthSourceType()); }
    
    public function GetDescription() : string
    {
        return $this->TryGetScalar("description") ?? $this->GetShortSourceType();
    }
    
    public function GetClientObject(bool $admin) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'description' => $this->GetDescription()
        );
        
        if ($admin) 
        {
            $retval['type'] = $this->GetShortSourceType();
            $retval['authsource'] = $this->GetAuthSource()->GetClientObject();
            $retval['default_group'] = $this->TryGetObjectID('default_group');
        }
        
        return $retval;
    }
}

require_once(ROOT."/apps/accounts/auth/Local.php");
require_once(ROOT."/apps/accounts/auth/LDAP.php");
require_once(ROOT."/apps/accounts/auth/IMAP.php");
require_once(ROOT."/apps/accounts/auth/FTP.php");
