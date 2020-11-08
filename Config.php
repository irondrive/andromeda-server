<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'chunksize' => null,
            'features__userstorage' => null
        ));
    }

    public function GetRWChunkSize() : int { return $this->TryGetScalar('rwchunksize') ?? 1024*1024; }
    public function SetRWChunkSize(int $size) : self { return $this->SetScalar('rwchunksize', $size); }
    
    public function GetCryptoChunkSize() : int { return $this->TryGetScalar('crchunksize') ?? 128*1024; }
    public function SetCryptoChunkSize(int $size) : self { return $this->SetScalar('crchunksize', $size); }
    
    public function GetAllowUserStorage() : bool { return $this->TryGetFeature('userstorage') ?? false; }
    public function SetAllowUserStorage(bool $allow) : self { return $this->SetFeature('userstorage', $allow); }
    
    public function GetClientObject() : array
    {
        return array(
            'chunksize' => $this->GetChunkSize()
        );
    }
}