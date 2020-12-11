<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

class InheritedProperty
{
    private $value; private ?AuthEntity $source;
    public function GetValue() { return $this->value; }
    public function GetSource() : ?AuthEntity { return $this->source; }
    public function __construct($value, ?AuthEntity $source){
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
    
    public static function GetPropUsage() : string { return "[--max_session_age int] [--max_password_age int] [--max_sessions int] [--max_contactinfos int] [--max_recoverykeys int] ".
                                                            "[--admin bool] [--enabled bool] [--forcetf bool] [--allowcrypto bool]"; }
    
    public function SetModified() : self { return $this->SetDate('modified'); }
    
    public function SetProperties(Input $input) : self
    {
        foreach (array('max_session_age','max_password_age') as $prop)
            if ($input->HasParam($prop)) $this->SetScalar($prop, $input->TryGetParam($prop, SafeParam::TYPE_INT));
        
        foreach (array('max_sessions','max_contactinfos','max_recoverykeys') as $prop)
            if ($input->HasParam($prop)) $this->SetCounterLimit(str_replace('max_','',$prop), $input->TryGetParam($prop, SafeParam::TYPE_INT));
        
        foreach (array('admin','enabled','forcetf','allowcrypto') as $prop)
            if ($input->HasParam($prop)) $this->SetFeature($prop, $input->TryGetParam($prop, SafeParam::TYPE_BOOL));
        
        return $this->SetModified();
    }
}
