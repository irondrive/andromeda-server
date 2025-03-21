<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; require_once("init.php");

use Andromeda\Core\Database\{ObjectDatabase, PDODatabase};
use Andromeda\Apps\Accounts\{Account, Config};

class SessionTest_Session extends Session
{
    public function pubGetAuthKey() : string { return $this->GetAuthKey(); }
    public function pubLockCrypto() : void { $this->LockCrypto(); }
}

class SessionTest_Account extends Account
{
    public function pubLockCrypto() : void { $this->LockCrypto(); }
}

class SessionTest extends \PHPUnit\Framework\TestCase
{
    public function testSessionCreate() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class));
        $account = $this->createMock(Account::class);

        $session = SessionTest_Session::Create($objdb, $account, $this->createMock(Client::class));
        $this->assertSame($account, $session->GetAccount());
        $this->assertNotEmpty($key=$session->pubGetAuthKey());
        $this->assertFalse($session->CheckKeyMatch("test123"));
        $this->assertTrue($session->CheckKeyMatch($key));
    }

    public function testSessionCrypto() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class), false);
        $account = new SessionTest_Account($objdb, ['id'=>'acct125'], false);
        Config::Create($objdb)->Save(); // init singleton

        $session = SessionTest_Session::Create($objdb, $account, $this->createMock(Client::class));
        $this->assertFalse($session->hasCrypto());

        $account->InitializeCrypto("test123");
        $session->InitializeCrypto();
        $this->assertTrue($session->isCryptoAvailable());

        $session = SessionTest_Session::Create($objdb, $account, $this->createMock(Client::class));
        $this->assertTrue($session->isCryptoAvailable());

        $session->pubLockCrypto();
        $account->pubLockCrypto();
        $this->assertTrue($session->CheckKeyMatch($session->pubGetAuthKey()));
        $this->assertFalse($session->isCryptoAvailable()); // CheckKeyMatch does NOT unlock crypto
        $session->UnlockCrypto();
        $this->assertTrue($session->isCryptoAvailable());
        $this->assertTrue($account->isCryptoAvailable()); // from the session
    }

    public function testSessionTimeout() : void
    {
        $objdb = new ObjectDatabase($this->createMock(PDODatabase::class), false);
        $account = $this->createMock(Account::class);

        $account->method('GetSessionTimeout')->willReturn(0); // impossible to pass
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('SetActiveDate');

        $session = SessionTest_Session::Create($objdb, $account, $client);
        $this->assertTrue($session->CheckKeyMatch($session->pubGetAuthKey())); // no previous date active
        $this->assertFalse($session->CheckKeyMatch($session->pubGetAuthKey())); // date active was set, now expired
    }
}
