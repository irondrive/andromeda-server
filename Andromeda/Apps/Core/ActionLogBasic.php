<?php namespace Andromeda\Apps\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;

require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog as BaseActionLog;

/** Core app access log for use without the accounts app installed */
final class ActionLog extends BaseActionLog
{
    use TableNoChildren;
    
    /** True if the action was done as admin */
    private FieldTypes\NullBoolType $admin;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->admin = $fields[] = new FieldTypes\NullBoolType('admin');
        
        $fields[] = new FieldTypes\NullStringType('account'); // unused 
        $fields[] = new FieldTypes\NullStringType('sudouser'); // unused
        $fields[] = new FieldTypes\NullStringType('client'); // unused
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    public function SetAdmin(bool $isAdmin) : self
    {
        $this->admin->SetValue($isAdmin ? true : null); return $this;
    }
    
    protected static function GetAppPropUsage() : string { return "[--admin bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $join = true) : array
    {
        $criteria = array();
        
        if ($params->HasParam('admin'))
            $criteria[] = $params->GetParam('admin')->GetBool() 
                ? $q->IsTrue("admin") : $q->Not($q->IsTrue("admin"));
            
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params, $join));
    }
    
    /**
     * Returns the printable client object of this access log
     * @return array<mixed> `{admin:bool}`
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = parent::GetClientObject($expand);
        
        $retval['admin'] = (bool)$this->admin->TryGetValue();
        
        return $retval;
    }
}
