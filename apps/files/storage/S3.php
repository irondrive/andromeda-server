<?php namespace Andromeda\Apps\Files\Storage; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Config.php"); use Andromeda\Core\Config;

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/FieldCrypt.php"); use Andromeda\Apps\Accounts\FieldCrypt;

require_once(ROOT."/apps/files/filesystem/FSManager.php"); use Andromeda\Apps\Files\Filesystem\FSManager;
require_once(ROOT."/apps/files/storage/Exceptions.php");
require_once(ROOT."/apps/files/storage/FWrapper.php");
require_once(ROOT."/apps/files/storage/Traits.php");

/** Exception indicating that the libsmbclient extension is missing */
class S3AwsSdkException extends ActivateException { public $message = "S3_AWS_SDK_MISSING"; }

/** Exception that wraps S3 SDK exceptions */
class S3ErrorException extends StorageException { public $message = "S3_SDK_EXCEPTION"; }

/** Exception indicating that writes cannot seek (append only) */
class S3WriteSeekException extends StorageException { public $message = "S3_CANNOT_SEEK_UPLOAD"; }

/** Exception indicating that objects cannot be modified */
class S3ModifyException extends Exceptions\ClientErrorException { public $message = "S3_OBJECTS_IMMUTABLE"; }

Account::RegisterCryptoHandler(function(ObjectDatabase $database, Account $account, bool $init){ if (!$init) S3::DecryptAccount($database, $account); });

FSManager::RegisterStorageType(S3::class);

abstract class S3Base1 extends FWrapper { use NoFolders; }
abstract class S3Base2 extends S3Base1 { use FieldCrypt; }
abstract class S3Base3 extends S3Base2 { use OptFieldCrypt; }

/**
 * Allows using an S3-compatible server for backend storage
 * 
 * Uses fieldcrypt to allow encrypting the keys.
 */
