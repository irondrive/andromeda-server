<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php");

class QueryBuilder
{
    public function __construct(){ $this->data = array(); }
    public function GetData() : array { return $this->data; }
    
    public function GetText() : string 
    { 
        return ($this->joinstr ?? "").
               ($this->where?" WHERE ".$this->where:"").
               ($this->limit?" LIMIT ".$this->limit:"").
               ($this->offset?" OFFSET ".$this->offset:"").
               ($this->orderby?" ORDER BY ".$this->orderby:"");
    }
    
    private ?string $where = null;
    private ?string $orderby = null;
    private ?string $join = null;
    private ?int $limit = null;
    private ?int $offset = null;
    
    public function IsNull(string $key) : string { return "$key IS NULL"; }
    
    private function BaseCompare(string $key, string $val, string $symbol) : string 
    { 
        $idx = "d".count($this->data); 
        $this->data[$idx] = $val; 
        return "$key $symbol :$idx";
    }    
    
    public function Like(string $key, string $val) : string { return $this->BaseCompare($key,"%$val%",'LIKE'); }    
    public function LessThan(string $key, string $val) : string { return $this->BaseCompare($key,$val,'<'); }
    public function GreaterThan(string $key, string $val) : string { return $this->BaseCompare($key,$val,'>'); }
    
    public function IsTrue(string $key) : string { return $this->GreaterThan($key,0); }
    
    public function Equals(string $key, ?string $val) : string 
    { 
        if ($val === null) return $this->IsNull($key);
        return $this->BaseCompare($key,$val,'='); 
    }
    
    public function NotEquals(string $key, ?string $val) : string 
    { 
        if ($val === null) return $this->Not($this->IsNull($key));
        return $this->BaseCompare($key,$val,'!='); 
    }
    
    public function Not(string $arg) : string { return "(NOT $arg)"; }
    public function OrArr(array $args) : string { return "(".implode(' OR ',$args).")"; }
    public function AndArr(array $args) : string { return "(".implode(' AND ',$args).")"; }
    
    public function Or(string ...$args) : string { return $this->OrArr($args); }
    public function And(string ...$args) : string { return $this->AndArr($args); }

    public function ManyOr(string $key, array $vals, string $func='Equals') 
    { 
        return $this->OrArr(array_map(function($val)use($key,$func){ 
            return $this->$func($key,$val); },$vals)); 
    }    
    
    public function ManyAnd(array $pairs, string $func='Equals') 
    {
        $retval = array(); foreach($pairs as $key=>$val){ 
            array_push($retval, $this->$func($key, $val)); }
        return $this->AndArr($retval);
    }
    
    public function Where(string $where) : self { $this->where = $where; return $this; }
    public function OrderBy(string $orderby) : self { $this->orderby = $orderby; return $this; }
    
    public function Limit(?int $limit) : self { if ($limit < 0) $limit = 0; $this->limit = $limit; return $this; }
    public function Offset(?int $offset) : self { if ($offset < 0) $offset = 0; $this->offset = $offset; return $this; }
    
    public function Join(ObjectDatabase $database, string $myclass, string $myprop, string $destclass, string $destprop) : self
    {
        $myclass = $database->GetClassTableName($myclass); $destclass = $database->GetClassTableName($destclass);
        $this->joinstr = " JOIN $myclass ON $myclass.$myprop = $destclass.$destprop "; return $this;
    }
}

