<?php namespace Andromeda\Core\Logging; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

/** 
 * Base class for access logs, providing some common DB functions for user viewing 
 * 
 * The access log system starts with a RequestLog to represent a request. A request log
 * then creates one ActionLog for each app action run in the transaction.  The action log
 * can be extended by an app-specific action log if it has extra data to log.
 */
abstract class BaseLog extends BaseObject
{
    protected const IDLength = 20;
    
    /** Returns the CLI usage string for loading objects by properties */
    public static abstract function GetPropUsage() : string;
    
    /**
     * Adds query filter parameters using the given input
     * 
     * MUST prefix column names with the appropriate table name
     * @param ObjectDatabase $database database reference
     * @param QueryBuilder $q query to create params with
     * @param SafeParams $params params with user supplied criteria
     * @return array<string> array of WHERE strings
     */
    public static abstract function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params) : array;
    
    /**
     * Returns the class we should load logs as
     * @param SafeParams $params input to determine class
     * @return class-string<static>
     */
    protected static function GetPropClass(SafeParams $params) : string { return static::class; }
    
    /** Returns the common CLI usage for loading log entries */
    public static function GetLoadUsage() : string { return "[--logic and|or] [--limit uint] [--offset uint]"; }
    
    /** Returns the common CLI usage for counting log entries */
    public static function GetCountUsage() : string { return "[--logic and|or]"; }
    
    /**
     * Returns a compiled query selecting rows from the given input
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params params with user filter params
     * @return QueryBuilder built query with WHERE set
     */
    protected static function GetWhereQuery(ObjectDatabase $database, SafeParams $params) : QueryBuilder
    {
        $q = new QueryBuilder(); $criteria = static::GetPropCriteria($database, $q, $params);
        
        $or = ($params->HasParam('logic') ? $params->GetParam('logic', SafeParams::PARAMLOG_ONLYFULL)
            ->FromWhitelist(array('and','or')) : null) === 'or'; // default AND
        
        if (!count($criteria))
        {
            if ($or) $q->Where("FALSE"); return $q; // match nothing
        }
        else return $q->Where($or ? $q->Or(...$criteria) : $q->And(...$criteria));
    }
    
    /**
     * Loads log entries the given input
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params user input with selectors
     * @return array<string, static> loaded log entries indexed by ID
     */
    public static function LoadByParams(ObjectDatabase $database, SafeParams $params) : array
    {
        $class = static::GetPropClass($params);
        
        $q = $class::GetWhereQuery($database, $params);
        
        $q->Limit($params->GetOptParam('limit',100)->GetUint());
        
        if ($params->HasParam('offset')) $q->Offset($params->GetParam('offset')->GetUint());
        
        return $database->LoadObjectsByQuery($class, $q);
    }
    
    /**
     * Counts log entries by the given input
     * @param ObjectDatabase $database database reference
     * @param SafeParams $params user input with selectors
     * @return int number of log entries that match
     */
    public static function CountByParams(ObjectDatabase $database, SafeParams $params) : int
    {
        $class = static::GetPropClass($params);
        
        $q = static::GetWhereQuery($database, $params);
        
        return $database->CountObjectsByQuery($class, $q);
    }
}
