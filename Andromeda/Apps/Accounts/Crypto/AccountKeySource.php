<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; if (!defined('Andromeda')) die();

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\FieldTypes;

use Andromeda\Apps\Accounts\Account;

/** 
 * A key source that stores an account master key copy 
 * This is used to provide methods of unlocking crypto in a request other than having the user's password.
 */
trait AccountKeySource
{
    use KeySource { InitializeCrypto as InitializeCryptoFrom; }
    
    /** 
     * The account this key source is for 
     * @var \Andromeda\Core\Database\FieldTypes\ObjectRefT<\Andromeda\Apps\Accounts\Account> // phpstan bug?
     */
    private FieldTypes\ObjectRefT $account;
    
    protected function AccountKeySourceCreateFields() : void
    {
        $fields = array();
        
        $this->account = $fields[] = new FieldTypes\ObjectRefT(Account::class, 'account');
        
        $this->RegisterChildFields($fields);
        
        $this->KeySourceCreateFields();
    }
    
    /**
     * Attemps to load the object with the given account and ID
     * @param ObjectDatabase $database database reference
     * @param Account $account account that owns this object
     * @param string $id the ID of this object
     * @return ?static loaded object or null if not found
     */
    /*public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
     {
     $q = new QueryBuilder(); $w = $q->And($q->Equals('obj_account',$account->ID()),$q->Equals('id',$id));
     return self::TryLoadUniqueByQuery($database, $q->Where($w));
     }*/ // TODO maybe this will be needed here? shouldn't be public though? unsure, check usages
    
    /** 
     * Sets the given account for the newly created key source
     * MUST be called when creating an object with this trait
     * @param ?string $wrapkey key to use to initialize crypto
     * @return $this
     */
    protected function AccountKeySourceCreate(Account $account, ?string $wrapkey = null) : self
    {
        $this->account->SetObject($account);
        
        if ($wrapkey !== null && $account->hasCrypto())
            $this->InitializeCrypto($wrapkey);
        
        return $this;
    }
    
    /** Returns the account that owns this key source */
    protected function GetAccount() : Account
    { 
        return $this->account->GetObject();
    }
    
    /**
     * Initializes crypto, storing a copy of the account's master key
     *
     * Crypto must be unlocked for the account to get a copy of the key
     * @param string $wrapkey the key to use to encrypt
     * @throws Exceptions\CryptoAlreadyInitializedException if already initialized
     * @see Account::GetEncryptedMasterKey()
     * @return $this
     */
    protected function InitializeCrypto(string $wrapkey) : self
    {
        // Account won't give us the key directly so we can't just call InitializeCryptoFrom
        if ($this->hasCrypto()) throw new Exceptions\CryptoAlreadyInitializedException();
        
        $master_salt = Crypto::GenerateSalt();
        $master_nonce = Crypto::GenerateSecretNonce();
        $this->master_salt->SetValue($master_salt);
        $this->master_nonce->SetValue($master_nonce);
        
        $wrapkey = Crypto::DeriveKey($wrapkey, $master_salt, Crypto::SecretKeyLength(), true);
        $master_key = $this->GetAccount()->GetEncryptedMasterKey($master_nonce, $wrapkey);
        $this->master_key->SetValue($master_key);

        return $this;
    }
}
