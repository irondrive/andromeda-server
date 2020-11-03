<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php");

if (!file_exists(ROOT."/apps/accounts/libraries/GoogleAuthenticator/PHPGangsta/GoogleAuthenticator.php")) 
    die("Missing library: GoogleAuthenticator - git submodule init/update?");
require_once(ROOT."/apps/accounts/libraries/GoogleAuthenticator/PHPGangsta/GoogleAuthenticator.php"); use PHPGangsta_GoogleAuthenticator;

class UsedToken extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'code' => null,         
            'twofactor' => new FieldTypes\ObjectRef(TwoFactor::class, 'usedtokens')
        ));
    }
    
    public function GetCode() : string          { return $this->GetScalar('code'); }
    public function GetTwoFactor() : TwoFactor  { return $this->GetObject('twofactor'); }
    
    public static function Create(ObjectDatabase $database, TwoFactor $twofactor, string $code) : UsedToken 
    {
        return parent::BaseCreate($database)
            ->SetScalar('code',$code)
            ->SetObject('twofactor',$twofactor);            
    }
}

class TwoFactor extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'comment' => null,
            'secret' => null,
            'nonce' => null,
            'valid' => null,         
            'account' => new FieldTypes\ObjectRef(Account::class, 'twofactors'),
            'usedtokens' => new FieldTypes\ObjectRefs(UsedToken::class, 'twofactor')
        ));
    }
    
    const SECRET_LENGTH = 32; const TIME_TOLERANCE = 2;
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetComment() : ?string { return $this->TryGetScalar("comment"); }
    
    private function GetUsedTokens() : array { return $this->GetObjectRefs('usedtokens'); }
    private function CountUsedTokens() : int { return $this->CountObjectRefs('usedtokens'); }

    public function GetIsValid() : bool     { return $this->GetScalar('valid'); }
    public function SetIsValid(bool $data = true) : self { return $this->SetScalar('valid',$data); }
    
    public static function Create(ObjectDatabase $database, Account $account, string $comment = null) : TwoFactor
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        $secret = $ga->createSecret(self::SECRET_LENGTH); $nonce = null;
        
        $nonce = CryptoSecret::GenerateNonce();
        $secret = $account->EncryptSecret($secret, $nonce);
        
        return parent::BaseCreate($database) 
            ->SetScalar('secret',$secret)
            ->SetScalar('nonce',$nonce)
            ->SetScalar('comment',$comment)
            ->SetObject('account',$account);
    }

    public function GetURL() : string
    {
        $ga = new PHPGangsta_GoogleAuthenticator();

        $secret = $this->GetAccount()->DecryptSecret(
            $this->GetScalar('secret'), $this->GetScalar('nonce'));  

        return $ga->getQRCodeGoogleUrl("Andromeda", $secret);
    }
        
    public function CheckCode(string $code) : bool
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        $account = $this->GetAccount(); $secret = $this->GetScalar('secret');        

        $secret = $account->DecryptSecret($secret, $this->GetScalar('nonce'));    

        foreach ($this->GetUsedTokens() as $usedtoken)
        {
            if ($usedtoken->GetDateCreated() < time()-(self::TIME_TOLERANCE*2*30)) $usedtoken->Delete();            
            else if ($usedtoken->GetCode() === $code) return false;
        }

        if (!$ga->verifyCode($secret, $code, self::TIME_TOLERANCE)) return false;

        UsedToken::Create($this->database, $this, $code);

        $this->SetIsValid();
        
        return true;
    }
    
    const OBJECT_METADATA = 0; const OBJECT_WITHSECRET = 1;
    
    public function GetClientObject(int $level = self::OBJECT_METADATA) : array
    {
        $data = array(
            'id' => $this->ID(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
        );
        
        if ($level === self::OBJECT_WITHSECRET) $data['qrcodeurl'] = $this->GetURL();

        return $data;
    }
    
    public function Delete() : void
    {
        if ($this->CountUsedTokens())
            $this->DeleteObjectRefs('usedtokens');
        
        parent::Delete();
    }
}
