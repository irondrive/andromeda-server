<?php namespace Andromeda\Apps\Files\Filesystem; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/files/filesystem/Native.php");
require_once(ROOT."/apps/files/File.php"); use Andromeda\Apps\Files\File;
require_once(ROOT."/core/Crypto.php"); use Andromeda\Core\CryptoSecret;

require_once(ROOT."/apps/files/storage/Storage.php"); use Andromeda\Apps\Files\Storage\FileReadFailedException;

/**
 * Implements an encryption layer on top of the native filesystem.
 * 
 * Files are divided into chunks, the size of which can be configured.
 * Import and Truncate are not as fast since new data must actually be written.
 * 
 * The crypto key for the filesystem is stored plainly in the database,
 * so this is only useful for off-server untrusted external storages.
 * Client-side crypto should be used for any case where the API is not trusted.
 */
class NativeCrypt extends Native
{
    public function __construct(FSManager $filesystem, string $masterkey, int $chunksize)
    {
        $this->masterkey = $masterkey;
        $this->chunksize = $chunksize;
        parent::__construct($filesystem);
    }
    
    public function GetChunkSize() : ?int { return $this->chunksize; }
    
    /** Chunk swapping is prevented by signing each with the file ID and chunk index */
    private function GetAuthString(File $file, int $index) { return $file->ID().":$index"; }

    /** Returns the chunk index storing the given byte offset */
    protected function GetChunkIndex(int $byte) : int { return intdiv($byte, $this->chunksize); }
    
    /** Returns the number of chunks required to store the given number of bytes */
    protected function GetNumChunks(int $bytes) : int { return $bytes ? intdiv($bytes-1, $this->chunksize)+1 : 0; }
    
    public function ImportFile(File $file, string $oldpath) : self
    {
        $newpath = parent::GetFilePath($file);
        
        $length = filesize($oldpath);
        $chunks = $this->GetNumChunks($length);
        
        $this->GetStorage()->CreateFile($newpath);

        if (!($handle = fopen($oldpath,'rb')))
            throw new FileReadFailedException();
        
        for ($chunk = 0; $chunk < $chunks; $chunk++)
        {
            $offset = $chunk * $this->chunksize;
            
            $rbytes = min($this->chunksize, $length-$offset);
            
            $data = fread($handle, $rbytes);
            
            if ($data === false || strlen($data) !== $rbytes)
                throw new FileReadFailedException();
            
            $this->WriteChunk($file, $chunk, $data);
        }
        
        fclose($handle);
        
        return $this;
    }
    
    public function ReadBytes(File $file, int $start, int $length) : string
    {
        $chunk0 = $this->GetChunkIndex($start);
        $chunkn = $this->GetChunkIndex($start+$length-1);

        $output = array(); for ($chunk = $chunk0; $chunk <= $chunkn; $chunk++)
        {            
            $data = $this->ReadChunk($file, $chunk);            
            $dstart = $this->chunksize * $chunk;
            
            // maybe need to trim off the start of the chunk
            if ($start > $dstart) { $data = substr($data, $start-$dstart); $dstart = $start; }
                
            // maybe need to trim off the end of the chunk
            $end = $start + $length; $dend = $dstart + strlen($data);
            if ($end < $dend) { $data = substr($data, 0, $end - $dend); }
            
            $output[] = $data;
        }
        
        $retval = implode($output);
        
        if (strlen($retval) !== $length)
            throw new FileReadFailedException();
        
        return $retval;
    }
    
