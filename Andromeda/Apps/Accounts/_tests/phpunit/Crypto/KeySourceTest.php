<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Crypto;
use Andromeda\Core\Database\{BaseObject, ObjectDatabase, PDODatabase, TableTypes};
use Andromeda\Core\Exceptions\DecryptionFailedException;
use Andromeda\Apps\Accounts\Account;

class KeySourceTest_KeySource extends BaseObject
{
    use AccountKeySource, TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        $this->AccountKeySourceCreateFields();
        
        parent::CreateFields();
    }

    public static function Create(ObjectDatabase $database, Account $account, string $wrappass) : static
    {
        $obj = $database->CreateObject(static::class);
        $obj->AccountKeySourceCreate($account, $wrappass);
        return $obj;
    }

    public function pubGetMasterKey(bool $raw) : ?string {
        return $raw ? ($this->ssenc_rawkey ?? null) : $this->ssenc_key->TryGetValue(); }

    public function pubGetMasterNonce() : ?string { return $this->ssenc_nonce->TryGetValue(); }
    public function pubGetMasterSalt() : ?string { return $this->ssenc_salt->TryGetValue(); }

    /** @return $this */
    public function pubInitializeCrypto(string $wrappass, bool $fast, bool $rekey = false) : self { 
        return $this->BaseInitializeCrypto($wrappass, $fast, $rekey); }

    /** @return $this */
    public function pubUnlockCrypto(string $wrappass, bool $fast) : self {
        return $this->UnlockCrypto($wrappass, $fast); }

    /** @return $this */
    public function pubLockCrypto() : self {
        return $this->LockCrypto(); }

    /** @return $this */
    public function pubDestroyCrypto() : self {
        return $this->DestroyCrypto(); }

    /** @return $this */
    public function pubInitializeCryptoFromAccount(Account $account, string $wrappass, bool $fast) : self
    {
        $this->account->SetObject($account);
        return $this->InitializeCryptoFromAccount($wrappass, $fast);
    }
}

class KeySourceTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyKeySource1() : void
    {
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);

        $this->assertFalse($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $this->assertNull($obj->pubGetMasterKey(false));
        $this->assertNull($obj->pubGetMasterKey(true));

        $this->expectException(Exceptions\CryptoUnlockRequiredException::class);
        $obj->EncryptSecret("test", "nonce");
    }

    public function testEmptyKeySource2() : void
    {
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);

        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubUnlockCrypto("testkey", fast:true);
    }

    public function testInitCrypto() : void
    {
        foreach ([false,true] as $fast)
        {
            $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);
            $obj->pubInitializeCrypto($wrappass = "wraptest", fast:$fast);

            $rawkey = $obj->pubGetMasterKey(true);
            $enckey = $obj->pubGetMasterKey(false);
            $this->assertSame($rawkey, $obj->GetMasterKey());
            $this->assertNotSame($rawkey, $enckey);

            $this->assertTrue($obj->hasCrypto());
            $this->assertTrue($obj->isCryptoAvailable());

            $obj->pubInitializeCrypto($wrappass = "wraptest", fast:$fast, rekey:true); // rekey
            $this->assertSame($rawkey, $obj->pubGetMasterKey(true));
            $this->assertNotSame($enckey, $obj->pubGetMasterKey(false));
        }
    }

    public function testReinitCrypto() : void
    {
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj->pubInitializeCrypto("wraptest", fast:true);

        $this->expectException(Exceptions\CryptoAlreadyInitializedException::class);
        $obj->pubInitializeCrypto("any password", fast:true);
    }

    public function testUnlockEncrypt() : void
    {
        $wrappass = "mytest123";
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [
            'ssenc_key' => base64_decode('cpVmEfKzpMenkbWRKpQ/AgiH5Qg8Fq0GBBZLtua6ZRtLgTKurdOTcs/ee58OL/hW'),
            'ssenc_nonce' => base64_decode('RoVLkhq96k0mD570YihwOTHtFdfsUkmD'),
            'ssenc_salt' => base64_decode('mTKpifFEtFs94VjjAnSY4A==')
        ], false);
        
        /*$obj2 = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj2->pubInitializeCrypto($wrappass,fast:true);
        echo " ".base64_encode($obj2->pubGetMasterKey())."\n";
        echo " ".base64_encode($obj2->pubGetMasterNonce())."\n";
        echo " ".base64_encode($obj2->pubGetMasterSalt())."\n";*/

        $this->assertTrue($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $obj->pubUnlockCrypto($wrappass, fast:false);
        $this->assertTrue($obj->isCryptoAvailable());

        $ctext = $obj->EncryptSecret($ptext="plain text test",$nonce=Crypto::GenerateSecretNonce());
        $this->assertNotSame($ctext, $ptext);
        $this->assertSame($ptext, $obj->DecryptSecret($ctext,$nonce));
        
        $obj->pubLockCrypto();
        $this->expectException(DecryptionFailedException::class);
        $obj->pubUnlockCrypto("some bad password", fast:false);
    }

    public function testUnlockEncryptFast() : void
    {
        $wrappass = "mytest123";
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [
            'ssenc_key' => base64_decode('D3ua0rnsaJShyzPpSmRM6nZFNhilol4O7RAplDDbzbo59cmgToULzs2MfO24h24g'),
            'ssenc_nonce' => base64_decode('cifShF6AyQt1S8PqZyaV3mUQI10VdwcN')
        ], false);

        /*$obj2 = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj2->pubInitializeCrypto($wrappass,fast:true);
        echo " ".base64_encode($obj2->pubGetMasterKey(raw:false))."\n";
        echo " ".base64_encode($obj2->pubGetMasterNonce())."\n";*/
        
        $this->assertTrue($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $obj->pubUnlockCrypto($wrappass, fast:true);
        $this->assertTrue($obj->isCryptoAvailable());

        $ctext = $obj->EncryptSecret($ptext="plain text test2",$nonce=Crypto::GenerateSecretNonce());
        $this->assertNotSame($ctext, $ptext);
        $this->assertSame($ptext, $obj->DecryptSecret($ctext,$nonce));
        
        $obj->pubLockCrypto();
        $this->expectException(DecryptionFailedException::class);
        $obj->pubUnlockCrypto("some bad password", fast:true);
    }

    public function testDestroyCrypto() : void
    {
        $obj = new KeySourceTest_KeySource($this->createMock(ObjectDatabase::class), [], false);
        $obj->pubInitializeCrypto($wrappass = "wraptest", fast:true);

        $this->assertTrue($obj->hasCrypto());
        $this->assertTrue($obj->isCryptoAvailable());

        $obj->pubDestroyCrypto();
        $this->assertFalse($obj->hasCrypto());
        $this->assertFalse($obj->isCryptoAvailable());

        $this->expectException(Exceptions\CryptoNotInitializedException::class);
        $obj->pubUnlockCrypto($wrappass, fast:true);
    }

    public function testFromAccount() : void
    {
        $objdb = $this->createMock(ObjectDatabase::class);
        $account = new Account($objdb, ['id'=>'myid12'], false);
        $account->InitializeCrypto($password="test123");
        $this->assertTrue($account->isCryptoAvailable());

        $obj = new KeySourceTest_KeySource($objdb, [], false);
        $obj->pubInitializeCryptoFromAccount($account, $wrappass="wrap123", fast:true);

        $this->assertTrue($obj->hasCrypto());
        $this->assertTrue($obj->isCryptoAvailable());

        $ctext = $account->EncryptSecret($ptext="plain text!",$nonce=Crypto::GenerateSecretNonce());
        $this->assertSame($ptext, $obj->DecryptSecret($ctext, $nonce)); // $obj can decrypt stuff encrypted by account!

        $this->expectException(Exceptions\CryptoAlreadyInitializedException::class);
        $obj->pubInitializeCryptoFromAccount($account, $wrappass, fast:true);
    }

    public function testKeySourceCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = new Account($objdb, ['id'=>'myid12'], false);

        $obj = KeySourceTest_KeySource::Create($objdb, $account, $wrappass="test");
        $this->assertSame($account, $obj->GetAccount());
        $this->assertFalse($obj->hasCrypto());

        $account->InitializeCrypto("testpw");
        $obj = KeySourceTest_KeySource::Create($objdb, $account, $wrappass);
        $this->assertTrue($obj->hasCrypto()); // automatic!
    }
}

