<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

class ContactInfo extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null,
            'info' => null,
            'valid' => null,
            'unlockcode' => null,          
            'account' => new FieldTypes\ObjectRef(Account::class, 'contactinfos')
        ));
    }
    
    const TYPE_EMAIL = 1;

    public static function LoadByInfo(ObjectDatabase $database, string $info) : ContactInfo
    {
        return self::LoadByUniqueKey($database, 'info', $info);
    }
    
    public static function TryLoadByInfo(ObjectDatabase $database, string $info) : ?ContactInfo
    {
        return self::TryLoadByUniqueKey($database, 'info', $info);
    }
    
    public function GetType() : int         { return $this->GetScalar('type'); }
    public function GetInfo() : string      { return $this->GetScalar('info'); }  
    
    public function GetIsValid() : bool     { return $this->GetScalar('valid'); }       
    public function SetIsValid(bool $data = true) : self { return $this->SetScalar('valid',$data); }
    
    public function GetUnlockCode() : ?string    { return $this->TryGetScalar('unlockcode'); }
    public function SetUnlockCode(?string $data) { return $this->SetScalar('unlockcode',$data); }
    
    public function GetAccount() : Account { return $this->GetObject('account'); }

    public static function Create(ObjectDatabase $database, Account $account, int $type, string $info) : ContactInfo
    {        
        return parent::BaseCreate($database)
            ->SetScalar('valid', true)
            ->SetScalar('info',$info)
            ->SetScalar('type',$type)
            ->SetObject('account', $account);
    }
    
    public static function GetEmails(array $contactinfos) : array
    {
        $output = array();
        foreach ($contactinfos as $contact) {
            if ($contact->GetType() == self::TYPE_EMAIL && $contact->GetIsValid()) 
                array_push($output, $contact->GetInfo()); }
        return $output;
    }
    
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
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'type' => $this->GetType(),
            'info' => $this->GetInfo(),
            'valid' => $this->GetIsValid(),
            'dates' => $this->GetAllDates(),
        );
    }
}
