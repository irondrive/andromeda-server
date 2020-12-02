<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;

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

    public function GetChunkIndex(int $byte) : int { return intdiv($byte, $this->chunksize); }
    public function GetNumChunks(int $bytes) : int { return $bytes ? intdiv($bytes-1, $this->chunksize)+1 : 0; }
    
    public function ImportFile(File $file, string $oldpath) : self
    {
        $newpath = parent::GetFilePath($file);
        
        $length = filesize($oldpath);
        $chunks = $this->GetNumChunks($length);
        
        $this->GetStorage()->CreateFile($newpath);

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
    
    public function ReadBytes(File $file, int $start, int $length) : string
    {
        $toend = $start + $length === $file->GetSize();
        if ($start % $this->chunksize || ($length % $this->chunksize && !$toend))
            throw new UnalignedAccessException();
        
        $length = min($start+$length, $file->GetSize())-$start;
        
        $chunk0 = $this->GetChunkIndex($start);
        $chunks = $this->GetNumChunks($length);        
        $fchunks = $this->GetNumChunks($file->GetSize());
        $chunks = min($chunks, $fchunks-$chunk0);

        $data = ""; for ($chunk = $chunk0; $chunk < $chunk0+$chunks; $chunk++)
            $data .= $this->ReadChunk($file, $chunk);
        return $data;
    }
    
    public function WriteBytes(File $file, int $start, string $data) : self
    {
        $length = strlen($data); $toend = $start + $length === $file->GetSize();
        if ($start % $this->chunksize || ($length % $this->chunksize && !$toend))
            throw new UnalignedAccessException();
        
        $length = min($start+$length, $file->GetSize())-$start;
        
        $chunk0 = $this->GetChunkIndex($start);
        $chunks = $this->GetNumChunks($length);
        $fchunks = $this->GetNumChunks($file->GetSize());
        $chunks = min($chunks, $fchunks-$chunk0);
        
        for ($chunk = 0; $chunk < $chunks; $chunk++)
        {
            $subdata = substr($data, $chunk*$this->chunksize, $this->chunksize);
            $this->WriteChunk($file, $chunk+$chunk0, $subdata);
        }
        return $this;
    }
    
    public function Truncate(File $file, int $length, bool $init = true) : self
    {
        $length = max($length, 0);
        
        $noncesize = CryptoSecret::NonceLength();
        $extrasize = $noncesize + CryptoSecret::OutputOverhead();
        
        $chunks = $this->GetNumChunks($length);
        $rlength = $chunks * $extrasize + $length;
        $length0 = $file->GetSize();

        if ($init && $length != $length0)
        {            
            $chunks0 = $this->GetNumChunks($length0);
            $remain = ($length-1) % $this->chunksize + 1;    
            if ($chunks <= $chunks0)
            {     
                if ($remain) $data = $this->ReadChunk($file, $chunks-1);    
                parent::Truncate($file, $rlength);
                
                if ($remain) // rewrite new last chunk
                {
                    $data = str_pad(substr($data, 0, $remain), $remain, "\0");
                    $this->WriteChunk($file, $chunks-1, $data);  
                }                
            }
            else // write new chunks
            {
                if ($chunks0) $data0 = $this->ReadChunk($file, $chunks0-1);
                parent::Truncate($file, $rlength);
                
                if ($chunks0) // rewrite old last chunk
                {
                    $data0 = str_pad($data0, $this->chunksize, "\0");
                    $this->WriteChunk($file, $chunks0-1, $data0);
                }
                
                if ($chunks > $chunks0) // fill new blank chunks
                {
                    $datan = str_pad("", $this->chunksize, "\0");
                    for ($chunk = $chunks0; $chunk < $chunks; $chunk++)
                    {
                        if ($chunk == $chunks-1) // trim last chunk
                            $datan = substr($datan, 0, $remain);
                        $this->WriteChunk($file, $chunk, $datan);
                    }
                }
            }
        }
        else parent::Truncate($file, $rlength);
        
        return $this;
    }
    
    protected function ReadChunk(File $file, int $index) : string
    {       
        $noncesize = CryptoSecret::NonceLength();
        $datasize = $this->chunksize + CryptoSecret::OutputOverhead();
        
        $nonceoffset = $index * ($noncesize + $datasize);
        $dataoffset = $nonceoffset + $noncesize;

        $nonce = parent::ReadBytes($file, $nonceoffset, $noncesize);
        $data = parent::ReadBytes($file, $dataoffset, $datasize);
        $auth = $this->GetAuthString($file, $index);
        
        return CryptoSecret::Decrypt($data, $nonce, $this->masterkey, $auth);
    }

    protected function WriteChunk(File $file, int $index, string $data) : self
    {
        $noncesize = CryptoSecret::NonceLength();
        $datasize = $this->chunksize + CryptoSecret::OutputOverhead();
        $blocksize = $datasize + $noncesize;
        
        $nonceoffset = $index * $blocksize;
        $dataoffset = $nonceoffset + $noncesize;

        $nonce = CryptoSecret::GenerateNonce();
        $auth = $this->GetAuthString($file, $index);
        
        $data = CryptoSecret::Encrypt($data, $nonce, $this->masterkey, $auth);

        parent::WriteBytes($file, $nonceoffset, $nonce);
        parent::WriteBytes($file, $dataoffset, $data);
        
        return $this;
    }
}
