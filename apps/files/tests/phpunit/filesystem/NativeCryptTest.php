<?php namespace Andromeda\Apps\Files\Filesystem; 

if (!defined('a2test')) define('a2test',true); require_once("a2init.php");

require_once(ROOT."/apps/files/filesystem/FSManager.php");

require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\Storage;

require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;

class NativeCryptTest extends \PHPUnit\Framework\TestCase
{
    private array $files = array();
    private array $paths = array();
    private array $sizes = array();
    
    private Storage $storage;
    private NativeCrypt $fsimpl;
    private FSManager $filesystem;
        
    protected function getTmpFile() : string
    {
        $file = tempnam(sys_get_temp_dir(), 'a2test');
        
        $this->assertNotFalse($file);
        
        $this->files[] = $file; return $file;
    }
    
    public function setUp() : void
    {        
        $storage = $this->createMock(Storage::class);
        
        $storage->method('CreateFile')->will($this->returnCallback(function(string $path)use($storage)
        {
            $file = $this->getTmpFile();
            
            $this->paths[$path] = fopen($file,'rb+');
            
            return $storage;
        }));
        
        $storage->method('ReadBytes')->will($this->returnCallback(function(string $path, int $start, int $length)
        {
            $this->assertArrayHasKey($path, $this->paths);
            
            $handle = $this->paths[$path];
            
            fseek($handle, $start); 
            
            return fread($handle, $length);
        }));
        
        $storage->method('WriteBytes')->will($this->returnCallback(function(string $path, int $start, string $data)use($storage)
        {
            $this->assertArrayHasKey($path, $this->paths);
            
            $handle = $this->paths[$path];
            
            fseek($handle, $start);
            
            fwrite($handle, $data);
            
            return $storage;
        }));
        
        $storage->method('Truncate')->will($this->returnCallback(function(string $path, int $length)use($storage)
        {
            $this->assertArrayHasKey($path, $this->paths);
            
            $handle = $this->paths[$path];
            
            ftruncate($handle, $length);
            
            return $storage;
        }));
        
        $this->storage = $storage;
        
        $this->filesystem = $this->createMock(FSManager::class);
        $this->filesystem->method('GetStorage')->willReturn($this->storage);
        
        $this->fsimpl = new NativeCrypt($this->filesystem, CryptoSecret::GenerateKey(), self::CHUNK_SIZE);
    }

    public function tearDown() : void
    {
        foreach ($this->paths as $handle) fclose($handle);
        
        foreach ($this->files as $file) unlink($file);
    }
    
    protected function createFile(string $data) : File
    {
        $file = $this->createMock(File::class);
        
        $id = Utilities::Random(File::IDLength);
        $file->method('ID')->willReturn($id);
        
        $path = self::getTmpFile(); file_put_contents($path, $data);
        
        $file->method('SetSize')->will($this->returnCallback(function($size)use($file){
            $this->sizes[$file->ID()] = $size; return $file;
        }));
        
        $file->method('GetSize')->will($this->returnCallback(function()use($file){
            return $this->sizes[$file->ID()];
        }));
        
        $this->fsimpl->ImportFile($file->SetSize(strlen($data)), $path);
        
        return $file;
    }
    
    // truncate makes thorough use of import/read/write!
    protected function tryTruncate(int $size0, int $size) : void
    {
        // first import a mock file and check its contents
        
        $data = Utilities::Random($size0);
        
        $file = $this->createFile($data);
        
        $ret = $this->fsimpl->ReadBytes($file, 0, $size0);
        
        $this->assertSame($data, $ret); 
        
        // next truncate the file and check its new contents
        
        if ($size < $size0) $data = substr($data, 0, $size);
        else if ($size > $size0) $data = str_pad($data, $size, "\0");
        
        $this->assertSame($size, strlen($data));
        
        $this->fsimpl->Truncate($file, $size); $file->SetSize($size);
        
        $ret = $this->fsimpl->ReadBytes($file, 0, $size);
        
        $this->assertSame($data, $ret);
    }
    
    protected function tryTruncatePair(int $size0, int $size) : void
    {
        $this->tryTruncate($size0, $size);
        $this->tryTruncate($size, $size0);
    }   
    
    const CHUNK_SIZE = 10; // bytes
    
    public function testTruncates() : void
    {
        $this->tryTruncate(0, 0);
        
        $this->tryTruncatePair(0, 5);
        $this->tryTruncatePair(0, 55);
        
        // extend/shrink within a chunk
        $this->tryTruncatePair(3, 7);
        $this->tryTruncatePair(43, 46);
        $this->tryTruncatePair(11, 18);
        $this->tryTruncatePair(14, 20);
        $this->tryTruncatePair(11, 20);
        
        // extend/shrink one chunk
        $this->tryTruncatePair(0, 10);
        $this->tryTruncatePair(3, 10);
        $this->tryTruncatePair(9, 10);
        $this->tryTruncatePair(10, 25);
        $this->tryTruncatePair(14, 25);
        $this->tryTruncatePair(19, 25);
        $this->tryTruncatePair(20, 39);
        $this->tryTruncatePair(27, 39);
        $this->tryTruncatePair(29, 39);
        
        // extend/shrink multiple chunks
        $this->tryTruncatePair(0, 50);
        $this->tryTruncatePair(3, 50);
        $this->tryTruncatePair(9, 50);
        $this->tryTruncatePair(10, 77);
        $this->tryTruncatePair(14, 77);
        $this->tryTruncatePair(19, 77);
        $this->tryTruncatePair(20, 99);
        $this->tryTruncatePair(27, 99);
        $this->tryTruncatePair(29, 99);
    }
}
