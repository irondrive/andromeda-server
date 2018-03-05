<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class EncryptionFailedException extends Exceptions\ServerException      { public $message = "ENCRYPTION_FAILED"; }
class DecryptionFailedException extends Exceptions\ServerException      { public $message = "DECRYPTION_FAILED"; }

class CryptoSecret
{
    public static function GenerateKey() : string
    {
        return random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
    
    public static function GenerateNonce() : string
    {
        return random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    }
    
    public static function GenerateSalt() : string
    {
        return random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    }
    
    public static function DeriveKey(string $password, string $salt) : string
    {
        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $password, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, 
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
        sodium_memzero($password); return $key;
    }
    
    public static function GenerateAuthKey() : string
    {
        return random_bytes(SODIUM_CRYPTO_AUTH_KEYBYTES);
    }
    
    public static function GenerateAuthNonce() : string
    {
        return random_bytes(SODIUM_CRYPTO_AUTH_NONCEBYTES);
    }
    
    public static function Encrypt(string $data, string $nonce, string $key) : string
    {
        $output = sodium_crypto_secretbox($data, $nonce, $key);
        sodium_memzero($data); sodium_memzero($nonce); sodium_memzero($key);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function Decrypt(string $data, string $nonce, string $key) : string
    {
        $output = sodium_crypto_secretbox_open($data, $nonce, $key);
        sodium_memzero($data); sodium_memzero($nonce); sodium_memzero($key);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }
    
    public static function MakeMAC(string $data, string $key)
    {
        $output = sodium_crypto_auth($data, $key);
        sodium_memzero($data); sodium_memzero($key);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function CheckMAC(string $data, string $message, string $key)
    {
        $output = sodium_crypto_auth_verify($data, $message, $key);
        sodium_memzero($data); sodium_memzero($message); sodium_memzero($key);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }
}

class CryptoPublic
{
    public static function GenerateKeyPair() : array
    {
        $keypair = sodium_crypto_box_keypair();
        return array(
            'public' => sodium_crypto_box_publickey($keypair),
            'private' => sodium_crypto_box_secretkey($keypair),
        );
        return $array;
    }
    
    public static function Encrypt(string $message, string $nonce, string $recipient_public, string $sender_private)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($sender_private, $recipient_public);
        $output = sodium_crypto_box($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($nonce); sodium_memzero($recipient_public); sodium_memzero($sender_private);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function Decrypt(string $message, string $nonce, string $recipient_private, string $sender_public)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($recipient_private, $sender_public);
        $output = sodium_crypto_box_open($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($nonce); sodium_memzero($recipient_public); sodium_memzero($sender_private);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }
}
