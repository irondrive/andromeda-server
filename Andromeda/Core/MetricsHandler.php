<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/ApiPackage.php");

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{ObjectDatabase, DBStats};

require_once(ROOT."/Core/IOFormat/Output.php");
use Andromeda\Core\IOFormat\Output;

require_once(ROOT."/Core/Logging/RequestMetrics.php");
use Andromeda\Core\Logging\RequestMetrics;

final class MetricsHandler
{
    /** @var DBStats performance metrics for initialization */
    private DBStats $init_stats;
    
    /** @var DBStats total request performance metrics */
    private DBStats $total_stats;
    
    /** Creates a new MetricsHandler and starts timing */
    public function __construct()
    {
        $this->total_stats = new DBStats();
    }
    
    /** 
     * Pops a stats context off the DB and assigns it to init stats
     * @param ObjectDatabase $database the database being timed
     */
    public function EndInitStats(ObjectDatabase $database) : void
    {
        $this->init_stats = $database->GetInternal()->popStatsContext();
        
        $this->total_stats->Add($this->init_stats);
    }
    
    /**
     * Compiles performance metrics and adds them to the given output, and logs
     * @param ApiPackage $apipack API package with database
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     * @throws FinalizeTransactionException if already in a db transaction
     */
    public function SaveMetrics(ApiPackage $apipack, Output $output, bool $isError = false) : void
    {
        if (!$apipack->HasDatabase() || !($mlevel = $apipack->GetMetricsLevel())) return;
        
        $database = $apipack->GetDatabase();
        $apprunner = $apipack->GetAppRunner();
        
        try // request should still succeed if this fails
        {
            // saving metrics must be in its own transaction
            if ($database->GetInternal()->inTransaction())
                throw new FinalizeTransactionException();
                
            $actions = $apprunner->GetActionHistory();
            $commits = $apprunner->GetCommitStats();
        
            foreach ($actions as $context)
                $this->total_stats->Add($context->GetMetrics());
            
            foreach ($commits as $commit)
                $this->total_stats->Add($commit);
            
            $this->total_stats->stopTiming();
            
            $metrics = RequestMetrics::Create(
                $mlevel, $database, $apprunner->GetRequestLog(),
                $this->init_stats, $actions, $commits, $this->total_stats)->Save();
            
            if ($apipack->isCommitRollback())
                $database->rollback(); 
            else $database->commit();
        
            if ($apipack->GetMetricsLevel(true))
                $output->SetMetrics($metrics->GetClientObject($isError));
        }
        catch (\Throwable $e)
        {
            if ($apipack->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $apipack->GetErrorManager()->LogException($e, false);
        }
    }
}