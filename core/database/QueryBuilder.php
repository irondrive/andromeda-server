<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php");

/** Minimalistic class for building prepared post-FROM SQL query strings */
class QueryBuilder
{
    /** @var array<string, string> variables to be substituted in the query */
    private array $data = array();

    /** @see QueryBuilder::$data */
    public function GetData() : array { return $this->data; }
    
    /** Returns the compiled query as a string */
    public function GetText() : string 
    { 
        $query = $this->fromalias ?? "";
        
        if (count($this->joins)) $query .= implode(array_map(function($str){ 
            return " JOIN $str "; }, $this->joins));
        
        if ($this->where !== null) $query .= " WHERE ".$this->where;
        if ($this->orderby !== null) $query .= " ORDER BY ".$this->orderby;
        
        if ($this->orderdesc !== null) $query .= $this->orderdesc ? " DESC" : " ASC";
        
        if ($this->limit !== null) $query .= " LIMIT ".$this->limit;
        if ($this->offset !== null) $query .= " OFFSET ".$this->offset;
        
        return trim($query);
    }
    
    public function __toString() : string { return $this->GetText(); }
    
    private ?string $fromalias = null;
    private array $joins = array();
    private ?string $where = null;
    private ?string $orderby = null;
    private ?bool $orderdesc = null;
    private ?string $join = null;
    private ?int $limit = null;
    private ?int $offset = null;

    /**
     * Adds the given value to the internal data array
     * @param string $val the actual data value
     * @return string the placeholder to go in the query
     */
    protected function AddData($val) : string
    {
        $idx = "d".count($this->data);
        $this->data[$idx] = $val;
        return ":$idx";
    }
    
    /** Base function for safely comparing columns to values */
    private function BaseCompare(string $key, $val, string $symbol) : string 
    {
        return "$key $symbol ".$this->AddData($val);
    }    
    
    /** Returns the given string with escaped SQL wildcard characters */
    public static function EscapeWildcards(string $query) : string
    {
        return str_replace('%','\%',str_replace('_','\_',$query));
    }
    
    /** Returns a string asserting the given column is null */
    public function IsNull(string $key) : string { return "$key IS NULL"; }
    
    /**
     * Returns a string comparing the given column to a value using LIKE
     * @param string $key the name of the column to compare
     * @param string $val the value to check for
     * @param bool $hasMatch if true, the string manages its own SQL wildcard characters else use %$val%
     * @return string the built string
     */
    public function Like(string $key, string $val, bool $hasMatch = false) : string 
    {
        $val = str_replace('\\','\\\\',$val);
        if (!$hasMatch) $val = '%'.static::EscapeWildcards($val).'%';
        return $this->BaseCompare($key,$val,'LIKE'); 
    }
    
    /** Returns a query string asserting the given column is less than the given value */
    public function LessThan(string $key, $val) : string { return $this->BaseCompare($key,$val,'<'); }
    
    /** Returns a query string asserting the given column is greater than the given value */
    public function GreaterThan(string $key, $val) : string { return $this->BaseCompare($key,$val,'>'); }
    
    /** Returns a query string asserting the given column is "true" (greater than zero) */
    public function IsTrue(string $key) : string { return $this->GreaterThan($key,0); }
    
    /** Returns a query string asserting the given column is equal to the given value */
    public function Equals(string $key, $val) : string 
    { 
        if ($val === null) return $this->IsNull($key);
        return $this->BaseCompare($key,$val,'='); 
    }
    
    /** Returns a query string asserting the given column is not equal to the given value */
    public function NotEquals(string $key, $val) : string 
    { 
        if ($val === null) return $this->Not($this->IsNull($key));
        return $this->BaseCompare($key,$val,'!='); 
    }
    
    /** Returns a query string that inverts the logic of the given query */
    public function Not(string $arg) : string { return "(NOT $arg)"; }
    
    /** Returns a query string that combines the given arguments using OR */
    public function Or(string ...$args) : string { return "(".implode(' OR ',$args).")"; }
    
    /** Returns a query string that combines the given arguments using AND */
    public function And(string ...$args) : string { return "(".implode(' AND ',$args).")"; }

