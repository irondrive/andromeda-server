<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

/** Log entry representing DBStats */
trait DBStatsLog
{
    /** The number of read queries */
    private FieldTypes\IntType $db_reads;
    /** Time spent on read queries */
    private FieldTypes\FloatType $db_read_time;
    /** The number of write queries */
    private FieldTypes\IntType $db_writes;
    /** Time spent on write queries */
    private FieldTypes\FloatType $db_write_time;
    /** Time spent on PHP code (non-query) */
    private FieldTypes\FloatType $code_time;
    /** Total time measured by the DBStats */
    private FieldTypes\FloatType $total_time;
    
    protected function DBStatsCreateFields() : void
    {
        $fields = array();

        $this->db_reads = $fields[] =      new FieldTypes\IntType('db_reads');
        $this->db_read_time = $fields[] =  new FieldTypes\FloatType('db_read_time');
        $this->db_writes = $fields[] =     new FieldTypes\IntType('db_writes');
        $this->db_write_time = $fields[] = new FieldTypes\FloatType('db_write_time');
        $this->code_time = $fields[] =     new FieldTypes\FloatType('code_time');
        $this->total_time = $fields[] =    new FieldTypes\FloatType('total_time');
        
        $this->RegisterFields($fields, self::class);
    }
    
    /**
     * Set metrics from a DBStats
     * @param DBStats $metrics stats
     */
    protected function SetDBStats(DBStats $metrics) : self
    {
        $this->db_reads->SetValue($metrics->GetReads());
        $this->db_read_time->SetValue($metrics->GetReadTime());
        $this->db_writes->SetValue($metrics->GetWrites());
        $this->db_write_time->SetValue($metrics->GetWriteTime());
        $this->code_time->SetValue($metrics->GetCodeTime());
        $this->total_time->SetValue($metrics->GetTotalTime());
        
        return $this;
    }
    
    /**
     * Gets the printable client object for this object
     * @return array<mixed> `{reads:int,read_time:float,writes:int,write_time:float,code_time:float,total_time:float}`
     * @see DBStats::getStats()
     */
    protected function GetDBStatsClientObject() : array
    {
        return array(
            'reads' =>      $this->db_reads->GetValue(),
            'read_time' =>  $this->db_read_time->GetValue(),
            'writes' =>     $this->db_writes->GetValue(),
            'write_time' => $this->db_write_time->GetValue(),
            'code_time' =>  $this->code_time->GetValue(),
            'total_time' => $this->total_time->GetValue()
        );
    }
}
