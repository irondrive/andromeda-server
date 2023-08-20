<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Errors\BaseExceptions;

require_once(ROOT."/Apps/Accounts/AuthSource/Exceptions.php");
use Andromeda\Apps\Accounts\Groups\Group;
use Andromeda\Apps\Accounts\{Account, Config};

/** 
 * Manages configured external authentication sources 
 * 
 * External auth sources are stored in their own database tables and are
 * their own classes but all belong to a common Manager class (this one) 
 * that manages them and provides a way to enumerate them efficiently.
 */
abstract class External extends BaseObject implements IAuthSource
{
    use TableTypes\TableLinkedChildren;
    
    /** @return array<string, class-string<self>> */
    public static function GetChildMap() : array
    {
        return self::getAuthClasses();
    }
    
    /** The timestamp this auth source was created */
    private FieldTypes\Timestamp $date_created;
    /** True if this auth source is enabled */
    private FieldTypes\IntType $enabled;
    /** The admin label for this auth source */
    private FieldTypes\NullStringType $description;
    /** 
     * The group all accounts from this source belong to
     * @var FieldTypes\NullObjectRefT<Group>
     */
    private FieldTypes\NullObjectRefT $default_group;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created =  $fields[] = new FieldTypes\Timestamp('date_created');
        $this->enabled =       $fields[] = new FieldTypes\IntType('enabled', false, self::ENABLED_FULL);
        $this->description =   $fields[] = new FieldTypes\NullStringType('description');
        $this->default_group = $fields[] = new FieldTypes\NullObjectRefT(Group::class, 'default_group');

        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** 
     * Returns a map of all external Auth classes as $name=>$class 
     * @return array<string, class-string<self>>
     */
    private static function getAuthClasses() : array
    {
        $classes = Utilities::getClassesMatching(self::class); // TODO this is dumb
        
        $retval = array(); foreach ($classes as $class)
        {
            if ($class !== self::class) continue; // skip self
            $retval[strtolower(Utilities::ShortClassName($class))] = $class;
        }
        
        return $retval;
    }
    
    /** Returns basic command usage for Create() and Edit() */
    public static function GetPropUsage() : string { return "--type ".implode('|',array_keys(self::getAuthClasses())).
                                                            " [--enabled ".implode('|',array_keys(self::ENABLED_TYPES))."]".
                                                            " [--description ?text] [--createdefgroup bool]"; }
    
    /** Gets command usage specific to external authentication backends */
    final public static function GetPropUsages() : array
    {
        $retval = array();
        foreach (self::getAuthClasses() as $name=>$class)
            $retval[] = "--type $name ".$class::GetPropUsage();
        return $retval;
    }
    
    /** Returns all available external auth objects */
    public static function LoadAll(ObjectDatabase $database) : array
    {
        return $database->LoadObjectsByQuery(static::class, new QueryBuilder()); // empty query
    }
    
    // TODO comment
    public static function TypedCreate(ObjectDatabase $database, SafeParams $params) : self
    {
        $classes = self::getAuthClasses();
        
        $type = $params->GetParam('type')->FromWhitelist(array_keys($classes));
        
        try { return $classes[$type]::Create($database, $params)->Activate(); }
        catch (BaseExceptions\ServerException $e){ throw InvalidAuthSourceException::Copy($e); } // TODO exception Copy is not a thing
        // TODO catch something better than ServerException - also probably need to catch PHP errors
        // make a common exception for FTP/LDAP etc. to all use (like Storage does) + also catch PHP errors? or the class could convert it itself
    }

    /** Creates and tests a new external authentication backend, creating a manager and optionally, a default group for it */ // TODO fix comments
    
    /** @return static */
    protected static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $obj = static::BaseCreate($database);
        $obj->date_created->SetTimeNow();
        
        if ($params->HasParam('enabled'))
        {
            $enabled = $params->GetParam('enabled')->FromWhitelist(array_keys(self::ENABLED_TYPES));
            $obj->enabled->SetValue(self::ENABLED_TYPES[$enabled]);
        }
        
        if ($params->HasParam('description'))
        {
            $descr = $params->GetParam('description')->GetNullHTMLText();
            $obj->description->SetValue($descr);
        }

        if ($params->GetOptParam('createdefgroup',true)->GetBool()) 
        {
            $obj->CreateDefaultGroup();
        }
        
        return $obj;
    }
    
    /** Edits properties of an existing external auth backend */
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('enabled'))
        {
            $param = $params->GetParam('enabled')->FromWhitelist(array_keys(self::ENABLED_TYPES));
            $this->enabled->SetValue(self::ENABLED_TYPES[$param]);
        }

        if ($params->HasParam('description'))
        {
            $descr = $params->GetParam('description')->GetNullHTMLText();
            $this->description->SetValue($descr);
        }
        
        if ($params->GetOptParam('createdefgroup',false)->GetBool()) 
        {
            $this->CreateDefaultGroup();
        }
        
        return $this;
    }
    
    /** Deletes the external authentication source and all accounts created by it */
    public function notifyDelete() : void
    {
        $config = Config::GetInstance($this->database);
        if ($config->GetDefaultAuthID() === $this->ID())
            $config->SetDefaultAuth(null)->Save();
        
        Account::DeleteByAuthSource($this->database, $this);
        
        $defgroup = $this->default_group->TryGetObject();
        if ($defgroup !== null) $defgroup->Delete();

        parent::Delete();
    }
    
    /** Returns the class-only (no namespace) of the auth source */
    private function GetTypeName() : string { return Utilities::ShortClassName(static::class); }
    
    /** Returns the group that all accounts from this auth source are implicitly part of */
    public function GetDefaultGroup() : ?Group { return $this->default_group->TryGetObject(); }

    /** Creates a new default group whose implicit members are all accounts of this auth source */
    private function CreateDefaultGroup() : self
    {
        if ($this->default_group->TryGetObjectID() !== null) return $this;
        
        $name = $this->GetTypeName(); $id = $this->ID();
        $group = Group::Create($this->database, "$name Accounts ($id)");
        
        $this->default_group->SetObject($group); 
        $group->Initialize(); // init AFTER set default
        return $this;
    }

    public const ENABLED_EXIST = 1; /** Only allow users that already exist in the DB to sign in */
    public const ENABLED_FULL = 2;  /** Allow auto-creating new accounts for all external signins */
    
    private const ENABLED_TYPES = array(
        'disable'=>0, 
        'exist'=>self::ENABLED_EXIST, 
        'full'=>self::ENABLED_FULL);
    
    /** Returns the enum of how/if this is enabled */
    public function GetEnabled() : int { return $this->enabled->GetValue(); }
    
    /** Returns the description set for this auth source, or the class name if none is set */
    public function GetDescription() : string
    {
        return $this->description->TryGetValue() ?? $this->GetTypeName();
    }
    
    /** Activate the auth source to prepare it for use */
    public function Activate() : self { return $this; }

    /**
     * Returns a printable client object for this manager and auth source
     * 
     * See the GetClientObject() for each specific auth source type.
     * @param bool $admin if true, show admin-level details
     * @return array<string, mixed> `{id:id, description:string}` \
        if $admin, add `{enabled:enum, type:enum, default_group:?id}`
     */
    public function GetClientObject(bool $admin) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'description' => $this->GetDescription()
        );
        
        if ($admin) 
        {
            $retval['type'] = $this->GetTypeName();
            $retval['enabled'] = array_flip(self::ENABLED_TYPES)[$this->GetEnabled()];
            $retval['default_group'] = $this->default_group->TryGetObjectID();
            $retval['date_created'] = $this->date_created->GetValue();
        }
        
        return $retval;
    }
}
