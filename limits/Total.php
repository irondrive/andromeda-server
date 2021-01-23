<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/files/limits/Base.php");

abstract class Total extends Base
{
    protected static $cache = array(); /* [objectID => self] */
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'dates__download' => null,
            'dates__upload' => null,
            'features__itemsharing' => null,
            'features__shareeveryone' => null,
            'features__publicupload' => null,
            'features__publicmodify' => null,
            'features__randomwrite' => null,
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
    
    public static function LoadByClient(ObjectDatabase $database, StandardObject $obj) : ?self
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::TryLoadUniqueByObject($database, 'object', $obj, true);
        }
        
        return static::$cache[$obj->ID()];
    }

    public function SetDownloadDate() : self { return $this->SetDate('download'); }
    public function SetUploadDate() : self { return $this->SetDate('upload'); }
    
    public function GetAllowRandomWrite() : ?bool { return $this->TryGetFeature('randomwrite'); }    
    public function GetAllowPublicModify() : ?bool { return $this->TryGetFeature('publicmodify'); }    
    public function GetAllowPublicUpload() : ?bool { return $this->TryGetFeature('publicupload'); }    
    public function GetAllowItemSharing() : ?bool { return $this->TryGetFeature('itemsharing'); }
    public function GetAllowShareEveryone() : ?bool { return $this->TryGetFeature('shareeveryone'); }
    
    protected static function Create(ObjectDatabase $database, StandardObject $obj) : self
    {
        $newobj = parent::BaseCreate($database)->SetObject('object',$obj);
        
        static::$cache[$obj->ID()] = $newobj; return $newobj;
    }
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage(); }

    public static function BaseConfigUsage() : string { return "[--randomwrite bool] [--publicmodify bool] [--publicupload bool] [--itemsharing bool] [--shareeveryone bool] [--max_size int] [--max_items int] [--max_shares int]"; }
        
    protected static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $lim = static::LoadByClient($database, $obj) ?? static::Create($database, $obj);
        
        $lim->SetBaseLimits($input);
        
        if ($input->HasParam('randomwrite')) $lim->SetFeature('randomwrite', $input->TryGetParam('randomwrite', SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicmodify')) $lim->SetFeature('publicmodify', $input->TryGetParam('publicmodify', SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicupload')) $lim->SetFeature('publicupload', $input->TryGetParam('publicupload', SafeParam::TYPE_BOOL));
        if ($input->HasParam('itemsharing')) $lim->SetFeature('itemsharing', $input->TryGetParam('itemsharing', SafeParam::TYPE_BOOL));
        if ($input->HasParam('shareeveryone')) $lim->SetFeature('shareeveryone', $input->TryGetParam('shareeveryone', SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('max_size')) $lim->SetCounterLimit('size', $input->TryGetParam('max_size', SafeParam::TYPE_INT));
        if ($input->HasParam('max_items')) $lim->SetCounterLimit('items', $input->TryGetParam('max_items', SafeParam::TYPE_INT));
        if ($input->HasParam('max_shares')) $lim->SetCounterLimit('shares', $input->TryGetParam('max_shares', SafeParam::TYPE_INT));

        if ($lim->isCreated()) $lim->Initialize();
        
        return $lim;
    }
}

