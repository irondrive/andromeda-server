<?php declare(strict_types=1); namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    public function testEscapeWildcards() : void
    {
        $this->assertSame('test\%test\_test', 
            QueryBuilder::EscapeWildcards('test%test_test'));   
    }
    
    /**
     * @param QueryBuilder $q
     * @param array<mixed> $data
     * @param string $value
     */
    protected function testQuery(QueryBuilder $q, array $data, string $value) : void
    {
        $this->assertSame($value, $q->GetText(), (string)$q);
        
        $keys = array_map(function(int $val){ return "d$val"; }, array_keys($data));
        
        $data = array_combine($keys, $data);        
        
        $this->assertSame($data, $q->GetData());
    }
    
    public function testBasic() : void
    {
        $q = new QueryBuilder(); $q->Where($q->IsNull('mykey'));        
        $this->testQuery($q, array(), "WHERE mykey IS NULL");        
        $this->assertSame("mykey IS NULL", $q->GetWhere());
        
        $q = new QueryBuilder(); $q->Where($q->LessThan('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey < :d0");
        
        $q = new QueryBuilder(); $q->Where($q->LessThanEquals('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey <= :d0");
        
        $q = new QueryBuilder(); $q->Where($q->GreaterThan('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey > :d0");
        
        $q = new QueryBuilder(); $q->Where($q->GreaterThanEquals('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey >= :d0");
        
        $q = new QueryBuilder(); $q->Where($q->IsTrue('mykey'));
        $this->testQuery($q, array(0), "WHERE mykey > :d0");        
        
        $q = new QueryBuilder(); $q->Where($q->Equals('mykey','myval'));
        $this->testQuery($q, array('myval'), "WHERE mykey = :d0");
        
        $q = new QueryBuilder(); $q->Where($q->NotEquals('mykey','myval'));
        $this->testQuery($q, array('myval'), "WHERE mykey <> :d0");
    }
    
    public function testLike() : void
    {
        $q = new QueryBuilder(); $q->Where($q->Like('mykey','my%val\\'));
        $this->testQuery($q, array('%my\%val\\\\%'), "WHERE mykey LIKE :d0");
        
        $q = new QueryBuilder(); $q->Where($q->Like('mykey','myval%',true));
        $this->testQuery($q, array('myval%'), "WHERE mykey LIKE :d0");
    }
    
    public function testCombos() : void
    {
        $q = new QueryBuilder(); $q->Where($q->Not($q->Equals('mykey','myval')));
        $this->testQuery($q, array('myval'), "WHERE (NOT mykey = :d0)");
        
        $q = new QueryBuilder(); $q->Where($q->And($q->Equals('mykey1','myval1'), $q->Equals('mykey2','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 AND mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->Or($q->Equals('mykey1','myval1'), $q->Equals('mykey2','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 OR mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->ManyEqualsAnd(array('mykey1'=>'myval1','mykey2'=>'myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 AND mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->ManyEqualsOr('mykey',array('myval1','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey = :d0 OR mykey = :d1)");
    }
    
    public function testAutoWhereAnd() : void
    {
        $q = new QueryBuilder(); $q->Where($q->Equals('a',3))->Where($q->Equals('b',4));
        $this->testQuery($q, array(3,4), "WHERE (a = :d0 AND b = :d1)");
    }
    
    public function testSpecial() : void
    {
        $q = new QueryBuilder(); $q->Where($q->IsNull('mykey'))
            ->Limit(15)->Offset(10)->OrderBy('mykey',true);
        
        $this->assertSame(15, $q->GetLimit());
        $this->assertSame(10, $q->GetOffset());
        
        $this->testQuery($q, array(), "WHERE mykey IS NULL ORDER BY mykey DESC LIMIT 15 OFFSET 10");
    }
    
    public function testJoins() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $tableA = $objdb->GetClassTableName(EasyObject::class);
        $tableB = $objdb->GetClassTableName(PolyObject0::class);
        
        $q = new QueryBuilder(); $q->Join($objdb, EasyObject::class, 'propA', PolyObject0::class, 'propB');
        $this->testQuery($q, array(), "JOIN $tableA ON $tableA.propA = $tableB.propB");
        
        $q = new QueryBuilder(); $q->SelfJoinWhere($objdb, EasyObject::class, 'propA', 'propB');
        $this->testQuery($q, array(), ", $tableA _tmptable WHERE $tableA.propA = _tmptable.propB");
    }
}
