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

/** Exception indicating that the created auth source is invalid */
class InvalidAuthSourceException extends Exceptions\ClientErrorException { public $message = "AUTHSOURCE_FAILED"; }

/** 
 * Manages configured external authentication sources 
 * 
 * External auth sources are stored in their own database tables and are
 * their own classes but all belong to a common Manager class (this one) 
 * that manages them and provides a way to enumerate them efficiently.
 */
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
    
    private static $auth_types = array();
    
    /** Registers a class that provides external authentication */
    public static function RegisterAuthType(string $class) : void
    {
        self::$auth_types[strtolower(Utilities::ShortClassName($class))] = $class;
    }
    
    /** Returns basic command usage for Create() and Edit() */
    public static function GetPropUsage() : string { return "--type ".implode('|',array_keys(self::$auth_types))." [--description ?text] [--createdefgroup bool]"; }
    
    /** Gets command usage specific to external authentication backends */
    public static function GetPropUsages() : array
    {
        $retval = array();
        foreach (self::$auth_types as $name=>$class)
            array_push($retval, "\t --type $name ".$class::GetPropUsage());
        return $retval;
    }
    
    /** Creates and tests a new external authentication backend, creating a manager and optionally, a default group for it */
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
    
    /** Edits properties of an existing external auth backend */
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('description')) $this->SetScalar('description',$this->TryGetParam('description',SafeParam::TYPE_TEXT));
        
        if ($input->TryGetParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        $this->GetAuthSource()->Edit($input); return $this;
    }
    
    /** Deletes the external authentication source and all accounts created by it */
    public function Delete() : void
    {
        Account::DeleteByAuthSource($this->database, $this);
        
        $this->DeleteObject('authsource');
        $this->DeleteObject('default_group');
        
        parent::Delete();
    }
    
    /** Returns the group that all accounts from this auth source are implicitly part of */
    public function GetDefaultGroup() : ?Group { return $this->TryGetObject('default_group'); }
    
    /** Returns the ID of the default group for this auth source */
    public function GetDefaultGroupID() : ?string { return $this->TryGetObjectID('default_group'); }
    
    /** Creates a new default group whose implicit members are all accounts of this auth source */
    private function CreateDefaultGroup() : self
    {
        if ($this->HasObject('default_group')) return $this;
        
        $name = $this->GetShortSourceType();
        $group = Group::Create($this->database, "$name Accounts (".$this->ID().")");
        $this->SetObject('default_group', $group); $group->Initialize(); return $this;
    }
    
    /** Returns the actual auth source interface for this manager */
    public function GetAuthSource() : External { return $this->GetObject('authsource'); }  
    
    /** Returns the type of auth source for this manager, without actually loading it */
    public function GetAuthSourceType() : string { return $this->GetObjectType('authsource'); }
    
    /** Returns the class-only (no namespace) of the auth source */
    private function GetShortSourceType() : string { return Utilities::ShortClassName($this->GetAuthSourceType()); }
    
    /** Returns the description set for this auth source, or the class name if none is set */
    public function GetDescription() : string
    {
        return $this->TryGetScalar("description") ?? $this->GetShortSourceType();
    }
    
    /**
     * Returns a printable client object for this manager and auth source
     * 
     * See the GetClientObject() for each specific auth source type.
     * @param bool $admin if true, show admin-level details
     * @return array {id:string, description:string} \
        if $admin, add {type:string, authsource:(Authsource), default_group:?id}
     */
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
