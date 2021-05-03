<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/core/logging/ActionLog.php");
require_once(ROOT."/core/logging/BaseLog.php");

/** Log entry representing an API request */
class RequestLog extends BaseLog
{    
    public static function GetFieldTemplate() : array
    {
        return array(
            'actions' => new FieldTypes\ObjectRefs(ActionLog::class, 'request'),
            'time' => null,
            'addr' => null,
            'agent' => null,
            'errcode' => null,
            'errtext' => null,
        );
    }
    
    /** Creates a new request log entry from the main API */
    public static function Create(Main $main) : self
    {
        $interface = $main->GetInterface();
        
        return parent::BaseCreate($main->GetDatabase())
            ->SetScalar('time', $main->GetTime())
            ->SetScalar('addr', $interface->getAddress())
            ->SetScalar('agent', $interface->getUserAgent());
    }
    
    /** Sets the given exception as the request result */
    public function SetError(\Throwable $e) : self
    {
        return $this->SetScalar('errcode', $e->getCode())  
                    ->SetScalar('errtext', $e->getMessage());
    }
    
    /** Creates an ActionLog for this request from the given action input */
    public function LogAction(Input $input) : ActionLog
    {
        return ActionLog::Create($this->database, $this, $input);
    }
    
    /**
     * Saves this request log, either to DB or file (or both)
     * {@inheritDoc}
     * @see BaseObject::Save()
     */
    public function Save(bool $onlyMandatory = false) : self
    {
        $main = Main::GetInstance();
        
        // don't save the request log until the final commit
        if ($main->GetContext() !== null && !$onlyMandatory) return $this;
        
        $config = $main->GetConfig();
        
        if ($config->GetEnableRequestLogDB())
        {            
            parent::Save(); // ignore $onlyMandatory
            
            // make sure every action log is saved also in case of rollback
            foreach ($this->GetObjectRefs('actions') as $apprun) $apprun->Save();            
        }
        
        if ($config->GetEnableRequestLogFile() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            $data = $this->GetFullClientObject(false, true);
            
            // condense the text-version of the log a bit
            unset($data['id']); $data['actions'] = array_values($data['actions']);            
            foreach ($data['actions'] as &$action) unset($action['id']);
            
            $data = Utilities::JSONEncode($data);
            
            file_put_contents("$logdir/access.log", $data."\r\n", FILE_APPEND); 
        }

        return $this;
    }
        
    public static function GetPropUsage() : string { return "[--mintime float] [--maxtime float] [--addr raw] [--agent raw] [--errcode int] [--errtext alphanum] [--desc bool]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array
    {       
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($input->HasParam('maxtime')) $criteria[] = $q->LessThan("$table.time", $input->GetParam('maxtime',SafeParam::TYPE_FLOAT));
        if ($input->HasParam('mintime')) $criteria[] = $q->GreaterThan("$table.time", $input->GetParam('mintime',SafeParam::TYPE_FLOAT));
        
        if ($input->HasParam('addr')) $criteria[] = $q->Equals("$table.addr", $input->GetParam('addr',SafeParam::TYPE_TEXT));
        if ($input->HasParam('agent')) $criteria[] = $q->Like("$table.agent", $input->GetParam('agent',SafeParam::TYPE_TEXT));
        
        if ($input->HasParam('errcode')) $criteria[] = $q->Equals("$table.errcode", $input->GetParam('errcode',SafeParam::TYPE_INT));
        if ($input->HasParam('errtext')) $criteria[] = $q->Equals("$table.errtext", $input->GetParam('errtext',SafeParam::TYPE_INT));
        
        $q->OrderBy("$table.time", $input->GetOptParam('desc',SafeParam::TYPE_BOOL));
        
        return $criteria;
    }

    /**
     * Returns the printable client object of this request log
     * @return array `{time:int, addr:string, agent:string}` 
         if error, add: `{errcode:int, errtext:string}`
     */
    public function GetClientObject() : array
    {
        $retval = array_map(function(FieldTypes\Scalar $e){ 
            return $e->GetValue(); }, $this->scalars);
        
        if (!$retval['errcode']) unset($retval['errcode']);
        if (!$retval['errtext']) unset($retval['errtext']);
        
        return $retval;
    }
    
    /**
     * Returns the printable client object of this request log plus all request actions
     * @see RequestLog::GetClientObject()
     * @see ActionLog::GetAppClientObject()
     * @return array add `{actions:[ActionLog]}`
     */
    public function GetFullClientObject(bool $expand = false, bool $applogs = false) : array
    {
        $retval = $this->GetClientObject();
        
        $retval['actions'] = array_map(function(ActionLog $log)use($expand,$applogs){
            return $log->GetAppClientObject($expand,$applogs); }, $this->GetObjectRefs('actions'));
            
        return $retval;
    }
}
