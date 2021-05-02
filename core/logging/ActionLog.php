<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/core/logging/BaseLog.php");
require_once(ROOT."/core/logging/BaseAppLog.php");
require_once(ROOT."/core/logging/RequestLog.php");

/** 
 * Log entry representing an app action in a request 
 * 
 * Performs a join with RequestLog when loading so that the user can
 * filter by request parameters and action parameters simulataneously.
 */
class ActionLog extends BaseLog
{    
    public static function GetFieldTemplate() : array
    {
        return array(
            'request' => new FieldTypes\ObjectRef(RequestLog::class, 'actions'),
            'applog' => new FieldTypes\ObjectPoly(BaseAppLog::class),
            'app' => null,
            'action' => null,
            'details' => new FieldTypes\JSON()
        );
    }
    
    /** Creates a new action log with the given input and request log */
    public static function Create(ObjectDatabase $database, RequestLog $reqlog, Input $input) : self
    {
        return parent::BaseCreate($database)->SetObject('request', $reqlog)
            ->SetScalar('app', $input->GetApp())->SetScalar('action',$input->GetAction());
    }
    
    /** Returns the ActionLog corresponding to the given BaseAppLog */
    public static function LoadByApplog(ObjectDatabase $database, BaseAppLog $applog) : self
    {
        return static::TryLoadUniqueByObject($database, 'applog', $applog, true);
    }
    
    /** Sets an extended BaseAppLog to accompany this ActionLog */
    public function SetApplog(BaseAppLog $applog) : self { return $this->SetObject('applog', $applog); }
    
    /** 
     * Sets the log's app-specific "details" field
     * 
     * This should be used for data that doesn't make sense to have its own DB column.
     * As this field is stored as JSON, its subfields cannot be selected by in the DB.
     * 
     * @param array $details the data values to log
     */
    public function SetDetails(array $details) : self
    {
        return $this->SetScalar('details', $details);
    }
    
    public function Save(bool $onlyMandatory = false) : self
    {
        // make sure the applog is saved also in case of rollback
        if (($applog = $this->TryGetObject('applog')) !== null) $applog->Save();
        
        if (Main::GetInstance()->GetConfig()->GetEnableRequestLogDB()) parent::Save($onlyMandatory);

        return $this;
    }

    public static function GetPropUsage(bool $withapp = true) : string { return RequestLog::GetPropUsage()." ".($withapp?"[--appname alphanum] ":'')."[--action alphanum]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input, bool $withapp = true) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($withapp && $input->HasParam('appname')) $criteria[] = $q->Equals("$table.app", $input->GetParam('appname',SafeParam::TYPE_ALPHANUM));
        
        if ($input->HasParam('action')) $criteria[] = $q->Equals("$table.action", $input->GetParam('action',SafeParam::TYPE_ALPHANUM));
        
        $q->Join($database, RequestLog::class, 'id', static::class, 'request');
        
        return array_merge($criteria, RequestLog::GetPropCriteria($database, $q, $input));
    }

    /**
     * Returns the printable client object of this action log
     * @return array `{app:string, action:string}`
        if details, add: `{details:array}`
     */
    protected function GetBaseClientObject(bool $details = false) : array
    {
        $retval = array_map(function(FieldTypes\Scalar $e){
            return $e->GetValue(); }, $this->scalars);
        
        if (!$details || !$retval['details']) unset($retval['details']);
        
        return $retval;
    }
    
    /**
     * Returns the printable client object of this action log, with the request log
     * @see ActionLog::GetBaseClientObject()
     * @see RequestLog::GetClientObject()
     * @return array add `{request:RequestLog}`
     */
    public function GetReqClientObject() : array
    {
        $retval = $this->GetBaseClientObject(true);
        
        $retval['request'] = $this->GetObject('request')->GetClientObject();
        
        return $retval;
    }
    
    /**
     * Returns the printable client object of this action log, with the applog
     * @see ActionLog::GetBaseClientObject()
     * @return array add `{applog:BaseAppLog}`
     */
    public function GetAppClientObject(bool $expand = false, bool $applogs = false) : array
    {
        $retval = $this->GetBaseClientObject($applogs);

        if ($applogs && ($applog = $this->TryGetObject('applog')) !== null)
            $retval['applog'] = $applog->GetClientObject($expand);
        
        return $retval;
    } 

    /**
     * Returns the printable client object of this action log, with both the request and applogs
     * @see ActionLog::GetAppClientObject()
     * @see ActionLog::GetReqClientObject()
     */
    public function GetFullClientObject(bool $expand = false, bool $applogs = false) : array
    {
        return array_merge($this->GetReqClientObject(),
                           $this->GetAppClientObject($expand, $applogs));
    }
}
