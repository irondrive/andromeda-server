<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php");

/** Minimalistic class for building prepared SQL query strings */
class QueryBuilder
{
    /** @var array<string, string> variables to be substituted in the query */
    private array $data = array();

    /** @see QueryBuilder::$data */
    public function GetData() : array { return $this->data; }
    
    /** Returns the compiled query as a string */
    public function GetText() : string 
    { 
        return ($this->joinstr ?? "").
               ($this->where!==null ?" WHERE ".$this->where:"").
               ($this->limit!==null ?" LIMIT ".$this->limit:"").
               ($this->offset!==null ?" OFFSET ".$this->offset:"").
               ($this->orderby!==null ?" ORDER BY ".$this->orderby:"");
    }
    
    private ?string $where = null;
    private ?string $orderby = null;
    private ?string $join = null;
    private ?int $limit = null;
    private ?int $offset = null;

    /**
     * Adds the given value to the internal data array
     * @param string $val the actual data value
     * @return string the placeholder to go in the query
     */
    private function AddData(string $val) : string
    {
        $idx = "d".count($this->data);
        $this->data[$idx] = $val;
        return ":$idx";
    }
    
    /** Base function for safely comparing columns to values */
    private function BaseCompare(string $key, string $val, string $symbol) : string 
    {
        return "$key $symbol ".$this->AddData($val);
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
        if (!$hasMatch) $val = "%$val%";
        return $this->BaseCompare($key,$val,'LIKE'); 
    }
    
    /** Returns a query string asserting the given column is less than the given value */
    public function LessThan(string $key, string $val) : string { return $this->BaseCompare($key,$val,'<'); }
    
    /** Returns a query string asserting the given column is greater than the given value */
    public function GreaterThan(string $key, string $val) : string { return $this->BaseCompare($key,$val,'>'); }
    
    /** Returns a query string asserting the given column is "true" (greater than zero) */
    public function IsTrue(string $key) : string { return $this->GreaterThan($key,0); }
    
    /** Returns a query string asserting the given column is equal to the given value */
    public function Equals(string $key, ?string $val) : string 
    { 
        if ($val === null) return $this->IsNull($key);
        return $this->BaseCompare($key,$val,'='); 
    }
    
    /** Returns a query string asserting the given column is not equal to the given value */
    public function NotEquals(string $key, ?string $val) : string 
    { 
        if ($val === null) return $this->Not($this->IsNull($key));
        return $this->BaseCompare($key,$val,'!='); 
    }
    
    /** Returns a query string that inverts the logic of the given query */
    public function Not(string $arg) : string { return "(NOT $arg)"; }
    
    /** Returns a query string that combines the given array of arguments using OR */
    public function OrArr(array $args) : string { return "(".implode(' OR ',$args).")"; }
    
    /** Returns a query string that combines the given array of arguments using AND */
    public function AndArr(array $args) : string { return "(".implode(' AND ',$args).")"; }
    
    /** Returns a query string that combines the given arguments using OR */
    public function Or(string ...$args) : string { return $this->OrArr($args); }
    
    /** Returns a query string that combines the given arguments using AND */
    public function And(string ...$args) : string { return $this->AndArr($args); }

    /**
     * Syntactic sugar function to check many OR conditions at once
     * @param string $key the column to compare against
     * @param array $vals array of possible values for the column
     * @param string $func the function to use to check if the values match
     * @return string the built query string
     */
    public function ManyOr(string $key, array $vals, string $func='Equals') 
    { 
        return $this->OrArr(array_map(function($val)use($key,$func){ 
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
            array_push($retval, $this->$func($key, $val)); }
        return $this->AndArr($retval);
    }
    
    /** Assigns a WHERE clause to the query */
    public function Where(string $where) : self { $this->where = $where; return $this; }
    
    /** Assigns an ORDER BY clause to the query */
    public function OrderBy(string $orderby) : self { $this->orderby = $orderby; return $this; }
    
    /** Assigns a LIMIT clause to the query */
    public function Limit(?int $limit) : self { if ($limit < 0) $limit = 0; $this->limit = $limit; return $this; }
    
    /** Assigns an OFFSET clause to the query (use with LIMIT) */
    public function Offset(?int $offset) : self { if ($offset < 0) $offset = 0; $this->offset = $offset; return $this; }
    
    /**
     * Assigns a JOIN clause to the query
     * @param ObjectDatabase $database reference to the database
     * @param string $joinclass the class of the objects that join us to the destination class
     * @param string $joinprop the column name of the join table that matches the destprop
     * @param string $destclass the class of the destination object
     * @param string $destprop the column name of the destination object that matches the joinprop
     * @param string $destpoly if not null, the destprop is a polymorphic reference matching this class
     * @return $this
     */
    public function Join(ObjectDatabase $database, string $joinclass, string $joinprop, string $destclass, string $destprop, ?string $destpoly = null) : self
    {
        $joinclass = $database->GetClassTableName($joinclass); $destclass = $database->GetClassTableName($destclass);
        
        $joinstr = "$joinclass.$joinprop"; if ($destpoly !== null)
        {
            $classsym = $this->AddData(FieldTypes\ObjectPoly::GetIDTypeDBValue("",$destpoly));
            $joinstr = $database->SQLConcat($joinstr, $classsym);
        }
        
        $this->joinstr = "JOIN $joinclass ON $joinstr = $destclass.$destprop"; return $this;
    }
}

