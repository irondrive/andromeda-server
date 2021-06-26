<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/SingletonObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

/** App config stored in the database */
class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'apiurl' => null,
            'rwchunksize' => new FieldTypes\Scalar(4*1024*1024),
            'crchunksize' => new FieldTypes\Scalar(128*1024),
            'upload_maxsize' => new FieldTypes\Scalar(),
            'features__timedstats' => new FieldTypes\Scalar(false)
        ));
    }
    
    /** Creates a new config singleton */
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database); }
    
    /** Returns the command usage for SetConfig() */
    public static function GetSetConfigUsage() : string { return "[--rwchunksize uint] [--crchunksize uint] [--upload_maxsize ?uint] ".
        "[--timedstats bool] [--apiurl ?string]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('apiurl')) $this->SetScalar('apiurl',$input->GetNullParam('apiurl',SafeParam::TYPE_RAW));
        
        if ($input->HasParam('rwchunksize')) $this->SetScalar('rwchunksize',$input->GetParam('rwchunksize',SafeParam::TYPE_UINT));
        if ($input->HasParam('crchunksize')) $this->SetScalar('crchunksize',$input->GetParam('crchunksize',SafeParam::TYPE_UINT));
        
        if ($input->HasParam('upload_maxsize')) $this->SetScalar('upload_maxsize',$input->GetNullParam('upload_maxsize',SafeParam::TYPE_UINT));
            
        if ($input->HasParam('timedstats')) $this->SetFeature('timedstats',$input->GetParam('timedstats',SafeParam::TYPE_BOOL));
                    
        return $this;
    }

    /** Returns the block size that should be used for file reads and writes */
    public function GetRWChunkSize() : int { return $this->GetScalar('rwchunksize'); }
    
    /** Returns the default block size for encrypted filesystems */
    public function GetCryptoChunkSize() : int { return $this->GetScalar('crchunksize'); }
    
    /** Returns whether the timed-stats system as a whole is enabled */
    public function GetAllowTimedStats() : bool { return $this->GetFeature('timedstats'); }
        
    /** Returns the URL this server API is accessible from over HTTP */
    public function GetAPIUrl() : ?string { return $this->TryGetScalar('apiurl'); }
    
    /**
     * Returns a printable client object for this config
     * @param bool $admin if true, show admin-only values
     * @return array `{uploadmax:{bytes:int, files:int}, features:{timedstats:bool}}` \
        if admin, add `{rwchunksize:int, crchunksize:int, upload_maxsize:?int, apiurl:?string}`
     */
    public function GetClientObject(bool $admin) : array
    {
        $postmax = Utilities::return_bytes(ini_get('post_max_size'));
        $uploadmax = Utilities::return_bytes(ini_get('upload_max_size'));
        
        if (!$postmax) $postmax = PHP_INT_MAX;
        if (!$uploadmax) $uploadmax = PHP_INT_MAX;
        
        $adminmax = $this->TryGetScalar('upload_maxsize') ?? PHP_INT_MAX;

        $retval = array(
            'uploadmax' => array(
                'bytes' => min($postmax, $uploadmax, $adminmax),
                'files' => ini_get('max_file_uploads')),
            'features' => $this->GetAllFeatures()
        );
        
        if ($admin)
        {
            $retval = array_merge($retval,array(
                'apiurl' => $this->GetAPIUrl(),
                'rwchunksize' => $this->GetRWChunkSize(),
                'crchunksize' => $this->GetCryptoChunkSize(),
                'upload_maxsize' => $this->TryGetScalar('upload_maxsize')
            ));
        }
        
        return $retval;
    }
}