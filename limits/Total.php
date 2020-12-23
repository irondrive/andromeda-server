<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/files/limits/Base.php");

abstract class Total extends Base
{
    protected static $cache = array(); /* [objectID => self] */
    
    public static function GetDBClass() : string { return self::class; }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'dates__download' => null,
            'dates__upload' => null,
            'features__itemsharing' => null,
            'features__shareeveryone' => null,
            'features__emailshare' => null,
            'features__publicupload' => null,
            'features__publicmodify' => null,
            'features__randomwrite' => null,
            'features__userstorage' => null,
            'counters__downloads' => new FieldTypes\Counter(),
            'counters__bandwidth' => new FieldTypes\Counter(true),
            'counters__size' => new FieldTypes\Counter(),
            'counters__items' => new FieldTypes\Counter(),
            'counters__shares' => new FieldTypes\Counter(),
            'counters_limits__size' => null,
            'counters_limits__items' => null,
            'counters_limits__shares' => null
        ));
    }
    
    protected static function BaseLoadFromDB(ObjectDatabase $database, StandardObject $obj) : ?self
    {
        return static::TryLoadUniqueByObject($database, 'object', $obj, true);
    }
    
    protected static function LoadByClient(ObjectDatabase $database, StandardObject $obj) : ?self
    {
        return static::BaseLoad($database, $obj);
    }
    
    protected static function Create(ObjectDatabase $database, StandardObject $obj) : self
    {
        $newobj = parent::BaseCreate($database)->SetObject('object',$obj);
        
        static::$cache[$obj->ID()] = $newobj; return $newobj;
    }
    
    protected abstract function FinalizeCreate() : self;
    
    public function SetDownloadDate() : self { return $this->SetDate('download'); }
    public function SetUploadDate() : self { return $this->SetDate('upload'); }
    
    public function GetAllowRandomWrite() : ?bool { return $this->TryGetFeature('randomwrite'); }    
    public function GetAllowPublicModify() : ?bool { return $this->TryGetFeature('publicmodify'); }    
    public function GetAllowPublicUpload() : ?bool { return $this->TryGetFeature('publicupload'); }    
    public function GetAllowItemSharing() : ?bool { return $this->TryGetFeature('itemsharing'); }
    public function GetAllowShareEveryone() : ?bool { return $this->TryGetFeature('shareeveryone'); }    
    public function GetAllowEmailShare() : ?bool { return $this->TryGetFeature('emailshare'); }
    public function GetAllowUserStorage() : ?bool { return $this->TryGetFeature('userstorage'); }
}

