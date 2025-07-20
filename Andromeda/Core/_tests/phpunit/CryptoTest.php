<?php declare(strict_types=1); namespace Andromeda\Core; require_once("init.php");

class CryptoTest extends \PHPUnit\Framework\TestCase
{
    public function testBase64Encode() : void
    {
        $this->assertSame(Crypto::base64_encode(""), "");
        $this->assertSame(Crypto::base64_encode("a"), "YQ=="); // 2==
        $this->assertSame(Crypto::base64_encode("ab"), "YWI="); // 1==
        $this->assertSame(Crypto::base64_encode("abc"), "YWJj"); // 0==
    
        $this->assertSame(Crypto::base64_encode("What's in a name? That which we call a rose By any other word would smell as sweet."),
            "V2hhdCdzIGluIGEgbmFtZT8gVGhhdCB3aGljaCB3ZSBjYWxsIGEgcm9zZSBCeSBhbnkgb3RoZXIgd29yZCB3b3VsZCBzbWVsbCBhcyBzd2VldC4=");
    
        $this->assertSame(Crypto::base64_encode("\x10\x00\x21\xD0\x9C\x61\xFF\x46"), "EAAh0Jxh/0Y=");
    }

    public function testBase64Decode() : void
    {
        $this->assertSame(Crypto::base64_decode(""), "");

        $this->assertSame(Crypto::base64_decode("YQ=="), "a"); // 2==
        $this->assertSame(Crypto::base64_decode("YWI="), "ab"); // 1==
        $this->assertSame(Crypto::base64_decode("YWJj"), "abc"); // 0==

        $this->assertSame(Crypto::base64_decode("V2hhdCdzIGluIGEgbmFtZT8gVGhhdCB3aGljaCB3ZSBjYWxsIGEgcm9zZSBCeSBhbnkgb3RoZXIgd29yZCB3b3VsZCBzbWVsbCBhcyBzd2VldC4="),
            "What's in a name? That which we call a rose By any other word would smell as sweet.");

        $this->assertSame(Crypto::base64_decode("EAAh0Jxh/0Y="), "\x10\x00\x21\xD0\x9C\x61\xFF\x46");

        $this->assertFalse(Crypto::base64_decode(" "));
        $this->assertFalse(Crypto::base64_decode("\0"));
        $this->assertFalse(Crypto::base64_decode("YQ==\0"));
        $this->assertFalse(Crypto::base64_decode("not valid")); // spaces
        $this->assertFalse(Crypto::base64_decode("123456 ")); // spaces
        $this->assertFalse(Crypto::base64_decode(" 123456")); // spaces
        $this->assertFalse(Crypto::base64_decode("YWI")); // missing padding
        $this->assertFalse(Crypto::base64_decode("YWIax")); // missing padding
        $this->assertFalse(Crypto::base64_decode("YWIaxy")); // missing padding
    }

    public function testGenerateSalt() : void
    {
        $this->assertSame(16, Crypto::SaltLength());
        $salt = Crypto::GenerateSalt();
        $this->assertSame(16, strlen($salt));
    }

    public function testDeriveKey() : void
    {
        $password = "mypassword123";
        $salt = "0123456789ABCDEF"; // 16 bytes!

        $this->assertSame(
            "6bf2e7a99d16a81842bf694fc6aae064",
            bin2hex(Crypto::DeriveKey($password, $salt, 16)));
        
        $this->assertSame(
            "0d347291ce9eca4b88e9be36af8a05aeac624c724bd17f5d",
            bin2hex(Crypto::DeriveKey($password, $salt, 24)));
        
        $this->assertSame(
            "a49dd97a617acdc03bbd4f3003b9d5d494c7ff69bd22218495e6dde729f6f11f",
            bin2hex(Crypto::DeriveKey($password, $salt, 32)));
    }

    public function testDeriveSubkey() : void
    {
        $this->assertSame([16,64], Crypto::SubkeySizeRange());
        $this->assertSame(32, Crypto::SuperKeyLength());

        $superkey = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        $this->assertSame("197bd77d3169885c028473c3025bf0f8", 
            bin2hex(Crypto::DeriveSubkey($superkey, 0, "ctx00000", 16)));
        $this->assertSame("d80f682e475e007ea535b218b7109b34ca411084525ed42b",
            bin2hex(Crypto::DeriveSubkey($superkey, 0, "ctx00000", 24)));
        $this->assertSame("27a54c21da4873a538adde23022acc7d",
            bin2hex(Crypto::DeriveSubkey($superkey, 1, "ctx00000", 16)));
        $this->assertSame("c104bb011b4d5184c3ebb5f557f57b19",
            bin2hex(Crypto::DeriveSubkey($superkey, 0, "ctx10000", 16)));
    }

