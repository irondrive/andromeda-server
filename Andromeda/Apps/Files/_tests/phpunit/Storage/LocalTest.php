<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Storage; require_once("init.php");

use Andromeda\Core\Utilities;
use Andromeda\Core\Database\ObjectDatabase;
use Andromeda\Core\IOFormat\{Input, SafeParams};
use Andromeda\Apps\Files\Filesystem;

/** basic tests for Storage, FWrapper, and Local */
class LocalTest extends \PHPUnit\Framework\TestCase
{
    /** @var array<string> $tmpfolders */
    private array $tmpfolders = array();

    public static function rrmdir(string $path) : void
    {
        $dir = opendir($path);
        assert(is_resource($dir));
        while(($file = readdir($dir)) !== false)
        {
            if (( $file !== '.' ) && ( $file !== '..' ))
            {
                $full = "$path/$file";
                if (is_dir($full)) self::rrmdir($full);
                else unlink($full);
            }
        }
        closedir($dir);
        rmdir($path);
    }

    public function tearDown() : void
    {
        foreach ($this->tmpfolders as $tmpfolder)
            self::rrmdir($tmpfolder); // recursive
    }

    /** @param array<string,?scalar> $data */
    protected function getStorage(array $data = []) : Local
    {
        $data['id'] = Utilities::Random(12);
        
        $path = sys_get_temp_dir()."/a2unit_".Utilities::Random(8);
        mkdir($this->tmpfolders[] = $data['path'] = $path);

        $database = $this->createMock(ObjectDatabase::class);
        return new Local($database, $data, false);
    }

    public function testBasicStorage() : void
    {
        $storage = $this->getStorage();
        $this->assertFalse($storage->isUserOwned());
        $this->assertNull($storage->TryGetOwner());
        $this->assertNull($storage->TryGetOwnerID());
        $this->assertSame(Storage::DEFAULT_NAME, $storage->GetName());
        $this->assertFalse($storage->isReadOnly());

        $this->assertTrue($storage->canGetFreeSpace());
        $this->assertTrue($storage->supportsFolders());
        $this->assertFalse($storage->usesBandwidth());

        $storage->SetName($name='mytest');
        $this->assertSame($name, $storage->GetName());
        $storage->SetName(null);
        $this->assertSame(Storage::DEFAULT_NAME, $storage->GetName());

        $storage->Test(false); // tmp is writeable
        $this->assertGreaterThan(0, $storage->GetFreeSpace());
    }

