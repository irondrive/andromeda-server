<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, Utilities};
use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\{Input, SafeParams};

/** Log entry representing an API request */
class RequestLog extends BaseLog
{
    use TableTypes\TableNoChildren;
    
    /** Timestamp of the request */
    private FieldTypes\Timestamp $time;
    /** Interface address used for the request */
    private FieldTypes\StringType $addr;
    /** Interface user-agent used for the request */
    private FieldTypes\StringType $agent;
    /** Error code if response was an error (or null) */
    private FieldTypes\NullIntType $errcode;
    /** Error message if response was an error (or null) */
    private FieldTypes\NullStringType $errtext;
    
    private bool $writtenToFile = false;
    
    /** 
     * action metrics logged this request
     * @var array<ActionLog>
     */
    private array $actions;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->time = $fields[] =    new FieldTypes\Timestamp('time');
        $this->addr = $fields[] =    new FieldTypes\StringType('addr');
        $this->agent = $fields[] =   new FieldTypes\StringType('agent');
        $this->errcode = $fields[] = new FieldTypes\NullIntType('errcode');
        $this->errtext = $fields[] = new FieldTypes\NullStringType('errtext');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** Creates a new request log entry from the main API */
    public static function Create(ApiPackage $apipack) : self
    {
        $interface = $apipack->GetInterface();
        $database = $apipack->GetDatabase();
        
        $obj = static::BaseCreate($database);
        $obj->time->SetTimeNow();
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
            throw new Exceptions\LogAfterWriteException();
        
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
            throw new Exceptions\LogAfterWriteException();
        
        $this->actions ??= array();
        
        return $this->actions[] = $class::Create($this->database, $this, $input);
    }

    public function Save(bool $isRollback = false) : self
    {
        $this->actions ??= array();
        
        $config = $this->GetApiPackage()->GetConfig();
        
        if ($config->GetEnableRequestLogDB())
        {
            parent::Save(); // ignore isRollback
        }

        return $this;
    }
    
    /** 
     * Writes the log to the log file 
     * @throws Exceptions\MultiFileWriteException if called > once
     */
    public function WriteFile() : self
    {
        $config = $this->GetApiPackage()->GetConfig();

        if (!$this->writtenToFile && 
            $config->GetEnableRequestLogFile() &&
            ($logdir = $config->GetDataDir()) !== null)
        {
            $this->writtenToFile = true;
        
            $data = Utilities::JSONEncode($this->GetFullClientObject(true));
            file_put_contents("$logdir/access.log", $data."\r\n", FILE_APPEND);
        }
        
        return $this;
    }
   
    public static function GetPropUsage(bool $join = true) : string 
    { 
        return "[--mintime float] [--maxtime float] [--addr utf8] [--agent utf8] ".
               "[--errcode ?int32] [--errtext ?utf8] [--asc bool]".($join ? ' '.ActionLog::GetPropUsage(false):''); 
    }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $join = true) : array
    {       
        $criteria = array();
        
        if ($params->HasParam('maxtime')) $criteria[] = $q->LessThan("time", $params->GetParam('maxtime')->GetFloat());
        if ($params->HasParam('mintime')) $criteria[] = $q->GreaterThan("time", $params->GetParam('mintime')->GetFloat());
        
        if ($params->HasParam('addr')) $criteria[] = $q->Equals("addr", $params->GetParam('addr')->GetUTF8String());
        if ($params->HasParam('agent')) $criteria[] = $q->Like("agent", $params->GetParam('agent')->GetUTF8String());
        
        if ($params->HasParam('errcode')) $criteria[] = $q->Equals("errcode", $params->GetParam('errcode')->GetNullInt32());
        if ($params->HasParam('errtext')) $criteria[] = $q->Equals("errtext", $params->GetParam('errtext')->GetNullUTF8String());
        
        $q->OrderBy("time", !$params->GetOptParam('asc',false)->GetBool()); // always sort by time, default desc

        if (!$join) return $criteria;
        
        $q->Join($database, ActionLog::class, 'requestlog', self::class, 'id'); // enable loading by ActionLog criteria
        return array_merge($criteria, ActionLog::GetPropCriteria($database, $q, $params, false));
    }

    /**
     * Returns the printable client object of this request log
     * @return array<mixed> `{time:float, addr:string, agent:string}` 
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
     * @return array<mixed> RequestLog + if $actions, add `{actions:[ActionLog]}`
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
