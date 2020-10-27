<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\{CryptoSecret, CryptoKey};
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\{Exceptions, DecryptionFailedException};
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php");

class AccountAlreadyUnlockedException extends Exceptions\ServerException { public $message = "CANT_TEST_RECOVERYKEY_ON_UNLOCKED_ACCOUNT"; }

class RecoveryKey extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'master_key' => null,
            'master_nonce' => null,
            'master_salt' => null,            
            'account' => new FieldTypes\ObjectRef(Account::class, 'recoverykeys')
        ));
    }
    
    const SET_SIZE = 8; const KEY_LENGTH = 32;
    
    public function GetAccount() : Account  { return $this->GetObject('account'); }

    public static function CreateSet(ObjectDatabase $database, Account $account) : array
    {        
        return array_map(function($i) use ($database, $account){ 
            return self::Create($database, $account); 
        }, range(0, self::SET_SIZE-1));
    }
    
    const CRYPTOKEY_LENGTH = 48;
    
    public static function Create(ObjectDatabase $database, Account $account) : self
    {
        $cryptokey = Utilities::Random(self::CRYPTOKEY_LENGTH);
        
        $master_salt = CryptoKey::GenerateSalt();
        $master_nonce = CryptoSecret::GenerateNonce();        
        $cryptokey_key = CryptoKey::DeriveKey($cryptokey, $master_salt, CryptoSecret::KeyLength());

        return parent::BaseCreate($database)
            ->SetObject('account',$account)
            ->SetScalar('master_salt', $master_salt)
            ->SetScalar('master_nonce', $master_nonce)
            ->SetScalar('master_key', $account->GetEncryptedMasterKey($master_nonce, $cryptokey_key));
    }
    
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        parent::DeleteManyMatchingAll($database, array('account' => $account->ID()));
    }
    
    public function CheckCode(string $code) : bool
    {
        $account = $this->GetAccount();

        try 
        { 
            if ($account->CryptoAvailable()) 
                throw new AccountAlreadyUnlockedException();
            
            $account->UnlockCryptoFromKeySource($this, $code);
            
            $this->Delete(); return true; 
        }
        catch (DecryptionFailedException $e) { return false; }
    }
    
    public function GetUnlockedKey(string $cryptokey) : string
    {
        $account = $this->GetAccount();
        
        $master = $this->GetScalar('master_key');
        $master_nonce = $this->GetScalar('master_nonce');
        $master_salt = $this->GetScalar('master_salt');
        
        $cryptokey = CryptoKey::DeriveKey($cryptokey, $master_salt, CryptoSecret::KeyLength());
        
        return CryptoSecret::Decrypt($master, $master_nonce, $cryptokey);
    }
}