    /**
     * Syntactic sugar function to check many OR conditions at once
     * @param string $key the column to compare against
     * @param array $vals array of possible values for the column
     * @param string $func the function to use to check if the values match
     * @return string the built query string
     */
    public function ManyOr(string $key, array $vals, string $func='Equals') 
    { 
        return $this->Or(...array_map(function($val)use($key,$func){ 
            return $this->$func($key,$val); },$vals)); 
    }    
    
    /**
     * Syntactic sugar function to check many AND conditions at once
     * @param array $pairs associative array mapping column names to their desired values
     * @param string $func the function to use to check if the values match
     * @return string the built query string
     */
    public function ManyAnd(array $pairs, string $func='Equals') 
    {
        $retval = array(); foreach($pairs as $key=>$val){ 
            $retval[] = $this->$func($key, $val); }
        return $this->And(...$retval);
    }
    
    /** Assigns a WHERE clause to the query */
    public function Where(string $where) : self { $this->where = $where; return $this; }
    
    /** Returns the current WHERE string */
    public function GetWhere() : ?string { return $this->where; }
    
    /** Assigns an ORDER BY clause to the query */
    public function OrderBy(string $orderby, ?bool $desc = null) : self { 
        $this->orderby = $orderby; $this->orderdesc = $desc; return $this; }
    
    /** Assigns a LIMIT clause to the query */
    public function Limit(?int $limit) : self { if ($limit < 0) $limit = 0; $this->limit = $limit; return $this; }
    
    /** Assigns an OFFSET clause to the query (use with LIMIT) */
    public function Offset(?int $offset) : self { if ($offset < 0) $offset = 0; $this->offset = $offset; return $this; }
    
    /**
     * Adds a JOIN clause to the query (can have > 1)
     * @param ObjectDatabase $database reference to the database
     * @param string $joinclass the class of the objects that join us to the destination class
     * @param string $joinprop the column name of the join table that matches the destprop
     * @param string $destclass the class of the destination object
     * @param string $destprop the column name of the destination object that matches the joinprop
     * @param string $destpoly if not null, the destprop is a polymorphic reference to this class
     * @param string $joinpoly if not null, the joinprop is a polymorphic reference to this class
     * @return $this
     */
    public function Join(ObjectDatabase $database, string $joinclass, string $joinprop, string $destclass, string $destprop, ?string $destpoly = null, ?string $joinpoly = null) : self
    {
        $joinclass = $database->GetClassTableName($joinclass); 
        $destclass = $database->GetClassTableName($destclass);
        
        $joinstr = "$joinclass.$joinprop"; if ($destpoly !== null)
        {
            $classsym = $this->AddData(FieldTypes\ObjectPoly::GetIDTypeDBValue("",$destpoly));
            $joinstr = $database->SQLConcat($joinstr, $classsym);
        }
        
        $deststr = "$destclass.$destprop"; if ($joinpoly !== null)
        {
            $classsym = $this->AddData(FieldTypes\ObjectPoly::GetIDTypeDBValue("",$joinpoly));
            $deststr = $database->SQLConcat($deststr, $classsym);
        }
        
        $this->joins[] = "$joinclass ON $joinstr = $deststr"; return $this;
    }
    
    /**
     * Performs a self join on a table (selects an alias table and sets the WHERE query)
     * 
     * If you need to add extra WHERE to the query, you must add to GetWhere()
     * @param ObjectDatabase $database database reference
     * @param string $joinclass the table to join to itself 
     * @param string $prop1 the column to match to prop2
     * @param string $prop2 the column to match to prop1
     * @param string $tmptable the name of the temp table to join to (has prop2)
     * @return $this
     */
    public function SelfJoinWhere(ObjectDatabase $database, string $joinclass, string $prop1, string $prop2, string $tmptable = '_tmptable') : self
    {
        $joinclass = $database->GetClassTableName($joinclass); 
        
        $this->fromalias = ", $joinclass $tmptable";
        
        $this->where = "$joinclass.$prop1 = $tmptable.$prop2";
        
        return $this;
    }
}

