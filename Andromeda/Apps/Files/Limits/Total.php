<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/Apps/Files/Limits/Base.php");

/**
 * The basic kind of limit not subject to a timeperiod, also stores config
 *
 * Stores counters that are cumulative over all-time and allows limiting
 * those that make sense (bandwidth cannot decrease so that cannot be limited here).
 * Also allows storing config that is specific to the limited object.
 */
abstract class Total extends Base
{
    /** array<limited object ID, self] */
    protected static $cache = array();
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'dates__download' => null,
            'dates__upload' => null,
            'features__itemsharing' => null,
            'features__share2groups' => null,
            'features__share2everyone' => null,
            'features__publicupload' => null,
            'features__publicmodify' => null,
            'features__randomwrite' => null,
            'counters__pubdownloads' => new FieldTypes\Counter(),
            'counters__bandwidth' => new FieldTypes\Counter(true),
            'counters__size' => new FieldTypes\Counter(),
            'counters__items' => new FieldTypes\Counter(),
            'counters__shares' => new FieldTypes\Counter(),
            'counters_limits__size' => null,
            'counters_limits__items' => null,
            'counters_limits__shares' => null
        ));
    }
    
    /** 
     * Loads the limit object corresponding to the given limited object 
     * @return static
     */
    public static function LoadByClient(ObjectDatabase $database, StandardObject $obj) : ?self
    {
        if (!array_key_exists($obj->ID(), static::$cache))
        {
            static::$cache[$obj->ID()] = static::TryLoadUniqueByObject($database, 'object', $obj, true);
        }
        
        return static::$cache[$obj->ID()];
    }
    
    /** Deletes the limit object corresponding to the given limited object */
    public static function DeleteByClient(ObjectDatabase $database, StandardObject $obj) : void
    {
        if (array_key_exists($obj->ID(), static::$cache)) static::$cache[$obj->ID()] = null;
        
        static::DeleteByObject($database, 'object', $obj, true);
    }

    /** Updates the last download timestamp to now */
    public function SetDownloadDate() : self { return $this->canTrackDLStats() ? $this->SetDate('download') : $this; }
    
    /** Updates the last upload (file create) timestamp to now */
    public function SetUploadDate() : self { return $this->canTrackDLStats() ? $this->SetDate('upload') : $this; }
    
    /** Returns true if the limited object should allow random writes to files */
    public function GetAllowRandomWrite() : ?bool { return $this->TryGetFeatureBool('randomwrite'); }
    
    /** Returns true if the limited object should allow public modification of files */
    public function GetAllowPublicModify() : ?bool { return $this->TryGetFeatureBool('publicmodify'); } 
    
    /** Returns true if the limited object should allow public upload to folders */
    public function GetAllowPublicUpload() : ?bool { return $this->TryGetFeatureBool('publicupload'); }
    
    /** Returns true if the limited object should allow sharing items */
    public function GetAllowItemSharing() : ?bool { return $this->TryGetFeatureBool('itemsharing'); }
    
    /** Returns true if the limited object should allow sharing to groups */
    public function GetAllowShareToGroups() : ?bool { return $this->TryGetFeatureBool('share2groups'); }
    
    /** Returns true if the limited object should allow sharing to everyone */
    public function GetAllowShareToEveryone() : ?bool { return $this->TryGetFeatureBool('share2everyone'); }
    
    /** 
     * Creates and caches a new limit object for the given limited object 
     * @return static
     */
    protected static function Create(ObjectDatabase $database, StandardObject $obj) : self
    {
        $newobj = parent::BaseCreate($database)->SetObject('object',$obj);
        
        static::$cache[$obj->ID()] = $newobj; return $newobj;
    }
    
    public static function GetConfigUsage() : string { return static::GetBaseUsage(); }

    public static function BaseConfigUsage() : string { return "[--randomwrite ?bool] [--publicmodify ?bool] [--publicupload ?bool] ".
                                                               "[--itemsharing ?bool] [--share2groups ?bool] [--share2everyone ?bool] ".
                                                               "[--max_size ?uint] [--max_items ?uint32] [--max_shares ?uint32]"; }
        
    protected static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $lim = static::LoadByClient($database, $obj) ?? static::Create($database, $obj);
        
        $lim->SetBaseLimits($input);
        
        if ($input->HasParam('randomwrite')) $lim->SetFeatureBool('randomwrite', $input->GetNullParam('randomwrite', SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicmodify')) $lim->SetFeatureBool('publicmodify', $input->GetNullParam('publicmodify', SafeParam::TYPE_BOOL));
        if ($input->HasParam('publicupload')) $lim->SetFeatureBool('publicupload', $input->GetNullParam('publicupload', SafeParam::TYPE_BOOL));
        if ($input->HasParam('itemsharing')) $lim->SetFeatureBool('itemsharing', $input->GetNullParam('itemsharing', SafeParam::TYPE_BOOL));
        if ($input->HasParam('share2groups')) $lim->SetFeatureBool('share2groups', $input->GetNullParam('share2groups', SafeParam::TYPE_BOOL));
        if ($input->HasParam('share2everyone')) $lim->SetFeatureBool('share2everyone', $input->GetNullParam('share2everyone', SafeParam::TYPE_BOOL));
        
        if ($input->HasParam('max_size')) $lim->SetCounterLimit('size', $input->GetNullParam('max_size', SafeParam::TYPE_UINT));
        if ($input->HasParam('max_items')) $lim->SetCounterLimit('items', $input->GetNullParam('max_items', SafeParam::TYPE_UINT32));
        if ($input->HasParam('max_shares')) $lim->SetCounterLimit('shares', $input->GetNullParam('max_shares', SafeParam::TYPE_UINT32));

        if ($lim->isCreated()) $lim->Initialize();
        
        return $lim;
    }
    
    /** Sets all stats counters to zero */
    protected function Initialize() : self
    {
        foreach (array('items','size','shares','pubdownloads','bandwidth') as $prop) 
            $this->DeltaCounter($prop, $this->GetCounter($prop)*-1);
        
        if (!$this->canTrackDLStats())
        {
            foreach (array('upload','download') as $prop) 
                $this->SetDate($prop, null);
        }
        
        return $this;
    }
    
    /**
     * Returns a printable client object of this timed limit
     * @param bool $full if false, show features only (no dates,counters,limits)
     * @return array `{dates:{created:float, download:?float, upload:?float}, features:{itemsharing:?bool, \
        share2everyone:?bool, share2groups:?bool, publicupload:?bool, publicmodify:?bool, randomwrite:?bool},
        limits:{size:?int, items:?int, shares:?int}, counters:{size:int, items:int, shares:int, pubdownloads:int, bandwidth:int}}`
     * @see Base::GetClientObject()
     */
    public function GetClientObject(bool $full) : array
    {
        $retval = array_merge(parent::GetClientObject($full), array(
            'features' => Utilities::array_map_keys(function($p){ return $this->TryGetFeatureBool($p); },
                array('itemsharing','share2everyone','share2groups',
                      'publicupload','publicmodify','randomwrite'))
        ));
        
        if ($full)
        {
            $retval['dates'] = array(
                'created' => $this->GetDateCreated(),
                'download' => $this->TryGetDate('download'),
                'upload' => $this->TryGetDate('upload')
            );
            
            $retval['counters'] = Utilities::array_map_keys(function($p){ return $this->GetCounter($p); },
                array('size','items','shares','pubdownloads','bandwidth'));
            
            $retval['limits'] = Utilities::array_map_keys(function($p){ return $this->TryGetCounterLimit($p); },
                array('size','items','shares'));
        }
        
        return $retval;
    }
}
