<?php namespace Andromeda\Apps\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Core/Logging/BaseAppLog.php"); use Andromeda\Core\Logging\BaseAppLog;

/** Core app access log for use without the accounts app installed */
class AccessLog extends BaseAppLog
{    
    public static function GetFieldTemplate() : array
    {
        return array(
            'admin' => null,
            'account' => null, // unused
            'sudouser' => null, // unused
            'client' => null // unused
        );
    }
    
    /** 
     * Creates a new log object that logs whether or not the request was done as admin - may return null
     * 
     * $auth is unused - just to retain signature compatibility with FullAccessLog::Create()
     * @see BaseAppLog::BaseRunCreate()
     */
    public static function Create(ObjectDatabase $database, $auth, bool $isAdmin) : ?self
    {
        $obj = parent::BaseRunCreate($database);
        
        if ($obj !== null) $obj->SetScalar('admin', $isAdmin);
        
        return $obj;
    }

    public static function GetPropUsage() : string { return "[--admin bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($input->HasParam('admin')) $criteria[] = $input->GetParam('admin',SafeParam::TYPE_BOOL)
            ? $q->IsTrue("$table.admin") : $q->Not($q->IsTrue("$table.admin"));
            
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $input));
    }
    
    /**
     * Returns the printable client object of this access log
     * @return array `{admin:bool}`
     */
    public function GetClientObject(bool $expand = false) : array
    {
        return array('admin' => (bool)$this->GetScalar('admin'));
    }
}
