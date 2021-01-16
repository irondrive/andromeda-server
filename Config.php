<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'rwchunksize' => new FieldTypes\Scalar(4*1024*1024),
            'crchunksize' => new FieldTypes\Scalar(128*1024),
            'features__timedstats' => new FieldTypes\Scalar(false)
        ));
    }
    
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database); }
    
    public static function GetSetConfigUsage() : string { return "[--rwchunksize int] [--crchunksize int] [--timedstats bool]"; }
    
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('rwchunksize')) $this->SetScalar('rwchunksize',$input->GetParam('rwchunksize',SafeParam::TYPE_INT));
        if ($input->HasParam('crchunksize')) $this->SetScalar('crchunksize',$input->GetParam('crchunksize',SafeParam::TYPE_INT));
        if ($input->HasParam('timedstats')) $this->SetFeature('timedstats',$input->GetParam('timedstats',SafeParam::TYPE_BOOL));
                    
        return $this;
    }

    public function GetRWChunkSize() : int { return $this->GetScalar('rwchunksize'); }    
    public function GetCryptoChunkSize() : int { return $this->GetScalar('crchunksize'); }
    public function GetAllowTimedStats() : bool { return $this->GetFeature('timedstats'); }    

    public function GetClientObject(bool $admin) : array
    {
        $postmax = Utilities::return_bytes(ini_get('post_max_size'));
        $uploadmax = Utilities::return_bytes(ini_get('upload_max_size'));
        if (!$postmax) $postmax = PHP_INT_MAX;
        if (!$uploadmax) $uploadmax = PHP_INT_MAX;
        
        $retval = array(
            'uploadmax' => min($postmax, $uploadmax),
            'maxfiles' => ini_get('max_file_uploads'),
            'features' => $this->GetAllFeatures()
        );
        
        if ($admin)
        {
            $retval = array_merge($retval,array(
                'rwchunksize' => $this->GetRWChunkSize(),
                'crchunksize' => $this->GetCryptoChunkSize()
            ));
        }
        
        return $retval;
    }
}