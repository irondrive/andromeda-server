<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoKey};
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/Account.php");

abstract class KeySource extends AuthObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'master_key' => null,
            'master_nonce' => null,
            'master_salt' => null
        ));
    }

    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    public function GetAccount() : Account  { return $this->GetObject('account'); }
    
    public static function CreateKeySource(ObjectDatabase $database, Account $account) : self
    {
        $obj = parent::BaseCreate($database)->SetObject('account',$account);;
        
        return (!$account->hasCrypto()) ? $obj : $obj->InitializeCrypto();
    }
    
    public function InitializeCrypto() : self
    {
        if ($this->hasCrypto()) throw new CryptoAlreadyInitializedException();
        
        $master_salt = CryptoKey::GenerateSalt();
        $master_nonce = CryptoSecret::GenerateNonce();
        
        $key = CryptoKey::DeriveKey($this->GetAuthKey(), $master_salt, CryptoSecret::KeyLength(), true);
        $master_key = $this->GetAccount()->GetEncryptedMasterKey($master_nonce, $key);
        
        return $this
            ->SetScalar('master_salt', $master_salt)
            ->SetScalar('master_nonce', $master_nonce)
            ->SetScalar('master_key', $master_key);
    }

    public function GetUnlockedKey() : string
    {
        if (!$this->hasCrypto()) throw new CryptoNotInitializedException();
        
        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $cryptokey = CryptoKey::DeriveKey($this->GetAuthKey(), $master_salt, CryptoSecret::KeyLength(), true);
        
        return CryptoSecret::Decrypt($master, $master_nonce, $cryptokey);
    }
}

