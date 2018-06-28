<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/AuthEntity.php");

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\ClientObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoPublic;

class Group extends AuthEntity implements ClientObject
{
    public function GetName() : string { return $this->GetScalar('name'); }
    public function GetComment() : ?string { return $this->TryGetScalar('comment'); }
    
    public function GetMembersScalar(string $field) { return parent::GetScalar("members__$field"); }
    public function TryGetMembersScalar(string $field) { return parent::TryGetScalar("members__$field"); }
    
    public function GetMembersObject(string $field) { return parent::GetObject("members__$field"); }
    public function TryGetMembersObject(string $field) { return parent::TryGetObject("members__$field"); }
    
    public function GetPriority() : int { return $this->TryGetScalar('priority') ?? 0; }
    
    public function hasCrypto() : bool { return $this->TryGetScalar('public_key') !== null; }
    public function useCrypto() : bool { return $this->TryGetMembersScalar('features__encryption') ?? false; }
    public function hasCryptoPending() : bool { return $this->TryGetCounter('crypto_pending') ?? 0; }
    
    public function GetAccountMemberships() : array { return $this->GetObjectRefs('accounts'); }
    public function CountAccountMemberships() : int { return $this->TryCountObjectRefs('accounts'); }
    
    public function GetAccounts() : array
    {
        $ids = array_values(array_map(function($m){ return $m->GetAccountID(); }, $this->GetAccountMemberships()));
        return Account::LoadManyByID($this->database, $ids);
    }
    
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return self::TryLoadByUniqueKey($database, 'name', $name);
    }
    
    public static function LoadByName(ObjectDatabase $database, string $name) : self
    {
        return self::LoadByUniqueKey($database, 'name', $name);
    }
    
    public function GetEmailRecipients() : array
    {
        $accounts = $this->GetAccounts(); $output = array();
        
        foreach($accounts as $account)
        {
            if (!$account->isEnabled()) continue;
            $emails = $account->GetEmailRecipients();
            foreach ($emails as $email) array_push($output, $email);
        }
        return $output;
    }
    
    public function SendMailTo(Emailer $mailer, string $subject, string $message, ?EmailRecipient $from = null)
    {
        $mailer->SendMail($subject, $message, $this->GetEmailRecipients(), $from, true);
    }
    
    public static function Create(ObjectDatabase $database, string $name, ?int $priority = null, ?string $comment = null) : self
    {
        $group = parent::BaseCreate($database)->SetScalar('name', $name);
        
        if ($priority !== null) $group->SetScalar('priority', $priority);
        if ($comment !== null) $group->SetScalar('comment', $comment);
        
        return $group;
    }
    
    public function Delete() : void
    {
        foreach ($this->GetAccountMemberships() as $accountm) $accountm->Delete();
        
        parent::Delete();
    }

    public function GetClientObject(int $level = 0) : array
    {
        return array(
            'id' => $this->ID(),
            'name' => $this->GetName(),
            'priority' => $this->GetPriority(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
            
            'accounts' => array_map(function($e){ return $e->ID(); }, $this->GetAccounts()),
        );        
    }
    
    public function InitializeCrypto() : string
    {
        $keypair = CryptoPublic::GenerateKeyPair();        
        $this->SetScalar('public_key', $keypair['public']);         
        return $keypair['private'];
    }
    
    public function RemoveCrypto() : self
    {
        $this->SetScalar('public_key', null); return $this;
    }
}
