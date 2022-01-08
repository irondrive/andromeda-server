<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Core/Logging/BaseLog.php");
require_once(ROOT."/Core/Logging/BaseAppLog.php");
require_once(ROOT."/Core/Logging/RequestLog.php");

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
            'obj_request' => new FieldTypes\ObjectRef(RequestLog::class, 'actions'),
            'obj_applog' => new FieldTypes\ObjectPoly(BaseAppLog::class),
            'app' => new FieldTypes\StringType(),
            'action' => new FieldTypes\StringType(),
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
    
    /** @return RequestLog the parent request log */
    private function GetRequestLog() : RequestLog { return $this->GetObject('request'); }
    
    /** @return ?BaseAppLog the relevant BaseAppLog if it exists */
    private function TryGetApplog() : ?BaseAppLog { return $this->TryGetObject('applog'); }
    
    /** Sets an extended BaseAppLog to accompany this ActionLog */
    public function SetApplog(BaseAppLog $applog) : self { return $this->SetObject('applog', $applog); }
    
    /** Returns the log's app-specific "details" field */
    public function GetDetails() : array { return $this->TryGetScalar('details') ?? array(); }
    
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
        $applog = $this->TryGetApplog();
        
        if ($applog !== null) $applog->SaveDetails();
        
        if (Main::GetInstance()->GetConfig()->GetEnableRequestLogDB())
            parent::Save(); // ignore $onlyMandatory
        
        // make sure the applog is saved also in case of rollback
        if ($applog !== null) $applog->Save();
        
        return $this;
    }

    public static function GetPropUsage(bool $withapp = true) : string { return RequestLog::GetPropUsage()." ".($withapp?"[--appname alphanum] ":'')."[--action alphanum]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input, bool $withapp = true) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($withapp && $input->HasParam('appname')) $criteria[] = $q->Equals("$table.app", $input->GetParam('appname',SafeParam::TYPE_ALPHANUM));
        
        if ($input->HasParam('action')) $criteria[] = $q->Equals("$table.obj_action", $input->GetParam('obj_action',SafeParam::TYPE_ALPHANUM));
        
        $q->Join($database, RequestLog::class, 'id', static::class, 'obj_request');
        
        return array_merge($criteria, RequestLog::GetPropCriteria($database, $q, $input));
    }

    /**
     * Returns the printable client object of this action log
     * @return array `{app:string, action:string}`
        if details, add: `{details:array}`
     */
    protected function GetBaseClientObject(bool $details = false) : array
    {
        $retval = array_map(function(FieldTypes\BaseField $e){
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
        
        $retval['request'] = $this->GetRequestLog()->GetClientObject();
        
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

        if ($applogs && ($applog = $this->TryGetApplog()) !== null)
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
