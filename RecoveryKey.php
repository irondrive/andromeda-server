<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\{Exceptions, DecryptionFailedException};

require_once(ROOT."/apps/accounts/Account.php");
require_once(ROOT."/apps/accounts/MasterKeySource.php"); 

class AccountAlreadyUnlockedException extends Exceptions\ServerException { public $message = "CANT_TEST_RECOVERYKEY_ON_UNLOCKED_ACCOUNT"; }

class RecoveryKey extends MasterKeySource
{
    const SET_SIZE = 8; const KEY_LENGTH = 32;
    
    public function GetAccount() : Account  { return $this->GetObject('account'); }

    public static function CreateSet(ObjectDatabase $database, Account $account) : array
    {        
        return array_map(function($i) use ($database, $account){ 
            return self::Create($database, $account); 
        }, range(0, self::SET_SIZE-1));
    }
    
    public static function Create(ObjectDatabase $database, Account $account) : self
    {
        return parent::BaseCreate($database)->SetObject('account',$account);
    }
    
    public function InitializeKey() : string
    {
        if ($this->useCrypto())
        {
            $this->SetScalar('authkey', null); 
            return parent::InitializeCrypto();
        } else {
            $key = Utilities::Random(self::KEY_LENGTH);
            $this->SetScalar('authkey', $key);
            return $key;
        }
    }
    
    public function EnableCrypto() : void
    {
        if ($this->hasCrypto()) return; if (!$this->GetAccount()->CryptoAvailable()) return;
        $this->InitializeCrypto($this->GetScalar('authkey'));
    }
    
    public function CheckCode(string $code) : bool
    {
        $account = $this->GetAccount();
        
        if ($this->hasCrypto())
        {
            try 
            { 
                if ($account->CryptoAvailable()) throw new AccountAlreadyUnlockedException();
                
                $account->UnlockCryptoFromKeySource($this, $code);
                
                $this->Delete(); return true; 
            }
            catch (DecryptionFailedException $e) { return false; }
        }
        else if ($code === $this->GetScalar('authkey'))
        {
            $this->Delete(); return true;
        }
        else return false;
    }
}

