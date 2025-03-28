<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\{BaseConfig, Utilities, VersionInfo};
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, TableTypes};
 use Andromeda\Core\IOFormat\SafeParams;

/** 
 * App config stored in the database 
 * @phpstan-type ConfigJ array{upload_maxbytes:?int, upload_maxfiles:?int}
 * @phpstan-type AdminConfigJ \Union<ConfigJ, array{apiurl:?string, date_created:float, rwchunksize:int, crchunksize:int, upload_maxbytes_admin:?int, php_post_max_size:?int, php_upload_max_filesize:?int, periodicstats:bool}>
 */
class Config extends BaseConfig
{
    public static function getAppname() : string { return 'files'; }
    
    public static function getVersion() : string {
        return VersionInfo::toCompatVer(andromeda_version); }
    
    use TableTypes\TableNoChildren;

    /** The URL of the client to use in links */
    private FieldTypes\NullStringType $apiurl;
    /** The file read/write chunk size */
    private FieldTypes\IntType $rwchunksize;
    /** The default crypto filesystem chunk size */
    private FieldTypes\IntType $crchunksize;
    /** The maximum allowed upload size (not enforceable) */
    private FieldTypes\NullIntType $upload_maxsize;
    /** True if timed stats as a whole is enabled */
    private FieldTypes\BoolType $periodicstats;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->apiurl = $fields[] =         new FieldTypes\NullStringType('apiurl');
        $this->rwchunksize = $fields[] =    new FieldTypes\IntType('rwchunksize', default:16*1024*1024); // 16M
        $this->crchunksize = $fields[] =    new FieldTypes\IntType('crchunksize', default:128*1024); // 128K
        $this->upload_maxsize = $fields[] = new FieldTypes\NullIntType('upload_maxsize');
        $this->periodicstats = $fields[] =  new FieldTypes\BoolType('periodicstats', default:false);
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Creates a new Config singleton */
    public static function Create(ObjectDatabase $database) : static
    {
        return $database->CreateObject(static::class);
    }
    
    /** Returns the command usage for SetConfig() */
    public static function GetSetConfigUsage() : string { return 
        "[--rwchunksize uint32] [--crchunksize uint32] ".
        "[--upload_maxsize ?uint] [--periodicstats bool] [--apiurl ?url]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(SafeParams $params) : void
    {
        if ($params->HasParam('apiurl')) 
            $this->apiurl->SetValue($params->GetParam('apiurl')->GetNullUTF8String());
        
        if ($params->HasParam('rwchunksize')) 
            $this->rwchunksize->SetValue($params->GetParam('rwchunksize')->GetUint32());
        
        if ($params->HasParam('crchunksize')) 
            $this->crchunksize->SetValue($params->GetParam('crchunksize')->GetUint32());
        
        if ($params->HasParam('upload_maxsize')) 
            $this->upload_maxsize->SetValue($params->GetParam('upload_maxsize')->GetNullUint());
        
        if ($params->HasParam('periodicstats')) 
            $this->periodicstats->SetValue($params->GetParam('periodicstats')->GetBool());
    }

    /** Returns the block size that should be used for file reads and writes */
    public function GetRWChunkSize() : int { return $this->rwchunksize->GetValue(); }
    
    /** Returns the default block size for encrypted filesystems */
    public function GetCryptoChunkSize() : int { return $this->crchunksize->GetValue(); }
    
    /** Returns whether the periodic-stats system as a whole is enabled */
    public function GetAllowPeriodicStats() : bool { return $this->periodicstats->GetValue(); }
        
    /** Returns the URL this server API is accessible from over HTTP if known */
    public function GetApiUrl() : ?string { return $this->apiurl->TryGetValue(); }
    
    /**
     * Return the maximum allowed upload size as min(php post_max, php upload_max, admin config)
     * @return ?int max upload size in bytes or null if none
     */
    public function GetMaxUploadSize() : ?int
    {
        $iniA = ini_get('post_max_size');
        $iniB = ini_get('upload_max_filesize');

        $maxes = array();
        $maxes[] = ($iniA !== false) ? Utilities::return_bytes($iniA) : null;
        $maxes[] = ($iniB !== false) ? Utilities::return_bytes($iniB) : null;
        $maxes[] = $this->upload_maxsize->TryGetValue();

        $maxes = array_filter($maxes); // remove nulls
        return (count($maxes) === 0) ? null : min($maxes);
    }

    /**
     * Returns a printable client object for this config
     * @param bool $admin if true, show sensitive admin-only values
     * @return ($admin is true ? AdminConfigJ : ConfigJ)
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $ini = ini_get('max_file_uploads');
        $retval = array(
            'upload_maxbytes' => $this->GetMaxUploadSize(),
            'upload_maxfiles' => ($ini !== false) ? (int)$ini : null
        );
        
        if ($admin)
        {
            $iniA = ini_get('post_max_size');
            $iniB = ini_get('upload_max_filesize');
    
            $retval['apiurl'] = $this->apiurl->TryGetValue();
            $retval['date_created'] = $this->date_created->GetValue();
            $retval['rwchunksize'] = $this->rwchunksize->GetValue();
            $retval['crchunksize'] = $this->crchunksize->GetValue();
            $retval['upload_maxbytes_admin'] = $this->upload_maxsize->TryGetValue();
            $retval['php_post_max_size'] = ($iniA !== false) ? Utilities::return_bytes($iniA) : null;
            $retval['php_upload_max_filesize'] = ($iniB !== false) ? Utilities::return_bytes($iniB) : null;
            $retval['periodicstats'] = $this->periodicstats->GetValue();
        }
        
        return $retval;
    }
}
