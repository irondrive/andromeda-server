<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class InheritedProperty
{
    private $value; private ?BaseObject $source;
    public function GetValue() { return $this->value; }
    public function GetSource() : ?BaseObject { return $this->source; }
    public function __construct($value, ?BaseObject $source){
        $this->value = $value; $this->source = $source; }
}

class GroupJoin extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'accounts' => new FieldTypes\ObjectRef(Account::class, 'groups'),
            'groups' => new FieldTypes\ObjectRef(Group::class, 'accounts')
        ));
    }
}

abstract class AuthEntity extends StandardObject
{
    public abstract function GetDisplayName() : string;
    public abstract function GetMailTo() : array;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'features__admin' => null,
            'features__enabled' => null,
            'features__forcetf' => null,
            'features__allowcrypto' => null,
            'counters_limits__sessions' => null,
            'counters_limits__contactinfos' => null,
            'counters_limits__recoverykeys' => null,
            'max_session_age' => null,
            'max_password_age' => null,
            'dates__modified' => null
        ));
    }
    
    public static function GetPropUsage() : string { return "[--max_session_age int] [--max_password_age int] ".
                                                            "[--max_sessions int] [--max_contactinfos int] [--max_recoverykeys int] ".
                                                            "[--admin bool] [--enabled bool] [--forcetf bool] [--allowcrypto bool]"; }

    public function SetProperties(Input $input) : self
    {
        foreach (array('max_session_age','max_password_age') as $prop)
            if ($input->HasParam($prop)) $this->SetScalar($prop, $input->TryGetParam($prop, SafeParam::TYPE_INT));
        
        foreach (array('max_sessions','max_contactinfos','max_recoverykeys') as $prop)
            if ($input->HasParam($prop)) $this->SetCounterLimit(str_replace('max_','',$prop), $input->TryGetParam($prop, SafeParam::TYPE_INT));
        
        foreach (array('admin','enabled','forcetf','allowcrypto') as $prop)
            if ($input->HasParam($prop)) $this->SetFeature($prop, $input->TryGetParam($prop, SafeParam::TYPE_BOOL));
        
        return $this->SetDate('modified');
    }
}
