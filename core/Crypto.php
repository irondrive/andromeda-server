<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

if (!function_exists('sodium_memzero')) die("PHP Sodium Extension Required\n");

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class EncryptionFailedException extends Exceptions\ServerException      { public $message = "ENCRYPTION_FAILED"; }
class DecryptionFailedException extends Exceptions\ServerException      { public $message = "DECRYPTION_FAILED"; }

class CryptoKey
{
    public static function GenerateSalt() : string
    {
        return random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    }
    
    public static function DeriveKey(string $password, string $salt, int $bytes) : string
    {
        $key = sodium_crypto_pwhash(
            $bytes, $password, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE);
        sodium_memzero($password); return $key;
    }
}

class CryptoSecret
{
    public static function KeyLength() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES; }
    public static function NonceLength() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES; }
    
    public static function OutputOverhead() : int { return SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES; }
    
    public static function GenerateKey() : string { return random_bytes(self::KeyLength()); }    
    public static function GenerateNonce() : string { return random_bytes(self::NonceLength()); }
    
    public static function Encrypt(string $data, string $nonce, string $key, string $extra = null) : string
    {
        $output = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, $extra, $nonce, $key);
        sodium_memzero($data); sodium_memzero($key);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function Decrypt(string $data, string $nonce, string $key, string $extra = null) : string
    {
        $output = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($data, $extra, $nonce, $key);
        sodium_memzero($data); sodium_memzero($key);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }
}

class CryptoAuth
{
    public static function KeyLength() : int { return SODIUM_CRYPTO_AUTH_KEYBYTES; }
    public static function NonceLength() : int { return SODIUM_CRYPTO_AUTH_NONCEBYTES; }
    
    public static function GenerateKey() : string { return random_bytes(self::KeyLength()); }
    public static function GenerateNonce() : string { return random_bytes(self::NonceLength()); }
    
    public static function MakeAuthCode(string $message, string $key)
    {
        $output = sodium_crypto_auth($message, $key);
        sodium_memzero($message); sodium_memzero($key);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function CheckAuthCode(string $mac, string $message, string $key)
    {
        $output = sodium_crypto_auth_verify($mac, $message, $key);
        sodium_memzero($mac); sodium_memzero($message); sodium_memzero($key);
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
    }
    
    public static function Encrypt(string $message, string $nonce, string $recipient_public, string $sender_private)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($sender_private, $recipient_public);
        $output = sodium_crypto_box($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($sender_private);
        if ($output === false) throw new EncryptionFailedException();
        return $output;
    }
    
    public static function Decrypt(string $message, string $nonce, string $recipient_private, string $sender_public)
    {
        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($recipient_private, $sender_public);
        $output = sodium_crypto_box_open($message, $nonce, $keypair);
        sodium_memzero($message); sodium_memzero($recipient_private);
        if ($output === false) throw new DecryptionFailedException();
        return $output;
    }
}
