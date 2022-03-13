<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/DBStats.php"); use Andromeda\Core\Database\DBStats;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

/** Log entry representing DBStats */
trait CommonMetrics
{
    private FieldTypes\IntType $db_reads;
    private FieldTypes\FloatType $db_read_time;
    private FieldTypes\IntType $db_writes;
    private FieldTypes\FloatType $db_write_time;
    private FieldTypes\FloatType $code_time;
    private FieldTypes\FloatType $total_time; // TODO comments
    
    protected function CommonCreateFields() : void
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
    protected function CommonSetMetrics(DBStats $metrics) : self
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
     * @return array `{reads:int,read_time:float,writes:int,write_time:float,code_time:float,total_time:float}`
     * @see DBStats::getStats()
     */
    protected function GetCommonClientObject() : array
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
