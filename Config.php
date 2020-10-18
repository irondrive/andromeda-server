<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\SingletonObject;

class Config extends SingletonObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'chunksize' => null
        ));
    }

    public function GetChunkSize() : int { return $this->TryGetScalar('chunksize') ?? 1024*1024; }
    public function SetChunkSize(int $size) : self { return $this->SetScalar('chunksize', $size); }
    
    public function GetClientObject() : array
    {
        return array(
            'chunksize' => $this->GetChunkSize()
        );
    }
}