<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/GroupStuff.php");

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

class Group extends AuthEntity
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'comment' => null,
            'priority' => new FieldTypes\Scalar(0),       
            'accounts' => new FieldTypes\ObjectJoin(Account::class, 'groups', GroupJoin::class)
        ));
    }
    
    public function GetDisplayName() : string { return $this->GetScalar('name'); }
    public function SetDisplayName(string $name) : self { return $this->SetScalar('name',$name); }
    
    public function GetComment() : ?string { return $this->TryGetScalar('comment'); }
    public function SetComment(?string $comment) : self { return $this->SetScalar('comment',$comment); }
    
    public function GetPriority() : int { return $this->GetScalar('priority'); }
    public function SetPriority(int $priority) { return $this->SetScalar('priority', $priority); }
    
    public function GetAccounts() : array
    {
        if (Config::GetInstance($this->database)->GetDefaultGroupID() === $this->ID())
            return Account::LoadAll($this->database);        

        foreach (Auth\Manager::LoadAll($this->database) as $authman)
        {
            if ($authman->GetDefaultGroupID() === $this->ID())
                return Account::LoadByAuthSource($this->database, $authman);
        }
        
        return $this->GetObjectRefs('accounts');
    }
    
    public function AddAccount(Account $account) : self { return $this->AddObjectRef('accounts', $account); }
    public function RemoveAccount(Account $account) : self { return $this->RemoveObjectRef('accounts', $account); }
    
    public function GetAccountAddedDate(Account $account) : ?int {
        $joinobj = $this->TryGetJoinObject('accounts', $account);
        return ($joinobj !== null) ? $joinobj->GetDateCreated() : null;
    }
    
    public static function TryLoadByName(ObjectDatabase $database, string $name) : ?self
    {
        return static::TryLoadUniqueByKey($database, 'name', $name);
    }

    public function GetMailTo() : array
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
    
    public static function Create(ObjectDatabase $database, string $name, ?int $priority = null, ?string $comment = null) : self
    {
        $group = parent::BaseCreate($database)->SetScalar('name', $name);
        
        if ($priority !== null) $group->SetScalar('priority', $priority);
        if ($comment !== null) $group->SetScalar('comment', $comment);
        
        return $group;
    }
    
    public function GetClientObject(bool $full = false) : array
    {
        $retval = array(
            'id' => $this->ID(),
            'name' => $this->GetDisplayName(),
            'priority' => $this->GetPriority(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
            'features' => $this->GetAllFeatures(),
            'counters' => $this->GetAllCounters(),
            'limits' => $this->GetAllCounterLimits(),
            'max_session_age' => $this->TryGetScalar('max_session_age'),
            'max_password_age' => $this->TryGetScalar('max_password_age')
        );
        
        if ($full) $retval['accounts'] = array_map(function($e){ return $e->ID(); }, $this->GetAccounts());
        
        return $retval;
    }

}
