<?php declare(strict_types=1); namespace Andromeda\Core\Logging; if (!defined('Andromeda')) die();

use Andromeda\Core\{ApiPackage, Config};
use Andromeda\Core\Database\DBStats;
use Andromeda\Core\IOFormat\Output;

class MetricsHandler
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
    
    /** Creates and returns a new DBStats to be used for init_stats */
    public function GetInitStats() : DBStats
    {
        return $this->init_stats = new DBStats();
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
            $total_stats->Add($this->init_stats);
            
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
     * @throws Exceptions\MetricsTransactionException if already in a db transaction
     */
    public function SaveMetrics(ApiPackage $apipack, Output $output, bool $isError = false) : void
    {
        try // request should still succeed if this fails
        {
            if (!$apipack->GetMetricsLevel()) return;
            
            $db = $apipack->GetDatabase();
            
            // want to re-use DB, saving must be in its own transaction
            if ($db->GetInternal()->inTransaction())
                throw new Exceptions\MetricsTransactionException();
            
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