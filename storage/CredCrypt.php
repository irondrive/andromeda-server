<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\{ObjectDatabase, KeyNotFoundException};
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

use Andromeda\Apps\Files\FilesApp;

class CredentialsEncryptedException extends Exceptions\ClientErrorException { public $message = "STORAGE_CREDENTIALS_ENCRYPTED"; }
class CryptoNotAvailableException extends Exceptions\ClientErrorException { public $message = "ACCOUNT_CRYPTO_NOT_AVAILABLE"; }

trait CredCrypt
{
    public static function CredCryptGetFieldTemplate() : array
    {
        return array(
            'username' => null,
            'password' => null,
            'username_nonce' => null,
            'password_nonce' => null,
        );
    }
    
    public function CredCryptGetClientObject() : array
    {
        return array(
            'username' => $this->TryGetUsername(),
            'password' => boolval($this->TryGetPassword()),
        );
    }
    
    public static function CredCryptGetCreateUsage() : string { return "[--username alphanum] [--password raw] [--credcrypt bool]"; }

    public function CredCryptCreate(Input $input, ?Account $account) : self
    {
        $credcrypt = $input->TryGetParam('credcrypt', SafeParam::TYPE_BOOL) ?? false;
        if ($account === null && $credcrypt) throw new CryptoNotAvailableException();
        
        return $this->SetPassword($input->TryGetParam('password', SafeParam::TYPE_RAW), $credcrypt)
            ->SetUsername($input->TryGetParam('username', SafeParam::TYPE_ALPHANUM, SafeParam::MaxLength(255)), $credcrypt);
    }
    
    public function CredCryptEdit(Input $input) : self 
    { 
        $crypt = $input->TryGetParam('credcrypt', SafeParam::TYPE_BOOL);
        if ($crypt !== null) $this->SetEncrypted($crypt);
        return $this;
    }
    
    protected function TryGetUsername() : ?string { return $this->TryGetEncryptedScalar('username'); }
    protected function TryGetPassword() : ?string { return $this->TryGetEncryptedScalar('password'); }
    
    protected function SetUsername(?string $username, bool $credcrypt) : self { return $this->SetEncryptedScalar('username',$username,$credcrypt); }
    protected function SetPassword(?string $password, bool $credcrypt) : self { return $this->SetEncryptedScalar('password',$password,$credcrypt); }

    protected function hasCryptoField($field) : bool { return $this->TryGetScalar($field."_nonce") !== null; }
    
    private array $crypto_cache = array();
    
    protected function GetEncryptedScalar(string $field) : string
    {
        $value = $this->TryGetEncryptedScalar($field);
        if ($value !== null) return $value;
        else throw new KeyNotFoundException($field);
    }
    
    protected function TryGetEncryptedScalar(string $field) : ?string
    {
        if (array_key_exists($field, $this->crypto_cache))
            return $this->crypto_cache[$field];
            
        $account = $this->GetAccount();
        $value = $this->TryGetScalar($field);
        if ($value !== null && $this->hasCryptoField($field))
        {
            FilesApp::needsCrypto();            
            if (!$account->CryptoAvailable())
                throw new CredentialsEncryptedException();
                
            $nonce = $this->GetScalar($field."_nonce");
            $value = $account->DecryptSecret($value, $nonce);
        }
        
        $this->crypto_cache[$field] = $value; return $value;
    }
    
    protected function SetEncryptedScalar(string $field, ?string $value, bool $credcrypt) : self
    {
        $this->crypto_cache[$field] = $value;

        $account = $this->GetAccount(); $nonce = null;
        if ($value !== null && $credcrypt)
        {
            FilesApp::needsCrypto();            
            if (!$account->hasCrypto())
                throw new CryptoNotAvailableException();
            
            $nonce = CryptoSecret::GenerateNonce();
            $value = $account->EncryptSecret($value, $nonce);            
        }
        $this->SetScalar($field."_nonce", $nonce);
        return $this->SetScalar($field,$value);
    }
    
    protected function SetEncrypted(bool $crypt) : self
    {
        $this->SetUsername($this->TryGetUsername(), $crypt);
        $this->SetPassword($this->TryGetPassword(), $crypt);
    }
      
    public static function DecryptAccount(ObjectDatabase $database, Account $account) : void 
    { 
        $storages = static::LoadByObject($database, 'owner', $account);
        foreach ($storages as $storage) $storage->SetEncrypted(false);
    }
}
