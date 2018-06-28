<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

require_once(ROOT."/apps/accounts/Account.php");

abstract class MasterKeySource extends StandardObject
{
    const CRYPTOKEY_LENGTH = 48;
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    
    public function hasCrypto() : bool { return $this->TryGetScalar('master_key') !== null; }
    public function useCrypto() : bool { return $this->GetAccount()->hasCrypto(); }
    
    public function GetUnlockedKey(string $cryptokey) : string
    {
        if (!$this->hasCrypto()) throw new CryptoUnavailableException();
        
        $account = $this->GetAccount();
        
        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $cryptokey = CryptoSecret::DeriveKey($cryptokey, $master_salt);
        
        return CryptoSecret::Decrypt($master, $master_nonce, $cryptokey);
    }
    
    public function InitializeCrypto(?string $presetkey = null) : string
    {
        $account = $this->GetAccount();
        
        $cryptokey = $presetkey ?? Utilities::Random(self::CRYPTOKEY_LENGTH);
        
        $master_salt = CryptoSecret::GenerateSalt(); $this->SetScalar('master_salt', $master_salt);
        $master_nonce = CryptoSecret::GenerateNonce(); $this->SetScalar('master_nonce', $master_nonce);
        
        $cryptokey_key = CryptoSecret::DeriveKey($cryptokey, $master_salt);
        
        $this->SetScalar('master_key', $account->GetEncryptedMasterKey($master_nonce, $cryptokey_key));
        
        return $cryptokey;
    }
}