<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

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
    
    public static  function PruneOldCodes(ObjectDatabase $database) : void
    {
        $mintime = Main::GetInstance()->GetTime()-(TwoFactor::TIME_TOLERANCE*2*30);
        $q = new QueryBuilder(); $q->Where($q->LessThan('dates__created', $mintime));
        static::DeleteByQuery($database, $q);
    }
    
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
    
    const SECRET_LENGTH = 32; const TIME_TOLERANCE = 1;
    
    public function GetAccount() : Account { return $this->GetObject('account'); }
    public function GetComment() : ?string { return $this->TryGetScalar("comment"); }
    
    private function GetUsedTokens() : array { return $this->GetObjectRefs('usedtokens'); }
    private function CountUsedTokens() : int { return $this->CountObjectRefs('usedtokens'); }

    public function GetIsValid() : bool     { return $this->GetScalar('valid'); }
    public function SetIsValid(bool $data = true) : self { return $this->SetScalar('valid',$data); }
    
    public function hasCrypto() : bool { return $this->TryGetScalar('nonce') !== null; }
    
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->Equals('id',$id));
        return self::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    public static function Create(ObjectDatabase $database, Account $account, string $comment = null) : TwoFactor
    {
        $obj = parent::BaseCreate($database)
                ->SetScalar('comment',$comment)
                ->SetObject('account',$account);
        
        $ga = new PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret(self::SECRET_LENGTH);
        
        if ($account->hasCrypto())
        {        
            $nonce = CryptoSecret::GenerateNonce();
            $secret = $account->EncryptSecret($secret, $nonce);            
            $obj->SetScalar('nonce',$nonce);
        }
        
        return $obj->SetScalar('secret',$secret);
    }
    
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
        
    public function CheckCode(string $code) : bool
    {
        UsedToken::PruneOldCodes($this->database);        
        
        foreach ($this->GetUsedTokens() as $usedtoken)
        {
            if ($usedtoken->GetCode() === $code) return false;
        }

        $ga = new PHPGangsta_GoogleAuthenticator();
        
        if (!$ga->verifyCode($this->GetSecret(), $code, self::TIME_TOLERANCE)) return false;

        $this->SetIsValid(); UsedToken::Create($this->database, $this, $code);       
        
        return true;
    }
    
    public function GetURL() : string
    {
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        return $ga->getQRCodeGoogleUrl("Andromeda", $this->GetSecret());
    }
    
    public function GetClientObject(bool $secret = false) : array
    {
        $data = array(
            'id' => $this->ID(),
            'comment' => $this->GetComment(),
            'dates' => $this->GetAllDates(),
        );
        
        if ($secret) 
        {
            $data['secret'] = $this->GetSecret();
            $data['qrcodeurl'] = $this->GetURL();
        }

        return $data;
    }

    public function Delete() : void
    {
        if ($this->CountUsedTokens())
            $this->DeleteObjectRefs('usedtokens');
        
        parent::Delete();
    }
}
