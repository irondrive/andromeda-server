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
            'rwchunksize' => new FieldTypes\Scalar(1024*1024),
            'crchunksize' => new FieldTypes\Scalar(128*1024),
            'features__userstorage' => new FieldTypes\Scalar(false),
            'features__randomwrite' => new FieldTypes\Scalar(true),
            'features__publicmodify' => new FieldTypes\Scalar(false),
            'features__publicupload' => new FieldTypes\Scalar(false),
            'features__shareeveryone' => new FieldTypes\Scalar(false),
            'features__emailshare' => new FieldTypes\Scalar(false)
        ));
    }
    
    public static function Create(ObjectDatabase $database) : self { return parent::BaseCreate($database); }
    
    public static function GetSetConfigUsage() : string { return "[--rwchunksize int] [--crchunksize int] [--userstorage bool] [--randomwrite bool]".
                                                                 "[--publicmodify bool] [--publicupload bool] [--shareeveryone bool] [--emailshare bool]"; }
    
    public function SetConfig(Input $input) : self
    {
        if ($input->HasParam('rwchunksize')) $this->SetScalar('rwchunksize',$input->GetParam('rwchunksize',SafeParam::TYPE_INT));
        if ($input->HasParam('crchunksize')) $this->SetScalar('crchunksize',$input->GetParam('crchunksize',SafeParam::TYPE_INT));
        
        if ($input->HasParam('userstorage')) $this->SetFeature('userstorage',$input->GetParam('userstorage',SafeParam::TYPE_BOOL));
        if ($input->HasParam('randomwrite')) $this->SetFeature('randomwrite',$input->GetParam('randomwrite',SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicmodify')) $this->SetFeature('publicmodify',$input->GetParam('publicmodify',SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicupload')) $this->SetFeature('publicupload',$input->GetParam('publicupload',SafeParam::TYPE_BOOL));
        if ($input->HasParam('shareeveryone')) $this->SetFeature('shareeveryone',$input->GetParam('shareeveryone',SafeParam::TYPE_BOOL));
        if ($input->HasParam('emailshare')) $this->SetFeature('emailshare',$input->GetParam('emailshare',SafeParam::TYPE_BOOL));
                    
        return $this;
    }

    public function GetRWChunkSize() : int { return $this->GetScalar('rwchunksize'); }
    public function SetRWChunkSize(int $size) : self { return $this->SetScalar('rwchunksize', $size); }
    
    public function GetCryptoChunkSize() : int { return $this->GetScalar('crchunksize'); }
    public function SetCryptoChunkSize(int $size) : self { return $this->SetScalar('crchunksize', $size); }
    
    public function GetAllowUserStorage() : bool { return $this->GetFeature('userstorage'); }
    public function SetAllowUserStorage(bool $allow) : self { return $this->SetFeature('userstorage', $allow); }
    
    public function GetAllowRandomWrite() : bool { return $this->GetFeature('randomwrite'); }
    public function SetAllowRandomWrite(bool $allow) : self { return $this->SetFeature('randomwrite', $allow); }
    
    public function GetAllowPublicModify() : bool { return $this->GetFeature('publicmodify'); }
    public function SetAllowPublicModify(bool $allow) : self { return $this->SetFeature('publicmodify', $allow); } 
    
    public function GetAllowPublicUpload() : bool { return $this->GetFeature('publicupload'); }
    public function SetAllowPublicUpload(bool $allow) : self { return $this->SetFeature('publicupload', $allow); }
    
    public function GetAllowShareEveryone() : bool { return $this->GetFeature('shareeveryone'); }
    public function SetAllowShareEveryone(bool $allow) : self { return $this->SetFeature('shareeveryone', $allow); }
    
    public function GetAllowEmailShare() : bool { return $this->GetFeature('emailshare'); }
    public function SetAllowEmailShare(bool $allow) : self { return $this->SetFeature('emailshare', $allow); }
    
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