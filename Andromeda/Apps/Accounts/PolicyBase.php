<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, TableTypes, ObjectDatabase};
use Andromeda\Core\IOFormat\SafeParams;

/** Base class for account/groups containing properties that can be set per-account or per-group */
abstract class PolicyBase extends BaseObject  // TODO was StandardObject
{
    public abstract function GetDisplayName() : string;
    
    public abstract function GetContacts() : array;
    
    public abstract function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void;
    
    use TableTypes\TableLinkedChildren;
    
    /** @return list<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array
    {
        return array(Account::class, Group::class);
    }
    
    // TODO RAY !! add fields here including date_created

    protected function CreateFields() : void
    {
        $fields = array();

        //$this->date_created =  $fields[] = new FieldTypes\Timestamp('date_created');

        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }




    
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
            'date_modified' => new FieldTypes\Timestamp() // last timestamp these properties were modified
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
