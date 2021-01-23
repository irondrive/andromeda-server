<?php namespace Andromeda\Apps\Files\Limits; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;


require_once(ROOT."/apps/files/limits/Total.php");
require_once(ROOT."/apps/files/limits/Timed.php");

abstract class AuthTotal extends Total
{
    public static function GetDBClass() : string { return self::class; }
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__emailshare' => null,
            'features__userstorage' => null
        ));
    }

    public static function GetConfigUsage() : string { return static::GetBaseUsage()." [--emailshare bool] [--userstorage bool]"; }
    
    public static function BaseConfigLimits(ObjectDatabase $database, StandardObject $obj, Input $input) : self
    {
        $lim = parent::BaseConfigLimits($database, $obj, $input);
        
        if ($input->HasParam('emailshare')) $lim->SetFeature('emailshare', $input->TryGetParam('emailshare', SafeParam::TYPE_BOOL));
        if ($input->HasParam('userstorage')) $lim->SetFeature('userstorage', $input->TryGetParam('userstorage', SafeParam::TYPE_BOOL));
        
        return $lim;
    }
}

abstract class AuthTimed extends Timed
{
    // USAGE: -1 means keep forever, 0 means don't keep, null means no value/inherit, otherwise int for max age
    public static function GetTimedUsage() : string { return "[--max_stats_age -1|0|?int]"; }
    
    protected function SetTimedLimits(Input $input) : void
    {
        if ($input->HasParam('max_stats_age')) $this->SetScalar('max_stats_age', $input->TryGetParam('max_stats_age', SafeParam::TYPE_INT));
    }
}
