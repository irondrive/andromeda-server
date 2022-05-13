<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** A value and inherit-source pair */
class InheritedProperty
{
    private $value; private ?BaseObject $source;
    
    /** Returns the value of the inherited property */
    public function GetValue() { return $this->value; }
    
    /** Returns the source object of the inherited property */
    public function GetSource() : ?BaseObject { return $this->source; }
    
    public function __construct($value, ?BaseObject $source){
        $this->value = $value; $this->source = $source; }
}

/** Class representing a group membership, joining an account and a group */
class GroupJoin //extends JoinObject // TODO
{
    /** 
     * Return the column name of the left side of the join
     * 
     * Must match the name of the column in the right-side object that refers to this join
     */
    protected static function GetLeftField() : string { return 'accounts'; }
    
    /**
     * Return the column name of the right side of the join
     *
     * Must match the name of the column in the left-side object that refers to this join
     */
    protected static function GetLeftClass() : string { return Account::class; }
    
    /** Return the column name of the right side of the join */
    protected static function GetRightField() : string { return 'groups'; }
    
    /** Return the object class referred to by the right side of the join */
    protected static function GetRightClass() : string { return Group::class; }
    
    /** Returns the joined account */
    public function GetAccount() : Account { return $this->GetObject('accounts'); }
    
    /** Returns the joined group */
    public function GetGroup() : Group { return $this->GetObject('groups'); }
    
    /**
     * Returns a printable client object of this group membership
     * @return array `{dates:{created:float}}`
     */
    public function GetClientObject()
    {
        return array(
            'dates' => array(
                'created' => $this->GetDateCreated(),
            )
        );
    }
}

/** Base class for account/groups containing properties that can be set per-account or per-group */
abstract class AuthEntity extends BaseObject  // TODO was StandardObject
{
    public abstract function GetDisplayName() : string;
    
    public abstract function GetContacts() : array;
    
    public abstract function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'admin' => new FieldTypes\BoolType(), // true if the account is an admin
            'disabled' => new FieldTypes\BoolType(), // > 0 if the account is disabled
            'forcetf' => new FieldTypes\BoolType(), // true if two-factor is required to create sessions (not just clients)
            'allowcrypto' => new FieldTypes\BoolType(), // true if server-side account crypto is enabled
            'accountsearch' => new FieldTypes\IntType(), // whether looking up accounts by name is allowed
            'groupsearch' => new FieldTypes\IntType(), // whether looking up groups by name is allowed
            'userdelete' => new FieldTypes\BoolType(), // whether the user is allowed to delete their account
            'limit_sessions' => new FieldTypes\Limit(), // maximum number of sessions for the account
            'limit_contacts' => new FieldTypes\Limit(), // maximum number of contacts for the account
            'limit_recoverykeys' => new FieldTypes\Limit(), // maximum number of recovery keys for the account
            'session_timeout' => new FieldTypes\IntType(), // server-side timeout - max time for a session to be inactive
            'client_timeout' => new FieldTypes\IntType(), // server-side timeout - max time for a client to be inactive
            'max_password_age' => new FieldTypes\IntType(), // max time since the account's password changed
            'date_modified' => new FieldTypes\Date() // last timestamp these properties were modified
        ));
    }
    
    /** defines command usage for SetProperties() */
    public static function GetPropUsage() : string { return "[--session_timeout ?uint] [--client_timeout ?uint] [--max_password_age ?uint] ".
                                                            "[--max_sessions ?uint8] [--max_contacts ?uint8] [--max_recoverykeys ?uint8] ".
                                                            "[--admin ?bool] [--disabled ?bool] [--forcetf ?bool] [--allowcrypto ?bool] ".
                                                            "[--accountsearch ?uint8] [--groupsearch ?uint8] [--userdelete ?bool]"; }

    /** 
     * Sets the value of an inherited property for the object 
     * @return $this
     */
    public function SetProperties(SafeParams $params) : self
    {
        foreach (array('session_timeout','client_timeout','max_password_age') as $prop)
            if ($params->HasParam($prop)) $this->SetScalar($prop, $params->GetParam($prop)->GetNullUint());
        
        foreach (array('max_sessions','max_contacts','max_recoverykeys') as $prop)
            if ($params->HasParam($prop)) $this->SetCounterLimit(str_replace('max_','',$prop), $params->GetParam($prop)->GetNullUint8());
        
        foreach (array('admin','disabled','forcetf','allowcrypto','userdelete') as $prop)
            if ($params->HasParam($prop)) $this->SetFeatureBool($prop, $params->GetParam($prop)->GetNullBool());
        
        foreach (array('accountsearch','groupsearch') as $prop)
            if ($params->HasParam($prop)) $this->SetFeatureInt($prop, $params->GetParam($prop)->GetNullUint8());
            
        return $this->SetDate('modified');
    }
}
