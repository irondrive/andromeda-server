<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/Apps/Accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/Apps/Accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/Apps/Accounts/Config.php"); use Andromeda\Apps\Accounts\Config;

/** Exception indicating that the created auth source is invalid */
class InvalidAuthSourceException extends Exceptions\ClientErrorException { public $message = "AUTHSOURCE_FAILED"; use Exceptions\Copyable; }

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
        return array(
            'enabled' => new FieldTypes\IntType(self::ENABLED_FULL),
            'description' => new FieldTypes\StringType(),
            'obj_authsource' => (new FieldTypes\ObjectPoly(External::class, 'manager', false))->autoDelete(),
            'obj_default_group' => (new FieldTypes\ObjectRef(Group::class))->autoDelete()
        );
    }
    
    /** Returns a map of all external Auth classes as $name=>$class */
    private static function getAuthClasses() : array
    {
        $classes = Utilities::getClassesMatching(External::class);
        
        $retval = array(); foreach ($classes as $class)
            $retval[strtolower(Utilities::ShortClassName($class))] = $class;
        
        return $retval;
    }
    
    /** Returns basic command usage for Create() and Edit() */
    public static function GetPropUsage() : string { return "--type ".implode('|',array_keys(self::getAuthClasses())).
                                                            " [--enabled ".implode('|',array_keys(self::ENABLED_TYPES))."]".
                                                            " [--description ?text] [--createdefgroup bool]"; }
    
    /** Gets command usage specific to external authentication backends */
    public static function GetPropUsages() : array
    {
        $retval = array();
        foreach (self::getAuthClasses() as $name=>$class)
            $retval[] = "--type $name ".$class::GetPropUsage();
        return $retval;
    }
    
    /** Creates and tests a new external authentication backend, creating a manager and optionally, a default group for it */
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $classes = self::getAuthClasses();
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM, 
            SafeParams::PARAMLOG_ONLYFULL, array_keys($classes));
        
        $descr = $input->GetOptNullParam('description', SafeParam::TYPE_TEXT);
        
        try { $authsource = $classes[$type]::Create($database, $input)->Activate(); }
        catch (Exceptions\ServerException $e){ throw InvalidAuthSourceException::Copy($e); }
        
        $manager = parent::BaseCreate($database);
        
        $manager->SetObject('authsource',$authsource)->SetScalar('description',$descr);
        
        if ($input->HasParam('enabled'))
        {
            $param = $input->GetParam('enabled',SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, array_keys(self::ENABLED_TYPES));
            
            $manager->SetScalar('enabled', self::ENABLED_TYPES[$param]);
        }
        
        if ($input->GetOptParam('createdefgroup',SafeParam::TYPE_BOOL) ?? true) $manager->CreateDefaultGroup();
        
        return $manager;
    }
    
    /** Edits properties of an existing external auth backend */
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('enabled'))
        {
            $param = $input->GetParam('enabled',SafeParam::TYPE_ALPHANUM, 
                SafeParams::PARAMLOG_ONLYFULL, array_keys(self::ENABLED_TYPES));
            
            $this->SetScalar('enabled', self::ENABLED_TYPES[$param]);
        }
        
        if ($input->HasParam('description')) $this->SetScalar('description',$input->GetNullParam('description',SafeParam::TYPE_TEXT));
        
        if ($input->GetOptParam('createdefgroup',SafeParam::TYPE_BOOL) ?? false) $this->CreateDefaultGroup();
        
        $this->GetAuthSource()->Edit($input); return $this;
    }
    
    /** Deletes the external authentication source and all accounts created by it */
    public function Delete() : void
    {
        Account::DeleteByAuthSource($this->database, $this);
        
        $config = Config::GetInstance($this->database);
        if ($config->GetDefaultAuthID() === $this->ID())
            $config->SetDefaultAuth(null);
        
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
    
    const ENABLED_EXIST = 1; /** Only allow users that already exist in the DB to sign in */
    const ENABLED_FULL = 2;  /** Allow auto-creating new accounts for all external signins */
    
    const ENABLED_TYPES = array('disable'=>0, 'exist'=>self::ENABLED_EXIST, 'full'=>self::ENABLED_FULL);
    
    /** Returns the enum of how/if this is enabled */
    public function GetEnabled() : int { return $this->GetScalar('enabled'); }
    
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
     * @return array `{id:id, description:string}` \
        if $admin, add `{enabled:enum, type:enum, authsource:(Authsource), default_group:?id}`
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
            $retval['enabled'] = array_flip(self::ENABLED_TYPES)[$this->GetEnabled()];
            $retval['authsource'] = $this->GetAuthSource()->GetClientObject();
            $retval['default_group'] = $this->TryGetObjectID('default_group');
        }
        
        return $retval;
    }
}
