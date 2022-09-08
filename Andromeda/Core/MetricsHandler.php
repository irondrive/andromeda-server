<?php declare(strict_types=1); namespace Andromeda\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Database/DBStats.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");
use Andromeda\Core\Database\{ObjectDatabase, DBStats};

require_once(ROOT."/Core/IOFormat/Output.php");
use Andromeda\Core\IOFormat\Output;

require_once(ROOT."/Core/Logging/RequestMetrics.php");
use Andromeda\Core\Logging\RequestMetrics;

final class MetricsHandler
{
    /** performance metrics for initialization */
    private DBStats $init_stats;
    
    /** total request performance metrics */
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
        $dbstats = $database->GetInternal()->popStatsContext();
        if ($dbstats === null) throw new MissingMetricsException();
        
        $this->total_stats->Add($this->init_stats = $dbstats);
    }
    
    /**
     * Compiles performance metrics and adds them to the given output
     * @param ApiPackage $apipack API package reference
     * @param Output $output the output object to add metrics to
     * @param bool $isError if true, the output is an error response
     * @return ?RequestMetrics created RequestMetrics object
     */
    public function GetMetrics(ApiPackage $apipack, Output $output, bool $isError = false) : ?RequestMetrics
    {
        try // request should still succeed if this fails
        {
            if (!($mlevel = $apipack->GetMetricsLevel())) return null;
            
            $database = $apipack->GetDatabase();
            $apprunner = $apipack->GetAppRunner();
            
            $total_stats = clone $this->total_stats;
            
            $actions = $apprunner->GetActionHistory();
            $commits = $apprunner->GetCommitStats();
            
            foreach ($actions as $context)
                $total_stats->Add($context->GetMetrics());
            
            foreach ($commits as $commit)
                $this->total_stats->Add($commit);
            
            $total_stats->stopTiming();
            
            $metrics = RequestMetrics::Create(
                $mlevel, $database, $apprunner->GetRequestLog(),
                $this->init_stats, $actions, $commits, $total_stats);

            if ($apipack->GetMetricsLevel(true))
                $output->SetMetrics($metrics->GetClientObject($isError));
            
            return $metrics;
        }
        catch (\Throwable $e)
        {
            if ($apipack->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $apipack->GetErrorManager()->LogException($e, false); return null;
        }
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
        try // request should still succeed if this fails
        {
            if (!$apipack->GetMetricsLevel()) return;
            
            $db = $apipack->GetDatabase();
            
            // want to re-use DB, saving must be in its own transaction
            if ($db->GetInternal()->inTransaction())
                throw new FinalizeTransactionException();
            
            // TODO create new objdb from existing internal db? although db->time will be wrong...
            // then objectDB can be more assertive about commitAfterRollback + unset internal DB reference on rollback
                
            $metrics = $this->GetMetrics($apipack, $output, $isError);
             
            if ($metrics === null) return; else $metrics->Save();
            
            if ($apipack->isCommitRollback()) $db->rollback(); else $db->commit();
        }
        catch (\Throwable $e)
        {
            if ($apipack->GetDebugLevel() >= Config::ERRLOG_DETAILS) throw $e;
            else $apipack->GetErrorManager()->LogException($e, false);
        }
    }
}