    public function testFastHash() : void
    {
        $this->assertSame(32, Crypto::FastHashKeyLength());
        $key = Crypto::GenerateFastHashKey();
        $this->assertSame(32, strlen($key));

        $this->assertSame("cae66941d9efbd404e4d88758ea67670",
            bin2hex(Crypto::FastHash("", 16)));
        $this->assertSame("8636a4e2cb8939774951ad4a760d12b3",
            bin2hex(Crypto::FastHash("message123", 16)));
        $this->assertSame("c7f56c49d29a82da86493b034de5f973ce5ad79852517fc8",
            bin2hex(Crypto::FastHash("message123", 24)));
        $this->assertSame("50d9da7dcdc5ea966b81f4a28cd9c738",
            bin2hex(Crypto::FastHash("message1234", 16)));

        $key = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        $this->assertSame("8ce2781336fbb0df41619d52fac1e2ab",
            bin2hex(Crypto::FastHash("message123", 16, $key)));
    }

    public function testCryptoSecret() : void
    {
        $this->assertSame(32, Crypto::SecretKeyLength());
        $this->assertSame(24, Crypto::SecretNonceLength());
        $this->assertSame(16, Crypto::SecretOutputOverhead());
        
        $this->assertSame(32, strlen(Crypto::GenerateSecretKey()));
        $this->assertSame(24, strlen(Crypto::GenerateSecretNonce()));
        
        $key = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        $nonce = "0123456789ABCDEF01234567"; // 24 bytes
        
        $msg = "my super secret data...";
                
        $this->assertSame(
            "5b394ad117f06e2622974cb5792d1ad41016613a37d9730b54fb214aae80efcd9218c03f0cc81e", 
            bin2hex($enc = Crypto::EncryptSecret($msg, $nonce, $key)));
        $this->assertSame(strlen($msg)+Crypto::SecretOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptSecret($enc, $nonce, $key));
        
        $extra = "extra auth data...";
        
        $this->assertSame(
            "5b394ad117f06e2622974cb5792d1ad41016613a37d973ff34324579b2d4bd0f815a507afc5215",
            bin2hex($enc = Crypto::EncryptSecret($msg, $nonce, $key, $extra)));
        $this->assertSame(strlen($msg)+Crypto::SecretOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptSecret($enc, $nonce, $key, $extra));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        $badkey = "1113456789ABCDEF0123456789ABCDEF"; // 32 bytes
        Crypto::DecryptSecret($enc, $nonce, $badkey, $extra);
    }
    
    public function testCryptoPublic() : void
    {
        $this->assertSame(24, Crypto::PublicNonceLength());
        $this->assertSame(16, Crypto::PublicOutputOverhead());
        $this->assertSame(24, strlen(Crypto::GeneratePublicNonce()));
        
        $pair = Crypto::GeneratePublicKeyPair();   
        $this->assertSame(32, strlen($pair['public']));
        $this->assertSame(32, strlen($pair['private']));
        
        $pub1 = (string)hex2bin("3dc676268fc36f9fe45b065186390adbcd88fadcb8f9e6384da5dd362a80ac2d");
        $priv1 = (string)hex2bin("292053109b4f6f89ebbde27771dc4b40bdddffb7c420064cd86d1e805f2659a4");
        
        $pub2 = (string)hex2bin("62d8459524005df5f4db734453e8069926b5631435545a81df45852f032d1447");
        $priv2 = (string)hex2bin("cf05a70e088db92bf3a0ee12e2e85f9f6f73bea2d73ab3c3d90c789123f3d3e0");
        
        $msg = "my super secret data...";
        $nonce = "0123456789ABCDEF01234567"; // 24 bytes
        
        $this->assertSame(
            "ff05ee407b1f91c764e112f9c59a9716055a62d0022dd931c57f50fd3c11dfca559adaba70b73e",
            bin2hex($enc = Crypto::EncryptPublic($msg, $nonce, $priv1, $pub2)));
        $this->assertSame(strlen($msg)+Crypto::PublicOutputOverhead(), strlen($enc));
        
        $this->assertSame($msg, Crypto::DecryptPublic($enc, $nonce, $priv2, $pub1));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        Crypto::DecryptPublic($enc, $nonce, $priv1, $pub1);
    }
    
    public function testCryptoAuth() : void
    {
        $this->assertSame(32, Crypto::AuthKeyLength());  
        $this->assertSame(32, Crypto::AuthTagLength());
        $this->assertSame(32, strlen(Crypto::GenerateAuthKey()));
        
        $msg = "this should be authenticated...";
        $key = "0123456789ABCDEF0123456789ABCDEF"; // 32 bytes
        
        $this->assertSame(
            "c4d6a1077b910511bb3f9af766e469726d3e461783f48cf156e8d9966434dbc4",
            bin2hex($mac = Crypto::MakeAuthCode($msg, $key)));
        
        $this->assertTrue(Crypto::TryCheckAuthCode($mac, $msg, $key));
        Crypto::AssertAuthCode($mac, $msg, $key); // no throw
        
        $badmac = (string)hex2bin("abc6a1077b910511bb3f9af766e469726d3e461783f48cf156e8d9966434dbc4");
        $this->assertFalse(Crypto::TryCheckAuthCode($badmac, $msg, $key));
        
        $this->expectException(Exceptions\DecryptionFailedException::class);
        Crypto::AssertAuthCode($badmac, $msg, $key);
    }
}