    public function WriteBytes(File $file, int $start, string $data) : self
    {
        if ($start > $file->GetSize()) $file->SetSize($start);
        
        $chunk0 = $this->GetChunkIndex($start);
        $chunkn = $this->GetChunkIndex($start+strlen($data)-1);
        
        for ($chunk = $chunk0; $chunk <= $chunkn; $chunk++)
        {            
            $cstart = $this->chunksize * $chunk; $cdata = null;
            
            // maybe need to trim down the input data
            $wstart = max($start, $cstart); 
            $wlen = $this->chunksize - $wstart + $cstart;
            $wdata = substr($data, $wstart - $start, $wlen);
            
            // maybe need to add old data to the beginning
            if ($cstart < $wstart)
            {
                $cdata = $this->ReadChunk($file, $chunk);
                $wdata = substr($cdata, 0, $wstart-$cstart).$wdata; $wstart = $cstart;
            }
            
            // maybe need to add old data to the end
            $wend = $wstart + strlen($wdata);            
            $cend = min($cstart + $this->chunksize, $file->GetSize());
            
            if ($wend < $cend)
            {
                $cdata ??= $this->ReadChunk($file, $chunk);
                $wdata .= substr($cdata, $wend-$cend);
            }
                        
            $this->WriteChunk($file, $chunk, $wdata);
        }
        return $this;
    }
    
    public function Truncate(File $file, int $length) : self
    {
        $length = max($length, 0);
        
        if ($length === $file->GetSize()) return $this;
        
        $chunks = $this->GetNumChunks($length);
        $chunks0 = $this->GetNumChunks($file->GetSize());
        
        $cfix = min($chunks, $chunks0) - 1;
        
        if ($cfix >= 0) // may need to rewrite a chunk
        {
            $coffset = $cfix * $this->chunksize;
            $cwant = min($this->chunksize, $length-$coffset);
            
            $cdata = $this->ReadChunk($file, $cfix);        
            $dofix = ($cwant !== strlen($cdata));
            
            if ($cwant > strlen($cdata)) // extend the chunk
                $cdata = str_pad($cdata, $cwant, "\0");
            if ($cwant < strlen($cdata)) // trim the chunk
                $cdata = substr($cdata, 0, $cwant);
            
            if ($dofix) $this->WriteChunk($file, $cfix, $cdata);
        }
        
        // may need to extend the file with new chunks
        for ($chunk = $chunks0; $chunk < $chunks; $chunk++)
        {
            $coffset = $chunk * $this->chunksize;
            $csize = min($this->chunksize, $length-$coffset);
            
            $cdata = str_pad("", $csize, "\0");            
            $this->WriteChunk($file, $chunk, $cdata);
        }
        
        $overhead = CryptoSecret::NonceLength() + CryptoSecret::OutputOverhead();
        $fsize = $overhead * ($this->GetNumChunks($length)) + $length;
        
        parent::Truncate($file, $fsize); return $this;
    }
    
    /**
     * Reads and decrypts the given chunk from the file
     * @param File $file file to read from
     * @param int $index chunk number
     * @return string decrypted chunk
     */
    protected function ReadChunk(File $file, int $index) : string
    {
        $noncesize = CryptoSecret::NonceLength();
        $overhead = $noncesize + CryptoSecret::OutputOverhead();
        
        $blocksize = $this->chunksize + $overhead;
        $datasize = $blocksize - $noncesize;
        
        $nonceoffset = $index * $blocksize;
        $dataoffset = $nonceoffset + $noncesize;

        // make sure we don't read beyond the end of the file
        $foverhead = $overhead * ($this->GetNumChunks($file->GetSize()));
        $datasize = min($datasize, $file->GetSize() + $foverhead - $dataoffset);

        // a chunk is stored as [nonce,data]
        $nonce = parent::ReadBytes($file, $nonceoffset, $noncesize);
        $data = parent::ReadBytes($file, $dataoffset, $datasize);
        
        if (strlen($nonce) != $noncesize || strlen($data) != $datasize) 
            throw new FileReadFailedException();
        
        $auth = $this->GetAuthString($file, $index);
        
        return CryptoSecret::Decrypt($data, $nonce, $this->masterkey, $auth);
    }

    /**
     * Encrypts and writes the given data to the given chunk
     * @param File $file file to write to
     * @param int $index chunk index to write
     * @param string $data plaintext data to write
     * @return $this
     */
    protected function WriteChunk(File $file, int $index, string $data) : self
    {   
        $noncesize = CryptoSecret::NonceLength();
        
        $blocksize = $noncesize + $this->chunksize + CryptoSecret::OutputOverhead();
        
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
