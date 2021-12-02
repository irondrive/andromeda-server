<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Apps/Accounts/Account.php");

/** Object for tracking used two factor codes, to prevent replay attacks */
class UsedToken extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'code' => null,         
            'twofactor' => new FieldTypes\ObjectRef(TwoFactor::class, 'usedtokens')
        ));
    }
    
    /** Returns the value of the used token */
    public function GetCode() : string { return $this->GetScalar('code'); }
    
    /** Returns the two factor code this is associated with */
    public function GetTwoFactor() : TwoFactor { return $this->GetObject('twofactor'); }
    
    /** Prunes old codes from the database that are too old to be valid anyway */
    public static function PruneOldCodes(ObjectDatabase $database) : void
    {
        $mintime = Main::GetInstance()->GetTime()-(TwoFactor::TIME_TOLERANCE*2*30);
        $q = new QueryBuilder(); $q->Where($q->LessThan('dates__created', $mintime));
        static::DeleteByQuery($database, $q);
    }
    
    /** Logs a used token with the given twofactor object and code */
    public static function Create(ObjectDatabase $database, TwoFactor $twofactor, string $code) : UsedToken 
    {
        return parent::BaseCreate($database)->SetObject('twofactor',$twofactor)->SetScalar('code',$code);            
    }
}

/** 
 * Describes an OTP twofactor authentication source for an account 
 * 
 * Accounts can have > 1 of these so the user is able to use multiple devices.
 * If account crypto is available, the secret is stored encrypted in the database.
 */
class TwoFactor extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'comment' => null,
            'secret' => null,
            'nonce' => null,
            'valid' => null,
            'dates__used' => null,
            'account' => new FieldTypes\ObjectRef(Account::class, 'twofactors'),
            'usedtokens' => (new FieldTypes\ObjectRefs(UsedToken::class, 'twofactor'))->autoDelete()
        ));
    }
    
    /** The length of the OTP secret */
    const SECRET_LENGTH = 32; 
    
    /** the time tolerance for codes, as a multiple of 30-seconds */
    const TIME_TOLERANCE = 2;
    
    /** Gets the account that owns this object */
    public function GetAccount() : Account { return $this->GetObject('account'); }
    
    /** Gets the comment/label the user assigned to this object */
    public function GetComment() : ?string { return $this->TryGetScalar("comment"); }
    
    /**
     * Gets an array of used tokens
     * @return array<string, UsedToken> used tokens indexed by ID
     */
    private function GetUsedTokens() : array { return $this->GetObjectRefs('usedtokens'); }

    /** Returns whether this twofactor has been validated */
    public function GetIsValid() : bool { return $this->GetScalar('valid'); }
    
    /** Returns whether the OTP secret is stored encrypted */
    public function hasCrypto() : bool { return $this->TryGetScalar('nonce') !== null; }
    
    /** 
     * Tries to load a two factor object by the given account and ID
     * @return ?self the loaded object or null if not found */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /** Creates and returns a new twofactor object for the given account */
    public static function Create(ObjectDatabase $database, Account $account, string $comment = null) : TwoFactor
    {
        $obj = parent::BaseCreate($database)
                ->SetScalar('comment',$comment)
                ->SetObject('account',$account);
        
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $obj->SetScalar('secret', $ga->createSecret(self::SECRET_LENGTH));
        
        if ($account->hasCrypto()) $obj->InitializeCrypto();
        
        return $obj;
    }
    
    /** Returns the (decrypted) OTP secret */
    private function GetSecret() : string
    {
        $secret = $this->GetScalar('secret');
        
        if ($this->hasCrypto())
        {
            $secret = $this->GetAccount()->DecryptSecret(
                $secret, $this->GetScalar('nonce'));
        }
        
        return $secret;
    }
    
    /** Stores the secret as encrypted by the owner */
    public function InitializeCrypto() : self
    {
        $nonce = CryptoSecret::GenerateNonce();
        
        $secret = $this->GetAccount()->EncryptSecret($this->GetSecret(), $nonce);  
        
        return $this->SetScalar('nonce',$nonce)->SetScalar('secret',$secret);
    }
    
    /** Stores the secret as plaintext (not encrypted) */
    public function DestroyCrypto() : self
    {
        return $this->SetScalar('secret',$this->GetSecret())->SetScalar('nonce',null);
    }
    
    /** Checks and returns whether the given twofactor code is valid */
    public function CheckCode(string $code) : bool
    {
        UsedToken::PruneOldCodes($this->database);
        
        foreach ($this->GetUsedTokens() as $usedtoken)
        {
            if ($usedtoken->GetCode() === $code) return false;
        }

        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        if (!$ga->verifyCode($this->GetSecret(), $code, self::TIME_TOLERANCE)) return false;

        $this->SetScalar('valid', true)->SetDate('used');
        
        UsedToken::Create($this->database, $this, $code);       
        
        return true;
    }
    
    /** Returns a Google URL for viewing a QR code of the OTP secret */
    public function GetURL() : string
    {
        $ga = new \PHPGangsta_GoogleAuthenticator();
        
        return $ga->getQRCodeGoogleUrl("Andromeda", $this->GetSecret());
    }
    
    /**
     * Returns a printable client object for this twofactor
     * @param bool $secret if true, show the OTP secret
     * @return array `{id:id, comment:?string, dates:{created:float,used:?float}` \
        if $secret, add `{secret:string, qrcodeurl:string}`
     */
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'comment' => $this->GetComment(),
            'dates' => array(
                'created' => $this->GetDateCreated(),
                'used' => $this->TryGetDate('used')
            ),
        );
        
        if ($secret) 
        {
            $data['secret'] = $this->GetSecret();
            $data['qrcodeurl'] = $this->GetURL();
        }

        return $data;
    }
}
