<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;

require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Logging/ActionLog.php");
require_once(ROOT."/Core/Logging/BaseLog.php");

/** Exception indicating that it was requested to modify the log after it was written to disk */
class LogAfterWriteException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("REQLOG_AFTER_WRITE", $details);
    }
}

/** Exception indicating the log file cannot be written more than once */
class MultiFileWriteException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("LOG_FILE_MULTI_WRITE", $details);
    }
}

/** Log entry representing an API request */
final class RequestLog extends BaseLog
{
    use TableNoChildren;
    
    /** Timestamp of the request */
    private FieldTypes\Date $time;
    /** Interface address used for the request */
    private FieldTypes\StringType $addr;
    /** Interface user-agent used for the request */
    private FieldTypes\StringType $agent;
    /** Error code if response was an error (or null) */
    private FieldTypes\NullStringType $errcode;
    /** Error message if response was an error (or null) */
    private FieldTypes\NullStringType $errtext;
    
    private bool $writtenToFile = false;
    
    /** Array of action metrics if not saved */
    private array $actions;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->time = $fields[] =    new FieldTypes\Date('time');
        $this->addr = $fields[] =    new FieldTypes\StringType('addr');
        $this->agent = $fields[] =   new FieldTypes\StringType('agent');
        $this->errcode = $fields[] = new FieldTypes\NullStringType('errcode');
        $this->errtext = $fields[] = new FieldTypes\NullStringType('errtext');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Creates a new request log entry from the main API */
    public static function Create(Main $main) : self
    {
        $interface = $main->GetInterface();
        
        $obj = parent::BaseCreate($main->GetDatabase());
        $obj->addr->SetValue($interface->getAddress());
        $obj->agent->SetValue($interface->getUserAgent());
        
        return $obj;
    }
    
    /** Returns the time this request log was created */
    public function GetTime() : float { return $this->time->GetValue(); }
    
    /** Sets the given exception as the request result */
    public function SetError(\Throwable $e) : self
    {
        if ($this->writtenToFile) 
            throw new LogAfterWriteException();
        
        $this->errcode->SetValue($e->getCode());
        $this->errtext->SetValue($e->getMessage());
        
        return $this;
    }
    
    /** 
     * Creates an ActionLog for this request from the given action input 
     * @template T of ActionLog
     * @param Input $input action input
     * @param class-string<T> $class actionlog class
     * @return T
     */
    public function LogAction(Input $input, string $class) : ActionLog
    {
        if ($this->writtenToFile)
            throw new LogAfterWriteException();
        
        $this->actions ??= array();
        
        return $this->actions[] = $class::Create($this->database, $this, $input);
    }

    public function Save(bool $isRollback = false) : self
    {
        $config = Main::GetInstance()->GetConfig();
        
        if ($config->GetEnableRequestLogDB())
        {
            parent::Save(); // ignore isRollback
        }

        return $this;
    }
    
    /** 
     * Writes the log to the log file 
     * @throws MultiFileWriteException if called > once
     */
    public function WriteFile() : self
    {
        $config = Main::GetInstance()->GetConfig();

        if ($config->GetEnableRequestLogFile() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            if ($this->writtenToFile)
                throw new MultiFileWriteException();
            $this->writtenToFile = true;
        
            $data = Utilities::JSONEncode($this->GetFullClientObject(true));
            file_put_contents("$logdir/access.log", $data."\r\n", FILE_APPEND);
        }
        
        return $this;
    }
   
    public static function GetPropUsage(bool $join = true) : string 
    { 
        return "[--mintime float] [--maxtime float] [--addr utf8] [--agent utf8] ".
               "[--errcode utf8] [--errtext utf8] [--asc bool]".($join ? ' '.ActionLog::GetPropUsage(false):''); 
    }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $join = true) : array
    {       
        $criteria = array();
        
        if ($params->HasParam('maxtime')) $criteria[] = $q->LessThan("time", $params->GetParam('maxtime')->GetFloat());
        if ($params->HasParam('mintime')) $criteria[] = $q->GreaterThan("time", $params->GetParam('mintime')->GetFloat());
        
        if ($params->HasParam('addr')) $criteria[] = $q->Equals("addr", $params->GetParam('addr')->GetUTF8String());
        if ($params->HasParam('agent')) $criteria[] = $q->Like("agent", $params->GetParam('agent')->GetUTF8String());
        
        if ($params->HasParam('errcode')) $criteria[] = $q->Equals("errcode", $params->GetParam('errcode')->GetUTF8String());
        if ($params->HasParam('errtext')) $criteria[] = $q->Equals("errtext", $params->GetParam('errtext')->GetUTF8String());
        
        $q->OrderBy("time", !$params->GetOptParam('asc',false)->GetBool()); // always sort by time, default desc

        if (!$join) return $criteria;
        
        $q->Join($database, ActionLog::class, 'requestlog', self::class, 'id'); // enable loading by ActionLog criteria
        return array_merge($criteria, ActionLog::GetPropCriteria($database, $q, $params, false));
    }

    /**
     * Returns the printable client object of this request log
     * @return array `{time:float, addr:string, agent:string}` 
         if error, add: `{errcode:int, errtext:string}`
     */
    public function GetClientObject() : array
    {
        $retval = array
        (
            'time' => $this->time->GetValue(),
            'addr' => $this->addr->GetValue(),
            'agent' => $this->agent->GetValue()
        );
        
        if (($errcode = $this->errcode->TryGetValue()) !== null)
            $retval['errcode'] = $errcode;
        
        if (($errtext = $this->errtext->TryGetValue()) !== null)
            $retval['errtext'] = $errtext;

        return $retval;
    }
    
    /**
     * Returns the printable client object of this request log plus all request actions
     * @param bool $actions if true, add action logs
     * @see RequestLog::GetClientObject()
     * @see ActionLog::GetAppClientObject()
     * @return array RequestLog + if $actions, add `{actions:[ActionLog]}`
     */
    public function GetFullClientObject(bool $actions = false, bool $expand = false) : array
    {
        $retval = $this->GetClientObject();
        
        if ($actions) 
        {
            $retval['actions'] = array();
            
            $actlogs = $this->actions ?? ActionLog::LoadByRequest($this->database,$this);
            
            foreach ($actlogs as $actlog) 
                $retval['actions'][] = $actlog->GetClientObject($expand);   
        }
            
        return $retval;
    }
}
