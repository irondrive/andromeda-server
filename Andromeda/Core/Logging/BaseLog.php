<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

/** 
 * Base class for access logs, providing some common DB functions for user viewing 
 * 
 * The access log system starts with a RequestLog to represent a request. A request log
 * then creates one ActionLog for each app action run in the transaction.  An action log
 * can be accompanied by a BaseAppLog if the app implements it and has extra data to log.
 */
abstract class BaseLog extends BaseObject
{
    public const IDLength = 20;
    
    /** Returns the CLI usage string for loading objects by properties */
    public static abstract function GetPropUsage() : string;
    
    /**
     * Adds query filter parameters using the given input
     * 
     * MUST prefix column names with the appropriate table name
     * @param ObjectDatabase $database database reference
     * @param QueryBuilder $q query to create params with
     * @param Input $input input with user supplied criteria
     * @return array<string> array of WHERE strings
     */
    public static abstract function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array;
    
    /** Returns the common CLI usage for loading log entries */
    public static function GetLoadUsage() : string { return "[--logic and|or] [--limit uint] [--offset uint]"; }
    
    /** Returns the common CLI usage for counting log entries */
    public static function GetCountUsage() : string { return "[--logic and|or]"; }
    
    /**
     * Returns a compiled query selecting rows from the given input
     * @param ObjectDatabase $database database reference
     * @param Input $input input with user filter params
     * @return QueryBuilder built query with WHERE set
     */
    protected static function GetWhereQuery(ObjectDatabase $database, Input $input) : QueryBuilder
    {
        $q = new QueryBuilder(); $criteria = static::GetPropCriteria($database, $q, $input);
        
        $or = $input->GetOptParam('logic',SafeParam::TYPE_ALPHANUM,SafeParams::PARAMLOG_ONLYFULL,
            function($v){ return $v === 'and' || $v === 'or'; }) === 'or'; // default AND
            
        if (!count($criteria)) $criteria[] = ($or ? "FALSE" : "TRUE");
        
        return $q->Where($or ? $q->Or(...$criteria) : $q->And(...$criteria));
    }
    
    /**
     * Loads log entries the given input
     * @param ObjectDatabase $database database reference
     * @param Input $input user input with selectors
     * @return array<string, BaseLog> loaded log entries indexed by ID
     */
    public static function LoadByInput(ObjectDatabase $database, Input $input) : array
    {
        $q = static::GetWhereQuery($database, $input);
        
        $q->Limit($input->GetOptParam('limit',SafeParam::TYPE_UINT) ?? 1000);
        
        if ($input->HasParam('offset')) $q->Offset($input->GetParam('offset',SafeParam::TYPE_UINT));
        
        return static::LoadByQuery($database, $q);
    }
    
    /**
     * Counts log entries by the given input
     * @param ObjectDatabase $database database reference
     * @param Input $input user input with selectors
     * @return int number of log entries that match
     */
    public static function CountByInput(ObjectDatabase $database, Input $input) : int
    {
        return static::CountByQuery($database, static::GetWhereQuery($database, $input));
    }
}
