<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/BaseConfig.php"); use Andromeda\Core\BaseConfig;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** App config stored in the database */
class Config extends BaseConfig
{
    public static function getAppname() : string { return 'files'; }
    
    public static function getVersion() : string {
        return VersionInfo::toCompatVer(andromeda_version); }
        
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'apiurl' => new FieldTypes\StringType(),
            'rwchunksize' => new FieldTypes\IntType(4*1024*1024), // 4M
            'crchunksize' => new FieldTypes\IntType(1*1024*1024), // 1M
            'upload_maxsize' => new FieldTypes\IntType(),
            'timedstats' => new FieldTypes\BoolType(false)
        ));
    }
    
    /** Creates a new config singleton */
    public static function Create(ObjectDatabase $database) : self 
    { 
        return parent::BaseCreate($database); 
    }
    
    /** Returns the command usage for SetConfig() */
    public static function GetSetConfigUsage() : string { return "[--rwchunksize uint32] [--crchunksize uint32]".
                                          " [--upload_maxsize ?uint] [--timedstats bool] [--apiurl ?url]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(SafeParams $params) : self
    {
        if ($params->HasParam('apiurl')) $this->SetScalar('apiurl',$params->GetParam('apiurl')->GetNullUTF8String()); // TODO add and use URL SafeParam?
        
        if ($params->HasParam('rwchunksize')) $this->SetScalar('rwchunksize',$params->GetParam('rwchunksize')->GetUint32());
        if ($params->HasParam('crchunksize')) $this->SetScalar('crchunksize',$params->GetParam('crchunksize')->GetUint32());
        
        if ($params->HasParam('upload_maxsize')) $this->SetScalar('upload_maxsize',$params->GetParam('upload_maxsize')->GetNullUint());
        
        if ($params->HasParam('timedstats')) $this->SetFeatureBool('timedstats',$params->GetParam('timedstats')->GetBool());
        
        return $this;
    }

    /** Returns the block size that should be used for file reads and writes */
    public function GetRWChunkSize() : int { return $this->GetScalar('rwchunksize'); }
    
    /** Returns the default block size for encrypted filesystems */
    public function GetCryptoChunkSize() : int { return $this->GetScalar('crchunksize'); }
    
    /** Returns whether the timed-stats system as a whole is enabled */
    public function GetAllowTimedStats() : bool { return $this->GetFeatureBool('timedstats'); }
        
    /** Returns the URL this server API is accessible from over HTTP */
    public function GetAPIUrl() : ?string { return $this->TryGetScalar('apiurl'); }
    
    /**
     * Returns a printable client object for this config
     * @param bool $admin if true, show admin-only values
     * @return array `{uploadmax:{bytes:int, files:int}}` \
        if admin, add `{rwchunksize:int, crchunksize:int, upload_maxsize:?int, \
            apiurl:?string, config:{timedstats:bool}}`
     */
    public function GetClientObject(bool $admin = false) : array
    {
        $postmax = Utilities::return_bytes(ini_get('post_max_size'));
        $uploadmax = Utilities::return_bytes(ini_get('upload_max_size'));
        
        if (!$postmax) $postmax = PHP_INT_MAX;
        if (!$uploadmax) $uploadmax = PHP_INT_MAX;
        
        $adminmax = $this->TryGetScalar('upload_maxsize') ?? PHP_INT_MAX;

        $retval = array(
            'uploadmax' => array(
                'bytes' => min($postmax, $uploadmax, $adminmax),
                'files' => (int)ini_get('max_file_uploads'))
        );
        
        if ($admin)
        {
            $retval = array_merge($retval,array(
                'apiurl' => $this->GetAPIUrl(),
                'rwchunksize' => $this->GetRWChunkSize(),
                'crchunksize' => $this->GetCryptoChunkSize(),
                'upload_maxsize' => $this->TryGetScalar('upload_maxsize'),
                'config' => array(
                    'timedstats'=>$this->GetAllowTimedStats()
                )
            ));
        }
        
        return $retval;
    }
}