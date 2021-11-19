<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Config.php"); use Andromeda\Core\DBVersion;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

/** App config stored in the database */
class Config extends DBVersion
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'apiurl' => null,
            'rwchunksize' => new FieldTypes\Scalar(4*1024*1024), // 4M
            'crchunksize' => new FieldTypes\Scalar(1*1024*1024), // 1M
            'upload_maxsize' => null,
            'features__timedstats' => new FieldTypes\Scalar(false)
        ));
    }
    
    /** Creates a new config singleton */
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database)->setVersion(FilesApp::getVersion()); }
    
    /** Returns the command usage for SetConfig() */
    public static function GetSetConfigUsage() : string { return "[--rwchunksize uint] [--crchunksize uint]".
                                          " [--upload_maxsize ?uint] [--timedstats bool] [--apiurl ?string]"; }
    
    /** Updates config with the parameters in the given input (see CLI usage) */
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('apiurl')) $this->SetScalar('apiurl',$input->GetNullParam('apiurl',SafeParam::TYPE_RAW));
        
        if ($input->HasParam('rwchunksize')) $this->SetScalar('rwchunksize',$input->GetParam('rwchunksize',SafeParam::TYPE_UINT,SafeParam::MaxValueBits(32)));
        if ($input->HasParam('crchunksize')) $this->SetScalar('crchunksize',$input->GetParam('crchunksize',SafeParam::TYPE_UINT,SafeParam::MaxValueBits(32)));
        
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
     * @return array `{uploadmax:{bytes:int, files:int}}` \
        if admin, add `{rwchunksize:int, crchunksize:int, upload_maxsize:?int, \
            apiurl:?string, features:{timedstats:bool}}`
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
                'files' => (int)ini_get('max_file_uploads'))
        );
        
        if ($admin)
        {
            $retval = array_merge($retval,array(
                'apiurl' => $this->GetAPIUrl(),
                'rwchunksize' => $this->GetRWChunkSize(),
                'crchunksize' => $this->GetCryptoChunkSize(),
                'upload_maxsize' => $this->TryGetScalar('upload_maxsize'),
                'features' => array(
                    'timedstats'=>$this->GetAllowTimedStats()
                )
            ));
        }
        
        return $retval;
    }
}