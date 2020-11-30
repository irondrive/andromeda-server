<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'rwchunksize' => null,
            'crchunksize' => null,
            'features__userstorage' => null,
            'features__randomwrite' => null,
            'features__publicmodify' => null,
            'features__publicupload' => null,
            'features__shareeveryone' => null,
            'features__emailshare' => null
        ));
    }

    public function GetRWChunkSize() : int { return $this->TryGetScalar('rwchunksize') ?? 1024*1024; }
    public function SetRWChunkSize(int $size) : self { return $this->SetScalar('rwchunksize', $size); }
    
    public function GetCryptoChunkSize() : int { return $this->TryGetScalar('crchunksize') ?? 128*1024; }
    public function SetCryptoChunkSize(int $size) : self { return $this->SetScalar('crchunksize', $size); }
    
    public function GetAllowUserStorage() : bool { return $this->TryGetFeature('userstorage') ?? false; }
    public function SetAllowUserStorage(bool $allow) : self { return $this->SetFeature('userstorage', $allow); }
    
    public function GetAllowRandomWrite() : bool { return $this->TryGetFeature('randomwrite') ?? true; }
    public function SetAllowRandomWrite(bool $allow) : self { return $this->SetFeature('randomwrite', $allow); }
    
    public function GetAllowPublicModify() : bool { return $this->TryGetFeature('publicmodify') ?? false; }
    public function SetAllowPublicModify(bool $allow) : self { return $this->SetFeature('publicmodify', $allow); } 
    
    public function GetAllowPublicUpload() : bool { return $this->TryGetFeature('publicupload') ?? false; }
    public function SetAllowPublicUpload(bool $allow) : self { return $this->SetFeature('publicupload', $allow); }
    
    public function GetAllowShareEveryone() : bool { return $this->TryGetFeature('shareeveryone') ?? false; }
    public function SetAllowShareEveryone(bool $allow) : self { return $this->SetFeature('shareeveryone', $allow); }
    
    public function GetAllowEmailShare() : bool { return $this->TryGetFeature('emailshare') ?? false; }
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