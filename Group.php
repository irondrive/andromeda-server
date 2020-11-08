<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/GroupStuff.php");

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

class Group extends AuthEntity
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'comment' => null,
            'priority' => null,
            'members__features__admin' => null,
            'members__features__enabled' => null,
            'members__features__forcetwofactor' => null,
            'members__max_client_age' => null,
            'members__max_session_age' => null,
            'members__max_password_age' => null,            
            'accounts' => new FieldTypes\ObjectJoin(Account::class, 'groups', GroupJoin::class)
        ));
    }
    
    public function GetName() : string { return $this->GetScalar('name'); }
    public function GetComment() : ?string { return $this->TryGetScalar('comment'); }
    
    public function GetMembersScalar(string $field) { return parent::GetScalar("members__$field"); }
    public function TryGetMembersScalar(string $field) { return parent::TryGetScalar("members__$field"); }
    
    public function GetMembersObject(string $field) { return parent::GetObject("members__$field"); }
    public function TryGetMembersObject(string $field) { return parent::TryGetObject("members__$field"); }
    
    public function GetPriority() : int { return $this->TryGetScalar('priority') ?? 0; }
    
    public function GetAccounts() : array { return $this->GetObjectRefs('accounts'); }
    public function CountAccounts() : int { return $this->CountObjectRefs('accounts'); }
    
    public function AddAccount(Account $account) : self { return $this->AddObjectRef('accounts', $account); }
    public function RemoveAccount(Account $account) : self { return $this->RemoveObjectRef('accounts', $account); }
    
    public function GetAccountAddedDate(Account $account) : ?int {
        $joinobj = $this->TryGetJoinObject('accounts', $account);
        return ($joinobj !== null) ? $joinobj->GetDateCreated() : null;
    }
    
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return static::TryLoadByUniqueKey($database, 'name', $name);
    }
    
    public static function LoadByName(ObjectDatabase $database, string $name) : self
    {
        return static::LoadByUniqueKey($database, 'name', $name);
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

    public function GetClientObject() : array
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
}