    public function testGetFilesystem() : void
    {
        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_NATIVE]);
        $this->assertFalse($storage->isEncrypted());
        $this->assertFalse($storage->isExternal());
        $this->assertInstanceOf(Filesystem\Native::class, $storage->GetFilesystem());

        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_NATIVE_CRYPT,'crypto_masterkey'=>'aaa','crypto_chunksize'=>50]);
        $this->assertTrue($storage->isEncrypted());
        $this->assertFalse($storage->isExternal());
        $fs = $storage->GetFilesystem();
        assert($fs instanceof Filesystem\NativeCrypt);
        $this->assertSame(50, $fs->GetChunkSize());

        $storage = $this->getStorage(['fstype'=>Storage::FSTYPE_EXTERNAL]);
        $this->assertFalse($storage->isEncrypted());
        $this->assertTrue($storage->isExternal());
        $this->assertInstanceOf(Filesystem\External::class, $storage->GetFilesystem());

        $storage = $this->getStorage(['fstype'=>999]);
        $this->expectException(Exceptions\InvalidFSTypeException::class);
        $storage->GetFilesystem();
    }

    public function testBasicOps() : void
    {
        $storage = $this->getStorage();
        $storage->ItemStat(""); // root
        $this->assertFalse($storage->isFile(""));
        $this->assertTrue($storage->isFolder(""));

        $storage->CreateFile($p="testfile");
        $s = $storage->ItemStat($p);
        $this->assertGreaterThan(0, $s->atime);
        $this->assertGreaterThan(0, $s->ctime);
        $this->assertGreaterThan(0, $s->mtime);
        $this->assertTrue($storage->isFile($p));
        $this->assertFalse($storage->isFolder($p));

        $storage->CreateFolder($p="testfolder");
        $s = $storage->ItemStat($p);
        $this->assertGreaterThan(0, $s->atime);
        $this->assertGreaterThan(0, $s->ctime);
        $this->assertGreaterThan(0, $s->mtime);
        $this->assertFalse($storage->isFile($p));
        $this->assertTrue($storage->isFolder($p));
        
        $storage->CreateFile($p="testfolder/test2");
        $storage->ItemStat($p);
        $this->assertTrue($storage->isFile($p));
        $this->assertFalse($storage->isFolder($p));

        $this->expectException(Exceptions\ItemStatFailedException::class);
        $storage->ItemStat('testfolder/nonexistent');
    }

    public function testReadOnly() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($fo="myfolder");
        $storage->CreateFile($fi="myfile");
        $storage->WriteBytes($fi, 0, $dat="myfiledata");

        $storage->Edit(new Input('a','a',(new SafeParams())->AddParam('readonly',true)));
        $this->assertTrue($storage->isReadOnly());

        // ReadFolder/Bytes and ItemStat work when read-only
        $this->assertTrue($storage->isFolder($fo));
        $storage->ItemStat($fo);
        $storage->ReadFolder($fo);

        $this->assertSame(strlen($dat), $storage->ItemStat($fi)->size);
        $this->assertSame($dat, $storage->ReadBytes($fi, 0, strlen($dat)));
    }

    public function testReadOnlyCreateFolder() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->CreateFolder('mytest');
    }

    public function testReadOnlyDeleteFolder() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->DeleteFolder('mytest');
    }

    public function testReadOnlyRenameFolder() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->RenameFolder('mytest','mytest2');
    }

    public function testReadOnlyMoveFolder() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->MoveFolder('mytest','mytest2');
    }

    public function testReadOnlyCreateFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->CreateFile('mytest');
    }

    public function testReadOnlyImportFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->ImportFile('mytest','mytest2',true);
    }

    public function testReadOnlyWriteBytes() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->WriteBytes('mytest',0,'test');
    }

    public function testReadOnlyTruncate() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->Truncate('mytest',0);
    }

    public function testReadOnlyDeleteFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->DeleteFile('mytest');
    }

    public function testReadOnlyRenameFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->RenameFile('mytest','mytest2');
    }

    public function testReadOnlyMoveFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->MoveFile('mytest','mytest2');
    }

    public function testReadOnlyCopyFile() : void
    {
        $storage = $this->getStorage(['readonly'=>true]);
        $this->expectException(Exceptions\ReadOnlyException::class);
        $storage->CopyFile('mytest','mytest2');
    }

    public function testReadFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p="mytest");
        $storage->CreateFolder("$p/2");
        $storage->CreateFile("$p/3.txt");
        $this->assertEqualsCanonicalizing(['2','3.txt'], $storage->ReadFolder($p));

        $this->expectException(Exceptions\FolderReadFailedException::class);
        $storage->ReadFolder("$p/3.txt");
    }

    public function testCreateFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p="mytest");
        $storage->CreateFolder($p); // succeeds
        $this->assertTrue($storage->isFolder($p));
        $storage->CreateFolder("$p/2");
        $storage->CreateFolder("$p/2/3");

        $this->expectException(Exceptions\FolderCreateFailedException::class);
        $storage->CreateFolder("myfolder1/myfolder2"); // parent does not exist
    }

    public function testCreateFile() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="testfile");
        $this->assertTrue($storage->isFile($p));
        $this->assertSame(0, $storage->ItemStat($p)->size);

        // test overwrite
        $storage->WriteBytes($p,0,Utilities::Random($s=55));
        $this->assertSame($s, $storage->ItemStat($p)->size);

        $storage->CreateFile($p);
        $this->assertSame(0, $storage->ItemStat($p)->size);

        $this->expectException(Exceptions\FileCreateFailedException::class);
        $storage->CreateFile("myfolder/$p"); // parent does not exist
    }

    public function testImportFile() : void
    {
        $storage = $this->getStorage(); 

        $infile = tempnam(sys_get_temp_dir(), 'a2test');
        assert($infile !== false);
        file_put_contents($infile, $data=Utilities::Random(250));
        $storage->ImportFile($infile, $p="mynewfile", false); // copy, not move
        $this->assertTrue(is_file($infile));
        $this->assertTrue($storage->isFile($p));
        $this->assertSame($data, $storage->ReadBytes($p,0,strlen($data)));

        $infile = tempnam(sys_get_temp_dir(), 'a2test');
        assert($infile !== false);
        file_put_contents($infile, $data=Utilities::Random(300));
        $storage->ImportFile($infile, $p, true); // move, not copy, also overwrite
        $this->assertFalse(is_file($infile));
        $this->assertTrue($storage->isFile($p));
        $this->assertSame($data, $storage->ReadBytes($p,0,strlen($data)));
        $this->assertSame(strlen($data), $storage->ItemStat($p)->size);

        $this->expectException(Exceptions\FileCopyFailedException::class);
        $storage->ImportFile($infile, "myfolder/test123", false); // parent does not exist
    }

    public function testImportFileBadSrc() : void
    {
        $storage = $this->getStorage();
        $this->expectException(Exceptions\FileCopyFailedException::class);
        $storage->ImportFile("does not exist", "test123", false);
    }

    public function testCopyFile() : void
    {
        $storage = $this->getStorage(); 

        $storage->CreateFile($infile="infile");
        $storage->WriteBytes($infile, 0, $data=Utilities::Random(250));
        $storage->CopyFile($infile, $p="mynewfile");
        $this->assertTrue($storage->isFile($infile));
        $this->assertTrue($storage->isFile($p));
        $this->assertSame($data, $storage->ReadBytes($p,0,strlen($data)));

        $this->expectException(Exceptions\FileCopyFailedException::class);
        $storage->CopyFile($infile, "myfolder/test123"); // parent does not exist
    }

    public function testCopyFileBadSrc() : void
    {
        $storage = $this->getStorage();
        $this->expectException(Exceptions\FileCopyFailedException::class);
        $storage->CopyFile("does not exist", "test123");
    }

    public function testReadBytes() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="myfile");
        $this->assertSame("", $storage->ReadBytes($p, 0, 0));

        $storage->WriteBytes($p, 0, $data=Utilities::Random($s=3000));
        $this->assertSame($data, $storage->ReadBytes($p, 0, $s));
        $this->assertSame(substr($data,0,50), $storage->ReadBytes($p, 0, 50));
        $this->assertSame(substr($data,50,50), $storage->ReadBytes($p, 50, 50));
        $this->assertSame(substr($data,$s-50,50), $storage->ReadBytes($p, $s-50, 50));

        $this->expectException(Exceptions\FileReadFailedException::class);
        $storage->ReadBytes($p, $s-50, 60); // past the end
    }

    public function testReadBytesBadOffset() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="myfile");
        $this->expectException(Exceptions\FileReadFailedException::class);
        $storage->ReadBytes($p, 1, 1); // start past the end
    }

    public function testReadBytesBadSrc() : void
    {
        $storage = $this->getStorage();
        $this->expectException(Exceptions\FileOpenFailedException::class);
        $storage->ReadBytes("myfile", 1, 1);
    }

    public function testWriteBytes() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="myfile");
        $this->assertSame(0, $storage->ItemStat($p)->size);

        $storage->WriteBytes($p, 0, $data=Utilities::Random($s=2000));
        $this->assertSame($data, $storage->ReadBytes($p, 0, $s));
        $this->assertSame($s, $storage->ItemStat($p)->size);

        $data2 = Utilities::Random($s2=1000);
        $storage->WriteBytes($p, $s, $data2);
        $data .= $data2; $s += $s2;
        $this->assertSame($data, $storage->ReadBytes($p, 0, $s));
        $this->assertSame($s, $storage->ItemStat($p)->size);

        $data2 = Utilities::Random($s2=200);
        $storage->WriteBytes($p, $s+100, $data2);
        $data .= str_repeat("\0",100).$data2; $s += 100+$s2;
        $this->assertSame($data, $storage->ReadBytes($p, 0, $s));
        $this->assertSame($s, $storage->ItemStat($p)->size);

        // add a rename to add ClosePath into the mix
        $storage->RenameFile($p, $p="myfile333");
        $this->assertSame($s, $storage->ItemStat($p)->size);

        $data2 = Utilities::Random($s2=1000);
        $storage->WriteBytes($p, $s, $data2);
        $data .= $data2; $s += $s2;
        $this->assertSame($data, $storage->ReadBytes($p, 0, $s));
        $this->assertSame($s, $storage->ItemStat($p)->size);
    }

    public function testWriteBytesBadSrc() : void
    {
        $storage = $this->getStorage();
        $this->expectException(Exceptions\FileOpenFailedException::class);
        $storage->WriteBytes("myfile", 1, "mytest");
    }

    public function testTruncate() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="myfile");
        $storage->Truncate($p, 0);
        $this->assertSame(0, $storage->ItemStat($p)->size);

        $storage->Truncate($p, $s=100); // extend file
        $this->assertSame($s, $storage->ItemStat($p)->size);
        $this->assertSame(str_repeat("\0",$s), $storage->ReadBytes($p,0,$s));

        $storage->WriteBytes($p, 0, $data=Utilities::Random($s));
        $storage->Truncate($p, $s=50); // shrink file
        $data = substr($data, 0, $s);
        $this->assertSame($s, $storage->ItemStat($p)->size);
        $this->assertSame($data, $storage->ReadBytes($p,0,$s));

        $storage->Truncate($p, 0);
        $this->assertSame(0, $storage->ItemStat($p)->size);

        $this->expectException(Exceptions\FileWriteFailedException::class);
        $storage->Truncate("not existing", 50); // does not exist
    }

    public function testDeleteFile() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($p="mytest12345");
        $storage->DeleteFile($p);
        $this->assertFalse($storage->isFile($p));
        $storage->DeleteFile($p); // passes if doesn't exist
    }

    public function testDeleteFolder() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p="mytest12345");
        $storage->DeleteFolder($p);
        $this->assertFalse($storage->isFolder($p));
        $storage->DeleteFolder($p); // passes if doesn't exist

        $storage->CreateFolder($p);
        $storage->CreateFile("$p/test.txt");
        $this->expectException(Exceptions\FolderDeleteFailedException::class);
        $storage->DeleteFolder($p); // folder is not empty
    }

    public function testRenameFile() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFile($p="test.docx");
        $storage->WriteBytes($p, 0, $data=Utilities::Random(55));
        $storage->RenameFile($p, $p2="test2.docx");
        $this->assertFalse($storage->isFile($p));
        $this->assertTrue($storage->isFile($p2));
        $this->assertSame($data, $storage->ReadBytes($p2, 0, strlen($data)));

        $storage->CreateFile($p3="overwritedest"); // test overwrite
        $storage->RenameFile($p2, $p3);
        $this->assertFalse($storage->isFile($p2));
        $this->assertTrue($storage->isFile($p3));
        $this->assertSame($data, $storage->ReadBytes($p3, 0, strlen($data)));

        $this->expectException(Exceptions\FileRenameFailedException::class);
        $storage->RenameFile("22222", "none"); // src does not exist
    }

    public function testRenameFolder() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFolder($p="mytest1");
        $storage->CreateFile("$p/test.txt");

        $storage->RenameFolder($p, $p="mytest2");
        $this->assertTrue($storage->isFolder($p));
        $this->assertEqualsCanonicalizing(['test.txt'], $storage->ReadFolder($p));

        $this->expectException(Exceptions\FolderRenameFailedException::class);
        $storage->RenameFolder("222$p", "none"); // src does not exist
    }

    public function testMoveFile() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFile($p="test.docx");
        $storage->CreateFolder($f="testfolder");
        $storage->WriteBytes($p, 0, $data=Utilities::Random(55));
        $storage->MoveFile($p, $p2="$f/$p");
        $this->assertFalse($storage->isFile($p));
        $this->assertTrue($storage->isFile($p2));
        $this->assertSame($data, $storage->ReadBytes($p2, 0, strlen($data)));

        $storage->CreateFile($p); // test overwrite
        $storage->MoveFile($p2, $p);
        $this->assertFalse($storage->isFile($p2));
        $this->assertTrue($storage->isFile($p));
        $this->assertSame($data, $storage->ReadBytes($p, 0, strlen($data)));

        $this->expectException(Exceptions\FileMoveFailedException::class);
        $storage->MoveFile("22222", "test/22222"); // src does not exist
    }

    public function testMoveFolder() : void
    {
        $storage = $this->getStorage();

        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFile("$p2/test.txt");

        $storage->MoveFolder($p2, $p2="$p1/$p2");
        $this->assertEqualsCanonicalizing(['test.txt'], $storage->ReadFolder($p2));

        $this->expectException(Exceptions\FolderMoveFailedException::class);
        $storage->MoveFolder($p2, "$p2/$p1"); // src does not exist
    }

    public function testRenameFolderOverwrite() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->RenameFolder($p1, $p2);
        $this->assertFalse($storage->isFolder($p1));
        $this->assertTrue($storage->isFolder($p2));
        $this->assertSame([], $storage->ReadFolder($p2));

        $storage->CreateFolder($p3="mytest3");
        $storage->CreateFile("$p2/file");
        $this->expectException(Exceptions\FolderRenameFailedException::class);
        $storage->RenameFolder($p3, $p2); // not empty
    }

    public function testMoveFolderOverwrite() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFolder($p3="$p2/mytest1");
        $storage->MoveFolder($p1, $p3);
        $this->assertFalse($storage->isFolder($p1));
        $this->assertTrue($storage->isFolder($p3));
        $this->assertSame([], $storage->ReadFolder($p3));

        $storage->CreateFolder($p1="mytest1");
        $this->expectException(Exceptions\FolderRenameFailedException::class);
        $storage->RenameFolder($p1, $p2); // not empty
    }

    public function testRenameFileValid() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFile("$p1/my");
        
        $this->expectException(Exceptions\FileRenameFailedException::class);
        $storage->RenameFile("$p1/my","$p2/my"); // dirname doesn't match
    }
    
    public function testRenameFolderValid() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFolder("$p1/my");
        
        $this->expectException(Exceptions\FolderRenameFailedException::class);
        $storage->RenameFolder("$p1/my","$p2/my"); // dirname doesn't match
    }
    
    public function testMoveFileValid() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFile("$p1/my");
        
        $this->expectException(Exceptions\FileMoveFailedException::class);
        $storage->MoveFile("$p1/my","$p2/mynew"); // basename doesn't match
    }
    
    public function testMoveFolderValid() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFolder($p1="mytest1");
        $storage->CreateFolder($p2="mytest2");
        $storage->CreateFolder("$p1/my");
        
        $this->expectException(Exceptions\FolderMoveFailedException::class);
        $storage->MoveFolder("$p1/my","$p2/mynew"); // basename doesn't match
    }

    public function testClosePath() : void
    {
        $storage = $this->getStorage();
        $storage->CreateFile($f1='testfile1');
        $storage->WriteBytes($f1,0,$d1=Utilities::Random(50));
        $storage->CreateFile($f2='testfile2');
        $storage->WriteBytes($f2,0,$d2=Utilities::Random(40));

        $this->assertSame($d1, $storage->ReadBytes($f1,0,strlen($d1))); // stream is open
        $storage->RenameFile($f1, $f2); // closes stream
        $this->assertSame($d1, $storage->ReadBytes($f2,0,strlen($d1)));
    }
}
