<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

/** An object describing a contact method for a user account */
class ContactInfo extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null, // type of contact info
            'info' => null, // value of the contact info
            'valid' => null, // true if it has been validated
            'unlockcode' => null,          
            'account' => new FieldTypes\ObjectRef(Account::class, 'contactinfos')
        ));
    }
    
    const TYPE_EMAIL = 1;
    
    /** Returns the contact info object matching the given value, or null */
    public static function TryLoadByInfo(ObjectDatabase $database, string $info) : ?ContactInfo
    {
        return static::TryLoadUniqueByKey($database, 'info', $info);
    }
    
    /** Returns the enum describing the type of contact info */
    public function GetType() : int { return $this->GetScalar('type'); }
    
    /** Returns the actual contact info value */
    public function GetInfo() : string { return $this->GetScalar('info'); }  
    
    /** Returns true if the contact info has been validated */
    public function GetIsValid() : bool { return $this->GetScalar('valid'); }
    
    /** Sets whether the contact info has been validated */
    public function SetIsValid(bool $data = true) : self { return $this->SetScalar('valid',$data); } // TODO just default to false, have this always set true, also see Create
    
    /** Returns the unlock code if it exists or null */
    public function GetUnlockCode() : ?string { return $this->TryGetScalar('unlockcode'); }
    
    /** Sets an unlock code for validation */
    public function SetUnlockCode(?string $data) { return $this->SetScalar('unlockcode',$data); }
    
    /** Returns the account that owns the contact */
    public function GetAccount() : Account { return $this->GetObject('account'); }

    public static function Create(ObjectDatabase $database, Account $account, int $type, string $info) : ContactInfo
    {        
        return parent::BaseCreate($database)
            ->SetScalar('valid', true)
            ->SetScalar('info',$info)
            ->SetScalar('type',$type)
            ->SetObject('account', $account);
    }
    
    /**
     * Converts an array of contact info objects to email address strings
     * @param ContactInfo[] loaded contact infos
     * @return string[] valid email addresses
     */
    public static function GetEmails(array $contactinfos) : array
    {
        $output = array();
        foreach ($contactinfos as $contact) {
            if ($contact->GetType() == self::TYPE_EMAIL && $contact->GetIsValid()) 
                array_push($output, $contact->GetInfo()); }
        return $output;
    }
    
    /**
     * Returns a partially redacted email address 
     * 
     * All characters other than @ . and the first character of each block between them are replaced by *
     * @param string $email full email address
     * @return string partially redacted email address
     */
    public static function RedactEmail(string $email) : string
    {
        $halves = explode('@',$email,2);
        
        $nameparts = explode('.', $halves[0]);
        $domainparts = explode('.', $halves[1]);
        
        $dots = function($str) { return str_pad($str[0], strlen($str), '*'); };
        
        $name = implode('.',array_map($dots, $nameparts));
        $domain = implode('.',array_map($dots, $domainparts));
        
        return "$name@$domain";
    }
    
    /**
     * Gets this contact info as a printable object
     * @return array `{id:string, type:int, info:string, valid:bool, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'type' => $this->GetType(), // TODO show string
            'info' => $this->GetInfo(),
            'valid' => $this->GetIsValid(),
            'dates' => $this->GetAllDates(),
        );
    }
}
