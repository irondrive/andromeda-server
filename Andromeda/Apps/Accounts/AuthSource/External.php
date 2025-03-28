<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\AuthSource; if (!defined('Andromeda')) die();

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Errors\BaseExceptions;

use Andromeda\Apps\Accounts\{Account, Config, Group};

/** 
 * Manages configured external authentication sources 
 * 
 * External auth sources are stored in their own database tables and are
 * their own classes but all belong to a common Manager class (this one) 
 * that manages them and provides a way to enumerate them efficiently.
 * 
 * @phpstan-type ExternalJ array{id:string, description:string}
 * @phpstan-type AdminExternalJ \Union<ExternalJ, array{type:string, enabled:key-of<self::ENABLED_TYPES>, default_group:?string, date_created:float}>
 */
abstract class External extends BaseObject implements IAuthSource
{
    protected const IDLength = 8;

    use TableTypes\TableLinkedChildren;
    
    /** 
     * A map of all external Auth classes as $name=>$class 
     * @var array<string, class-string<self>>
     */
    public const TYPES = array(
        'ftp' => FTP::class, 
        'imap' => IMAP::class, 
        'ldap' => LDAP::class
    );
    
    /** @return array<string, class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { return self::TYPES; }
    
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
        $this->enabled =       $fields[] = new FieldTypes\IntType('enabled', default:self::ENABLED_FULLENABLE);
        $this->description =   $fields[] = new FieldTypes\NullStringType('description');
        $this->default_group = $fields[] = new FieldTypes\NullObjectRefT(Group::class, 'default_group');

        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Returns basic command usage for Create() and Edit() */
    public static function GetCreateUsage() : string { return "--type ".implode('|',array_keys(self::TYPES)).
                                                            " [--enabled ".implode('|',array_keys(self::ENABLED_TYPES))."]".
                                                            " [--description ?text] [--createdefgroup bool]"; }
    
    /** Returns basic command usage for Create() and Edit() */
    public static function GetEditUsage() : string { return static::GetCreateUsage(); } // same

    /** 
     * Gets command usage specific to external authentication backends
     * @return list<string>
     */
    final public static function GetCreateUsages() : array
    {
        $retval = array();
        foreach (self::TYPES as $name=>$class)
            $retval[] = "--type $name ".$class::GetCreateUsage();
        return $retval;
    }
    
    /** 
     * Gets command usage specific to external authentication backends
     * @return list<string>
     */
    final public static function GetEditUsages() : array
    {
        $retval = array();
        foreach (self::TYPES as $name=>$class)
            $retval[] = "--type $name ".$class::GetEditUsage();
        return $retval;
    }
    
    /** Creates and tests a new external auth backend based on the user input */
    public static function TypedCreate(ObjectDatabase $database, SafeParams $params) : self
    {
        $type = $params->GetParam('type')->FromAllowlist(array_keys(self::TYPES));
        
        return self::TYPES[$type]::Create($database, $params);
    }

    /** Creates a new external authentication backend, and optionally a default group for it */
    public static function Create(ObjectDatabase $database, SafeParams $params) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        
        if ($params->HasParam('enabled'))
        {
            $enabled = $params->GetParam('enabled')->FromAllowlist(array_keys(self::ENABLED_TYPES));
            $obj->enabled->SetValue(self::ENABLED_TYPES[$enabled]);
        }
        
        if ($params->HasParam('description'))
        {
            $descr = $params->GetParam('description')->GetNullHTMLText();
            $obj->description->SetValue($descr);
        }

        if ($params->GetOptParam('createdefgroup',false)->GetBool())
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
            $param = $params->GetParam('enabled')->FromAllowlist(array_keys(self::ENABLED_TYPES));
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
    
    /** Deletes the external authentication source and all accounts created by it, and the default group if set */
    public function NotifyPreDeleted() : void
    {
        $config = Config::GetInstance($this->database);
        if ($config->GetDefaultAuthID() === $this->ID())
            $config->SetDefaultAuth(null)->Save();
        
        Account::DeleteByAuthSource($this->database, $this);
        
        $defgroup = $this->default_group->TryGetObject();
        if ($defgroup !== null) $defgroup->Delete();
    }

    /**
     * Verify the password given
     * @param string $username the username to check
     * @param string $password the password to check
     * @param bool $throw allow throwing if the check fails (allow, not guarantee)
     * @return bool true if the password check is valid
     * @throws BaseExceptions\PHPError if $throw and the check fails
     * @throws BaseExceptions\BaseException always if a connection fails, or other errors
     */
    abstract public function VerifyUsernamePassword(string $username, string $password, bool $throw = false) : bool;

    public function VerifyAccountPassword(Account $account, string $password): bool
    {
        return $this->VerifyUsernamePassword($account->GetUsername(), $password);
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
        $group = Group::Create($this->database, "$name Accounts ($id)")->Save(); // save now since the AuthSource gets inserted first
        
        $this->default_group->SetObject($group);
        $group->PostDefaultCreateInitialize(); // init AFTER set default
        return $this;
    }

    /** Only allow users that already existi in the DB to sign in */
    public const ENABLED_PREEXISTING = 1;
    /** Allow auto-creating new accounts for all external signins */
    public const ENABLED_FULLENABLE = 2;

    /** @var array<string,int> */
    private const ENABLED_TYPES = array(
        'disable'=>0,
        'preexist'=>self::ENABLED_PREEXISTING, 
        'fullenable'=>self::ENABLED_FULLENABLE);
    
    /** Returns the enum of how/if this is enabled */
    public function GetEnabled() : int { return $this->enabled->GetValue(); }
    
    /** Returns the description set for this auth source, or the class name if none is set */
    public function GetDescription() : string
    {
        return $this->description->TryGetValue() ?? $this->GetTypeName();
    }
    
    /**
     * Returns a printable client object for this manager and auth source
     * 
     * See the GetClientObject() for each specific auth source type.
     * @param bool $admin if true, show admin-level details
     * @return ($admin is true ? AdminExternalJ : ExternalJ)
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
