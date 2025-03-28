<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\FieldTypes;
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\Crypto\CryptFields;

/** 
 * Trait for storage classes that store a possibly-encrypted username and password
 * @phpstan-type UserPassJ array{username:?string, password:bool, iscrypt:bool}
 */
trait UserPass
{
    use CryptFields\CryptObject;

    /** The optional username to use with the storage */
    protected CryptFields\NullCryptStringType $username;
    /** The nonce to use if the username is encrypted */
    protected FieldTypes\NullStringType $username_nonce;
    /** The optional password to use with the storage */
    protected CryptFields\NullCryptStringType $password;
    /** The nonce to use if the password is encrypted */
    protected FieldTypes\NullStringType $password_nonce;

    protected function UserPassCreateFields() : void
    {
        $fields = array();
        $this->username_nonce = $fields[] = new FieldTypes\NullStringType('username_nonce');
        $this->username = $fields[] = new CryptFields\NullCryptStringType('username',$this->owner,$this->username_nonce);
        $this->password_nonce = $fields[] = new FieldTypes\NullStringType('password_nonce');
        $this->password = $fields[] = new CryptFields\NullCryptStringType('password',$this->owner,$this->password_nonce);
        $this->RegisterChildFields($fields);
    }

    /** @return list<CryptFields\CryptField> */
    protected function GetUserPassCryptFields() : array { return array($this->username, $this->password); }

    /** Returns the command usage for Create() */
    public static function GetUserPassCreateUsage(bool $requireUsername) : string { 
        return ($requireUsername ? "--username alphanum" : "[--username ?alphanum]")." [--password ?raw] [--credcrypt bool]"; }
    
    /** Performs cred-crypt level initialization on a new storage */
    public function UserPassCreate(SafeParams $params, bool $requireUsername) : self
    {
        $this->SetEncrypted($params->GetOptParam('credcrypt',false)->GetBool());
        $this->password->SetValue($params->GetOptParam('password',null,SafeParams::PARAMLOG_NEVER)->GetNullRawString());

        if ($requireUsername)
            $username = $params->GetParam('username')->CheckLength(255)->GetAlphanum();
        else $username = $params->GetOptParam('username',null)->CheckLength(255)->GetNullAlphanum();
        $this->username->SetValue($username);

        return $this;
    }
    
    /** Returns the command usage for Edit() */
    public static function GetUserPassEditUsage(bool $requireUsername) : string { 
        return "[--username ".(!$requireUsername?"?":"")."alphanum] [--password ?raw] [--credcrypt bool]"; }
    
    /** Performs cred-crypt level edit on an existing storage */
    public function UserPassEdit(SafeParams $params, bool $requireUsername) : self
    {
        if ($params->HasParam('credcrypt'))
            $this->SetEncrypted($params->GetParam('credcrypt')->GetBool());
        if ($params->HasParam('password')) 
            $this->password->SetValue($params->GetParam('password',SafeParams::PARAMLOG_NEVER)->GetNullRawString());

        if ($params->HasParam('username')) 
        {
            $username = $params->GetParam('username')->CheckLength(255);
            $this->username->SetValue($requireUsername ? $username->GetAlphanum() : $username->GetNullAlphanum());
        }

        return $this;
    }
    
    /**
     * Returns the printable client object of this trait
     * @return UserPassJ
     */
    public function GetUserPassClientObject() : array 
    {
        return array(
            'iscrypt' => $this->username->isEncrypted(),
            'username' => $this->username->TryGetValue(),
            'password' => $this->password->GetDBValue() !== null
        );
    }
}
