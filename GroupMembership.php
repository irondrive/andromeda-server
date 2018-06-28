<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoPublic};

class InheritedProperty 
{
    private $value; private $source;
    public function GetValue() { return $this->value; }
    public function GetSource() : ?AuthEntity { return $this->source; }
    public function __construct($value, ?AuthEntity $source){ 
        $this->value = $value; $this->source = $source; }
}

class GroupMembership extends StandardObject
{
    public function GetAccountID() : string { return $this->GetObjectID('account'); }
    public function GetGroupID() : string { return $this->GetObjectID('group'); }
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    
    private $pending_set = false;
    public function GetGroup() : Group 
    { 
        $group = $this->GetObject('group');
        
        if ($group->useCrypto())
        {
            if (!$this->hasCrypto() && !$this->pending_set)
            {
                $group->DeltaCounter('crypto_pending');
                $this->pending_set = true;
            }
            
            $this->ProcessCryptoPendings();
        }       
        
        return $group; 
    }
    
    public static function TryLoadByAccountAndGroup(ObjectDatabase $database, Account $account, Group $group) : ?self
    {
        $found = array_values(self::LoadManyMatchingAll($database, array(
            'account*object*Apps\Accounts\Account*groups' => $account->ID(), 
            'group*object*Apps\Accounts\Group*accounts' => $group->ID() )));
        
        if (count($found) >= 1) return $found[0]; else return null;
    }
    
    public static function Create(ObjectDatabase $database, Account $account, Group $group) : GroupMembership
    {
        $membership = parent::BaseCreate($database);
        
        $membership->SetObject('account',$account)->SetObject('group',$group);
        
        $membership->GetGroup();
        
        return $membership;
    }
    
    public function DecryptKeyFrom(AuthEntity $sender, string $data, string $nonce) : string
    {
        $account = $this->GetAccount(); if (!$this->CryptoAvailable()) throw new CryptoUnavailableException();
        
        $this->UnlockCrypto(); $private = $this->GetScalar('private_key');
        
        $data = CryptoPublic::Decrypt($data, $nonce, $private, $sender->GetPublicKey());
        
        sodium_memzero($private); return $data;
    }   
    
    public function hasCrypto() : bool { return $this->TryGetScalar('private_key') !== null; }
    public function useCrypto() : bool { return $this->GetGroup()->useCrypto(); }
    
    private function canUnlockCrypto() : bool
    {
        return $this->GetAccount()->CryptoAvailable() && $this->hasCrypto();
    }
    
    private function canCreateCrypto() : bool
    {
        $group = $this->GetGroup(); return $this->GetAccount()->CryptoAvailable() && !$group->hasCrypto() && $group->useCrypto();
    }
    
    private $cryptoAvailable = false; 
    public function CryptoAvailable() : bool 
    { 
        return $this->cryptoAvailable || $this->canUnlockCrypto() || $this->canCreateCrypto();
    }
    
    private $processing = false;
    private function ProcessCryptoPendings() : self
    {
        if ($this->processing) return $this;
        
        $this->processing = true; $group = $this->GetGroup();
        
        if ($group->useCrypto() && $group->hasCryptoPending() && $this->CryptoAvailable())
        {
            $this->UnlockCrypto(); 
            
            $account = $this->GetObject('account');
            $private = $this->GetScalar('private_key');            
            
            foreach ($group->GetAccountMemberships() as $membership)
            {
                if (!$membership->hasCrypto())
                {
                    $membership->InitializeCryptoFrom($private, $account);
                    $group->DeltaCounter('crypto_pending', -1);
                }
            }
        }
        
        $this->processing = false; return $this;
    }
    
    private function InitializeCryptoFrom(string $private, Account $sender) : self
    {
        if (!$sender->CryptoAvailable()) throw new CryptoUnavailableException();
        
        $nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('private_nonce', $nonce);  
        
        $private_encrypted = $sender->EncryptKeyFor($this->GetAccount(), $private, $nonce); 
        $this->SetScalar('private_key', $private_encrypted);
        
        $this->SetScalar('private_key', $private, true); $this->cryptoAvailable = true;
        
        $this->SetObject('key_sender', $sender);
        
        sodium_memzero($private); return $this;
    }
    
    private function InitializeCrypto(string $private) : self
    {
        $account = $this->GetAccount();
        
        if (!$account->CryptoAvailable()) throw new CryptoUnavailableException();
        
        $nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('private_nonce', $nonce);
        
        $private_encrypted = $account->EncryptSecret($private, $nonce);
        $this->SetScalar('private_key', $private_encrypted);
        
        $this->SetScalar('private_key', $private, true); $this->cryptoAvailable = true;
        
        $this->SetObject('key_sender', null);
        
        sodium_memzero($private); return $this;
    }
    
    private function UnlockCrypto() : self
    {
        if ($this->cryptoAvailable) return $this;

        if ($this->canUnlockCrypto())
        {
            $account = $this->GetAccount();
            $private = $this->GetScalar('private_key'); 
            $nonce = $this->GetScalar('private_nonce');
            
            if (($from = $this->TryGetObject('key_sender')) !== null)
            {
                $private = $account->DecryptKeyFrom($from, $private, $nonce);
                
                $this->InitializeCrypto($private);
            }
            else $private = $account->DecryptSecret($private, $nonce);
        }
        else if ($this->canCreateCrypto())
        {
            $private = $this->GetGroup()->InitializeCrypto();
            
            $this->InitializeCrypto($private);
        }
        else throw new CryptoUnavailableException();
        
        $this->SetScalar('private_key', $private, true); $this->cryptoAvailable = true; return $this;
    }
    
    public function Delete() : void
    {
        $group = $this->GetGroup();
        
        if ($group->CountAccountMemberships() == 0) $group->RemoveCrypto();
        
        parent::Delete();
    }
    
}