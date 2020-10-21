<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoAEAD;

class UnalignedAccessException extends Exceptions\ServerException { public $message = "FS_ACCESS_NOT_ALIGNED"; }

class NativeCrypt extends Native
{
    public function __construct(FSManager $filesystem, string $masterkey, int $chunksize)
    {
        $this->masterkey = $masterkey;
        $this->chunksize = $chunksize;
        parent::__construct($filesystem);
    }
    
    public function GetChunkSize() : ?int { return $this->chunksize; }
    
    private function GetAuthString(File $file, int $index) { return $file->ID().":$index"; }
    
    public function ImportFile(File $file, string $oldpath) : self
    {
        $newpath = parent::GetFilePath($file);
        
        $length = filesize($oldpath);
        $chunks = intdiv($length, $this->chunksize) + 1;
        
        $this->GetStorage()->CreateFile($newpath);
        $this->Truncate($file, $length, false);
        
        $handle = fopen($oldpath,'rb');
        
        for ($chunk = 0; $chunk < $chunks; $chunk++)
        {
            $offset = $chunk * $this->chunksize;
            fseek($handle, $offset);
            $data = fread($handle, $this->chunksize);
            $this->WriteChunk($file, $chunk, $data);
        }
        
        fclose($handle);
        return $this;
    }
    
    public function ReadChunk(File $file, int $index) : string
    {
        $noncesize = CryptoAEAD::NonceLength();
        $datasize = $this->chunksize + CryptoAEAD::OutputOverhead();
        
        $nonceoffset = $index * ($noncesize + $datasize);
        $dataoffset = $nonceoffset + $noncesize;

        $nonce = parent::ReadBytes($file, $nonceoffset, $noncesize);
        $data = parent::ReadBytes($file, $dataoffset, $datasize);
        $auth = $this->GetAuthString($file, $index);
        
        return CryptoAEAD::Decrypt($data, $nonce, $this->masterkey, $auth);
    }

    public function ReadBytes(File $file, int $start, int $length) : string
    {
        if ($start % $this->chunksize || $length % $this->chunksize)
            throw new UnalignedAccessException();        
        return $this->ReadChunk($file, $start / $this->chunksize);
    }
    
    public function WriteChunk(File $file, int $index, string $data) : self
    {
        $noncesize = CryptoAEAD::NonceLength();
        $datasize = $this->chunksize + CryptoAEAD::OutputOverhead();
        $blocksize = $datasize + $noncesize;
        
        $nonceoffset = $index * $blocksize;
        $dataoffset = $nonceoffset + $noncesize;

        $nonce = CryptoAEAD::GenerateNonce();
        $auth = $this->GetAuthString($file, $index);
        
        $data = CryptoAEAD::Encrypt($data, $nonce, $this->masterkey, $auth);

        parent::WriteBytes($file, $nonceoffset, $nonce);
        parent::WriteBytes($file, $dataoffset, $data);
        
        return $this;
    }
    
    public function WriteBytes(File $file, int $start, string $data) : self
    {
        if ($start % $this->chunksize || strlen($data) % $this->chunksize)
            throw new UnalignedAccessException();
        $this->WriteChunk($file, $start / $this->chunksize); return $this;
    }
    
    public function Truncate(File $file, int $length, bool $init = true) : self
    {
        $noncesize = CryptoAEAD::NonceLength();
        $extrasize = $noncesize + CryptoAEAD::OutputOverhead();

        $chunks = intdiv($length, $this->chunksize) + 1;
        $rlength = $chunks * $extrasize + $length;

        parent::Truncate($file, $rlength);

        if ($init)
        {
            $data = str_pad("", $this->chunksize, "\0");
            for ($chunk = 0; $chunk < $chunks-1; $chunk)
               $this->WriteChunk($file, $chunk, $data);
            
            $remain = $length % $this->chunksize;
            $data = substr($data, 0, $remain);
            $this->WriteChunk($file, $chunks-1, $data);
        }
        
        return $this;
    }
}