class S3 extends S3Base3
{
    protected static function getEncryptedFields() : array { return array('accesskey','secretkey'); }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'endpoint' => null,
            'path_style' => null,
            'port' => null,
            'usetls' => null,
            'region' => null,
            'bucket' => null,
            'accesskey' => null,
            'secretkey' => null
        ));
    }
    
    /**
     * Returns a printable client object of this S3 storage
     * @return array `{endpoint:string, path_style:?bool, port:?int, usetls:bool, \
           region:string, bucket:string, accesskey:?string, secretkey:bool}`
     * @see FWrapper::GetClientObject()
     */
    public function GetClientObject() : array
    {
        return array_merge(parent::GetClientObject(), array(
            'endpoint' => $this->GetScalar('endpoint'),
            'path_style' => $this->TryGetScalar('path_style'),
            'port' => $this->TryGetScalar('port'),
            'usetls' => $this->getUseTLS(),
            'region' => $this->GetScalar('region'),
            'bucket' => $this->GetScalar('bucket'),
            'accesskey' => $this->TryGetAccessKey(),
            'secretkey' => boolval($this->TryGetScalar('secretkey'))
        ));
    }
    
    /** Returns the S3 bucket identifier */
    protected function GetBucket() : string { return $this->GetScalar('bucket'); }
    
    /** Returns the S3 access key (or null) */
    protected function TryGetAccessKey() : ?string { return $this->TryGetEncryptedScalar('accesskey'); }
    
    /** Returns the S3 secret key (or null) */
    protected function TryGetSecretKey() : ?string { return $this->TryGetEncryptedScalar('secretkey'); }
    
    /** Sets the S3 access key to the given value */
    protected function SetAccessKey(?string $key) : self { return $this->SetEncryptedScalar('accesskey',$key); }
    
    /** Sets the S3 secret key to the given value */
    protected function SetSecretKey(?string $key) : self { return $this->SetEncryptedScalar('secretkey',$key); }
    
    /** Returns true if the connection should use TLS */
    protected function getUseTLS() : bool { return boolval($this->TryGetScalar('usetls')) ?? true; }
    
    /** Returns the given path with no leading, trailing or duplicate / */
    protected static function cleanPath(string $path) : string { return implode('/',array_filter(explode('/',$path))); }
    
    public static function GetCreateUsage() : string { return parent::GetCreateUsage()." --endpoint fspath --bucket alphanum --region alphanum [--path_style bool]".
                                                                                       "[--port int] [--usetls bool] [--accesskey randstr] [--secretkey randstr]"; }
    
    public static function Create(ObjectDatabase $database, Input $input, FSManager $filesystem) : self
    {
        return parent::Create($database, $input, $filesystem)
            ->SetScalar('endpoint', self::cleanPath($input->GetParam('endpoint', SafeParam::TYPE_FSPATH)))
            ->SetScalar('path_style', $input->GetOptParam('path_style',SafeParam::TYPE_BOOL))
            ->SetScalar('port', $input->GetOptParam('port', SafeParam::TYPE_UINT))
            ->SetScalar('usetls', $input->GetOptParam('usetls', SafeParam::TYPE_BOOL))
            ->SetScalar('region', $input->GetParam('region', SafeParam::TYPE_ALPHANUM))
            ->SetScalar('bucket', $input->GetParam('bucket', SafeParam::TYPE_ALPHANUM))
            ->SetAccessKey($input->GetOptParam('accesskey', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER))
            ->SetSecretKey($input->GetOptParam('secretkey', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER));
    }
    
    public static function GetEditUsage() : string { return parent::GetEditUsage()." [--endpoint fspath] [--bucket alphanum] [--region alphanum] [--path_style ?bool]".
                                                                                   "[--port ?int] [--usetls ?bool] [--accesskey ?randstr] [--secretkey ?randstr]"; }
    
    public function Edit(Input $input) : self
    {
        if ($input->HasParam('endpoint')) $this->SetScalar('endpoint', self::cleanPath($input->GetParam('endpoint', SafeParam::TYPE_FSPATH)));
        
        if ($input->HasParam('region')) $this->SetScalar('region', $input->GetParam('region', SafeParam::TYPE_ALPHANUM));
        if ($input->HasParam('bucket')) $this->SetScalar('bucket', $input->GetParam('bucket', SafeParam::TYPE_ALPHANUM));
        
        if ($input->HasParam('port')) $this->SetScalar('port', $input->GetNullParam('port', SafeParam::TYPE_UINT));
        if ($input->HasParam('usetls')) $this->SetScalar('usetls', $input->GetNullParam('usetls', SafeParam::TYPE_BOOL));
        if ($input->HasParam('path_style')) $this->SetScalar('path_style', $input->GetNullParam('path_style', SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('accesskey')) $this->SetScalar('accesskey', $input->GetNullParam('accesskey', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER));
        if ($input->HasParam('secretkey')) $this->SetScalar('secretkey', $input->GetNullParam('secretkey', SafeParam::TYPE_RANDSTR, SafeParams::PARAMLOG_NEVER));
        
        return parent::Edit($input);
    }
    
    /** s3 connection resource */ private $s3;
    
    /** The stream wrapper ID */ private string $streamID;
    
    /** Checks for the SMB client extension */
    public function SubConstruct() : void
    {
        if (!class_exists('\\Aws\\S3\\S3Client')) throw new S3AwsSdkException();
    }
    
    public function Activate() : self
    {
        if (isset($this->s3)) return $this;
        
        $params = array(
            'version' => '2006-03-01', 
            'region' => $this->GetScalar('region')    
        );
        
        $endpoint = $this->GetScalar('endpoint');        
        
        if (($port = $this->TryGetScalar('port')) !== null) 
        {
            $endpoint = explode('/', $endpoint);
            $endpoint[0] .= ":$port";
            $endpoint = implode('/', $endpoint);
        }
        
        $params['endpoint'] = $endpoint;
        
        $params['scheme'] = $this->getUseTLS() ? 'https' : 'http';
        
        if (($pathstyle = $this->TryGetScalar('path_style')) !== null)
            $params['use_path_style_endpoint'] = boolval($pathstyle);
        
        $accesskey = $this->TryGetAccessKey();
        $secretkey = $this->TryGetSecretKey();
        
        if ($accesskey !== null || $secretkey !== null)
        {
            $params['credentials'] = array();
            if ($accesskey !== null) $params['credentials']['key'] = $accesskey;
            if ($secretkey !== null) $params['credentials']['secret'] = $secretkey;
        }

        $api = Main::GetInstance(); $debug = $api->GetDebugLevel();
        
        if ($debug >= Config::ERRLOG_SENSITIVE)
            $params['debug'] = array('logfn'=>function(string $str){
                ErrorManager::GetInstance()->LogDebug("S3 SDK: $str"); });
                
        $this->s3 = new \Aws\S3\S3Client($params);
        
        $this->streamID = str_replace('_','-',$this->ID());        
        \Aws\S3\StreamWrapper::Register($this->s3, $this->streamID);
        
        return $this;
    }
    
    protected function GetFullURL(string $path = "") : string
    {
        return $this->streamID."://".$this->GetBucket().'/'.$path;
    }
    
    /**
     * Runs an S3 client function
     * @param string $func name of S3Client function
     * @param array $params params to pass
     * @throws S3ErrorException if the SDK throws any S3Exception
     * @return \Aws\ResultInterface S3 result
     */
    protected function tryS3(string $func, array $params) : \Aws\ResultInterface
    {
        try { return $this->s3->$func($params); }
        catch (\Aws\S3\Exception\S3Exception $e) {
            throw S3ErrorException::Copy($e); }
    }
    
    /**
     * Returns an array of the params used for all requests (bucket and key)
     * @param string $path key/path of object
     * @return array `{Bucket:string,Key:string}`
     */
    protected function getStdParams(string $path) : array
    {
        return array('Bucket' => $this->GetBucket(), 'Key' => $path);
    }
    
    protected function assertReadable() : void
    {
        if ($this->ReadFolder('') === null)
            throw new TestReadFailedException();
    }
    
    protected function assertWriteable() : void { $this->TestWriteable(); }

    public function ItemStat(string $path) : ItemStat
    {
        if ($this->isFolder($path)) return new ItemStat();
        
        else return parent::ItemStat($path);
    }
    
    protected function SubReadFolder(string $path = "") : array
    {
        if (!$this->isFolder($path))
            throw new FoldersUnsupportedException();
            
        else return parent::SubReadFolder('');
    } 
    
    protected function SubReadBytes(string $path, int $start, int $length) : string
    {
        // we completely ignore the FWrapper's read system because
        // the amazon SDK fread() does not allow us to seek ahead
        
        $this->ClosePath($path);
        
        $params = $this->getStdParams($path);
        
        $params['Range'] = "bytes=$start-".($start+$length-1);
        
        $result = $this->tryS3('getObject', $params);
        
        $data = (string)$result->get('Body');
        
        if (strlen($data) !== $length)
        {
            ErrorManager::GetInstance()->LogDebug(array(
                'read'=>strlen($data), 'wanted'=>$length));
            
            throw new FileReadFailedException();
        }
        
        return $data;
    }
    
    protected static function supportsReadWrite() : bool { return false; }
    protected static function supportsSeekReuse() : bool { return false; }
    
    protected function OpenReadHandle(string $path){ throw new FileOpenFailedException(); }
    protected function OpenWriteHandle(string $path){  throw new FileOpenFailedException(); }
    
    protected function OpenContext(string $path, int $offset, bool $isWrite) : FileContext
    {
        if (!$isWrite) throw new FileReadFailedException();
        
        if ($offset) throw new S3ModifyException();
        
        $handle = fopen($this->GetFullURL($path),'w');
        
        return new FileContext($handle, 0, true);
    }
    
    protected function SubCreateFile(string $path) : self
    {
        $this->GetContext($path, 0, true); return $this;
    }
    
    protected function SubTruncate(string $path, int $length) : self { throw new S3ModifyException(); }    
}
