<?php namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/Database/QueryBuilder.php");
require_once(ROOT."/Core/Database/ObjectDatabase.php");

class QueryBuilderTest extends \PHPUnit\Framework\TestCase
{
    public function testEscapeWildcards()
    {
        $this->assertSame('test\%test\_test', 
            QueryBuilder::EscapeWildcards('test%test_test'));   
    }
    
    protected function testQuery(QueryBuilder $q, array $data, string $value) : void
    {
        $this->assertSame($value, $q->GetText(), (string)$q);
        
        $keys = array_map(function($val){ return "d$val"; }, array_keys($data));
        
        $data = array_combine($keys, $data);        
        
        $this->assertSame($data, $q->GetData());
    }
    
    public function testBasic()
    {
        $q = new QueryBuilder(); $q->Where($q->IsNull('mykey'));        
        $this->testQuery($q, array(), "WHERE mykey IS NULL");        
        $this->assertSame("mykey IS NULL", $q->GetWhere());
        
        $q = new QueryBuilder(); $q->Where($q->LessThan('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey < :d0");
        
        $q = new QueryBuilder(); $q->Where($q->GreaterThan('mykey',5));
        $this->testQuery($q, array(5), "WHERE mykey > :d0");
        
        $q = new QueryBuilder(); $q->Where($q->IsTrue('mykey'));
        $this->testQuery($q, array(0), "WHERE mykey > :d0");        
        
        $q = new QueryBuilder(); $q->Where($q->Equals('mykey','myval'));
        $this->testQuery($q, array('myval'), "WHERE mykey = :d0");
        
        $q = new QueryBuilder(); $q->Where($q->NotEquals('mykey','myval'));
        $this->testQuery($q, array('myval'), "WHERE mykey != :d0");
    }
    
    public function testLike()
    {
        $q = new QueryBuilder(); $q->Where($q->Like('mykey','my%val\\'));
        $this->testQuery($q, array('%my\%val\\\\%'), "WHERE mykey LIKE :d0");
        
        $q = new QueryBuilder(); $q->Where($q->Like('mykey','myval%',true));
        $this->testQuery($q, array('myval%'), "WHERE mykey LIKE :d0");
    }
    
    public function testCombos()
    {
        $q = new QueryBuilder(); $q->Where($q->Not($q->Equals('mykey','myval')));
        $this->testQuery($q, array('myval'), "WHERE (NOT mykey = :d0)");
        
        $q = new QueryBuilder(); $q->Where($q->And($q->Equals('mykey1','myval1'), $q->Equals('mykey2','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 AND mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->Or($q->Equals('mykey1','myval1'), $q->Equals('mykey2','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 OR mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->ManyAnd(array('mykey1'=>'myval1','mykey2'=>'myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey1 = :d0 AND mykey2 = :d1)");
        
        $q = new QueryBuilder(); $q->Where($q->ManyOr('mykey',array('myval1','myval2')));
        $this->testQuery($q, array('myval1','myval2'), "WHERE (mykey = :d0 OR mykey = :d1)");
    }
    
    public function testSpecial()
    {
        $q = new QueryBuilder(); $q->Where($q->IsNull('mykey'))
            ->Limit(15)->Offset(10)->OrderBy('mykey',true);
        
        $this->testQuery($q, array(), "WHERE mykey IS NULL ORDER BY mykey DESC LIMIT 15 OFFSET 10");
    }
    
    public function testJoins()
    {
        $database = $this->createStub(ObjectDatabase::class);
        
        $database->method('GetClassTableName')->will($this->returnArgument(0));
        
        $database->method('SQLConcat')->will($this->returnCallback(
            function($a,$b){ return "$a+$b"; }));
        
        $q = new QueryBuilder(); $q->Join($database, 'tableA', 'propA', 'tableB', 'propB');
        $this->testQuery($q, array(), "JOIN tableA ON tableA.propA = tableB.propB");
        
        $q = new QueryBuilder(); $q->Join($database, 'tableA', 'propA', 'tableB', 'propB', 'name\\polyA', 'name\\polyB');
        $this->testQuery($q, array(':polyA',':polyB'), "JOIN tableA ON tableA.propA+:d0 = tableB.propB+:d1");
        
        $q = new QueryBuilder(); $q->SelfJoinWhere($database, 'tableA', 'propA', 'propB');
        $this->testQuery($q, array(), ", tableA _tmptable WHERE tableA.propA = _tmptable.propB");
    }
}
