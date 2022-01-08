<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/JoinObject.php"); use Andromeda\Core\Database\JoinObject;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

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
class GroupJoin extends JoinObject
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
abstract class AuthEntity extends StandardObject
{
    public abstract function GetDisplayName() : string;
    
    public abstract function GetContacts() : array;
    
    public abstract function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'admin' => null, // true if the account is an admin
            'disabled' => null, // > 0 if the account is disabled
            'forcetf' => null, // true if two-factor is required to create sessions (not just clients)
            'allowcrypto' => null, // true if server-side account crypto is enabled
            'accountsearch' => null, // whether looking up accounts by name is allowed
            'groupsearch' => null, // whether looking up groups by name is allowed
            'userdelete' => null, // whether the user is allowed to delete their account
            'limit_sessions' => null, // maximum number of sessions for the account
            'limit_contacts' => null, // maximum number of contacts for the account
            'limit_recoverykeys' => null, // maximum number of recovery keys for the account
            'session_timeout' => null, // server-side timeout - max time for a session to be inactive
            'client_timeout' => null, // server-side timeout - max time for a client to be inactive
            'max_password_age' => null, // max time since the account's password changed
            'date_modified' => null // last timestamp these properties were modified
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
    public function SetProperties(Input $input) : self
    {
        foreach (array('session_timeout','client_timeout','max_password_age') as $prop)
            if ($input->HasParam($prop)) $this->SetScalar($prop, $input->GetNullParam($prop, SafeParam::TYPE_UINT));
        
        foreach (array('max_sessions','max_contacts','max_recoverykeys') as $prop)
            if ($input->HasParam($prop)) $this->SetCounterLimit(str_replace('max_','',$prop), $input->GetNullParam($prop, SafeParam::TYPE_UINT8));
        
        foreach (array('admin','disabled','forcetf','allowcrypto','userdelete') as $prop)
            if ($input->HasParam($prop)) $this->SetFeatureBool($prop, $input->GetNullParam($prop, SafeParam::TYPE_BOOL));
        
        foreach (array('accountsearch','groupsearch') as $prop)
            if ($input->HasParam($prop)) $this->SetFeatureInt($prop, $input->GetNullParam($prop, SafeParam::TYPE_UINT8));
            
        return $this->SetDate('modified');
    }
}
