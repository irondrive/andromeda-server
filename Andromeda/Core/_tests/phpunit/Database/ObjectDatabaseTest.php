<?php namespace Andromeda\Core\Database; 

require_once("init.php");

require_once(ROOT."/Core/_tests/phpunit/Database/testObjects.php");

require_once(ROOT."/Core/Database/ObjectDatabase.php");

class ObjectDatabaseTest extends \PHPUnit\Framework\TestCase
{
    public function testGetClassTable() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $this->assertSame(
            'a2obj_core_database_polyobject1',
            $objdb->GetClassTableName(PolyObject1::class));
        
        $database = $this->createMock(Database::class);
        $database->method('UsePublicSchema')->willReturn(true);
        $objdb = new ObjectDatabase($database);
        
        $this->assertSame(
            'public.a2obj_core_database_polyobject1',
            $objdb->GetClassTableName(PolyObject1::class));
    }

    private const select1 = array(
        'SELECT * FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
        'WHERE testprop1 > :d0', array('d0'=>3));
        
    private const select2 = array(
        'SELECT * FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1)', array('d0'=>3, 'd1'=>18));
    
    private const select3 = array(
        'SELECT * FROM a2obj_core_database_polyobject1 '.
        'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
        'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
        'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1)', array('d0'=>3, 'd1'=>5));
    
    private const id1 = 'testid1234';
    private const id2 = 'testid4567';
    
    private const row1 = array(
        /*1*/'id'=>self::id1,'testprop1'=>5,'type'=>27, /** @phpstan-ignore-line */ 
        /*2*/'id'=>self::id1,'testprop15'=>15,'type'=>27,
        /*4*/'id'=>self::id1,'testprop4'=>41,'type'=>13, 
        /*5*/'id'=>self::id1,'testprop5'=>7,'type'=>101);
    
    private const row2 = array(
        /*1*/'id'=>self::id2,'testprop1'=>10,'type'=>27, /** @phpstan-ignore-line */ 
        /*2*/'id'=>self::id2,'testprop15'=>16,'type'=>27, 
        /*4*/'id'=>self::id2,'testprop4'=>42,'type'=>18);

    public function testLoadByQuery() : void
    {
        // loading by every base class should yield the same results!
        foreach (array(PolyObject0::class, PolyObject1::class, PolyObject2::class, PolyObject3::class, PolyObject4::class) as $class)
        {
            $database = $this->createMock(Database::class);
            $objdb = new ObjectDatabase($database);
            
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));

            $database->expects($this->exactly(3))->method('read')
                ->withConsecutive(self::select1, self::select2, self::select3)
                ->willReturnOnConsecutiveCalls([self::row1], [self::row2], []);
                
            $objs = $objdb->LoadObjectsByQuery($class, $q);
            
            $this->assertSame(2, count($objs));
            $id1 = self::id1; $obj1 = $objs[$id1];
            $id2 = self::id2; $obj2 = $objs[$id2];
            
            $this->assertInstanceof(PolyObject5a::class, $obj1);
            assert($obj1 instanceof PolyObject5a);
            
            $this->assertSame($id1, $obj1->ID());
            $this->assertSame(5, $obj1->GetTestProp1());
            $this->assertSame(15, $obj1->GetTestProp15());
            $this->assertSame(41, $obj1->GetTestProp4());
            $this->assertSame(7, $obj1->GetTestProp5());
            
            $this->assertInstanceof(PolyObject5b::class, $obj2);
            assert($obj2 instanceof PolyObject5b);
            
            $this->assertSame($id2, $obj2->ID());
            $this->assertSame(10, $obj2->GetTestProp1());
            $this->assertSame(16, $obj2->GetTestProp15());
            $this->assertSame(42, $obj2->GetTestProp4());
        }
    }

    public function testObjectIdentity() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $id = 'testid5678';
        
        $qstr = 'SELECT * FROM a2obj_core_database_easyobject ';
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                [ $qstr, array() ],
                [ $qstr, array() ]
                )
            ->willReturnOnConsecutiveCalls(
                array(array('id'=>$id)),
                array(array('id'=>$id))
            );
        
        $objs = $objdb->LoadObjectsByQuery(EasyObject::class, $q);
        $this->assertSame(1, count($objs)); $obj = $objs[$id];
        
        $objs2 = $objdb->LoadObjectsByQuery(EasyObject::class, $q);
        $this->assertSame(1, count($objs2)); $obj2 = $objs2[$id];
        
        // Test that loading the same object twice does not reconstruct it
        $this->assertSame($obj, $obj2);
    }

    public function testCountByQuery() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $q = new QueryBuilder(); $q->Where($q->Equals('mytest',5));
        
        $qstr1 = 'SELECT COUNT(a2obj_core_database_polyobject1.id) FROM a2obj_core_database_polyobject1 WHERE mytest = :d0';
        $qstr2 = 'SELECT COUNT(a2obj_core_database_polyobject2.id) FROM a2obj_core_database_polyobject2 WHERE mytest = :d0';
        $qstr4 = 'SELECT COUNT(a2obj_core_database_polyobject4.id) FROM a2obj_core_database_polyobject4 WHERE mytest = :d0';
        $qstr5a = 'SELECT COUNT(a2obj_core_database_polyobject5a.id) FROM a2obj_core_database_polyobject5a WHERE mytest = :d0';
        $qstr5b = 'SELECT COUNT(a2obj_core_database_polyobject4.id) FROM a2obj_core_database_polyobject4 WHERE (mytest = :d0 AND a2obj_core_database_polyobject4.type = :d1)';
        
        $database->expects($this->exactly(7))->method('read')
            ->withConsecutive(
                [ $qstr1, array('d0'=>5) ],
                [ $qstr1, array('d0'=>5) ],
                [ $qstr2, array('d0'=>5) ],
                [ $qstr4, array('d0'=>5) ],
                [ $qstr4, array('d0'=>5) ],
                [ $qstr5a, array('d0'=>5) ],
                [ $qstr5b, array('d0'=>5,'d1'=>18) ],
             )
            ->willReturnOnConsecutiveCalls(
                array(array('COUNT(a2obj_core_database_polyobject1.id)'=>1)), // count PolyObject0
                array(array('COUNT(a2obj_core_database_polyobject1.id)'=>1)), // count PolyObject1
                array(array('COUNT(a2obj_core_database_polyobject2.id)'=>1)), // count PolyObject2
                array(array('COUNT(a2obj_core_database_polyobject4.id)'=>1)), // count PolyObject3
                array(array('COUNT(a2obj_core_database_polyobject4.id)'=>1)), // count PolyObject4
                array(array('COUNT(a2obj_core_database_polyobject5a.id)'=>1)), // count PolyObject5a
                array(array('COUNT(a2obj_core_database_polyobject4.id)'=>1)), // count PolyObject5b
             );
            
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject0::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject1::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject2::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject3::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject4::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject5a::class, $q));
        $this->assertSame(1, $objdb->CountObjectsByQuery(PolyObject5b::class, $q));
    }
    
    public function testInsertObject() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = PolyObject5a::Create($objdb);
        
        $id = $obj->ID();
        $obj->SetTestProp15(15); // part of 2's table!
        $obj->SetTestProp4(100); 
        $obj->SetTestProp4n(null); // set null
        $obj->DeltaTestProp4c(5); // counter
        
        $database->expects($this->exactly(4))->method('write')
            ->withConsecutive(
                [ 'INSERT INTO a2obj_core_database_polyobject1 (id) VALUES (:d0)', array('d0'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 (testprop15,id) VALUES (:d0,:d1)', array('d0'=>15,'d1'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 (type,testprop4,testprop4n,testprop4c,id) VALUES (:d0,:d1,NULL,:d2,:d3)', array('d0'=>13,'d1'=>100,'d2'=>5,'d3'=>$id) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a (type,id) VALUES (:d0,:d1)', array('d0'=>100,'d1'=>$id) ]
            )->willReturn(1);
        
        $obj->Save();
    }
    
    public function testUpdateObject() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = new PolyObject5a($objdb, array('id'=>($id='testid123'),'testprop1'=>5,'testprop4'=>6,'testprop4n'=>6,'testprop5'=>7));
        
        $database->expects($this->exactly(3))->method('write')
            ->withConsecutive(
                [ 'UPDATE a2obj_core_database_polyobject5a SET testprop5=:d0 WHERE id=:id', array('d0'=>11,'id'=>$id) ],
                [ 'UPDATE a2obj_core_database_polyobject4 SET testprop4=:d0, testprop4n=NULL, testprop4c=testprop4c+:d1 WHERE id=:id', array('d0'=>10,'d1'=>23,'id'=>$id) ],
                [ 'UPDATE a2obj_core_database_polyobject2 SET testprop15=:d0 WHERE id=:id', array('d0'=>15,'id'=>$id) ],
            )->willReturn(1);
            
        $obj->Save(); // nothing
        $obj->SetTestProp15(15); // 2's table
        $obj->SetTestProp4(10);
        $obj->SetTestProp4n(null); // set NULL
        $obj->DeltaTestProp4c(23); // counter +=
        $obj->SetTestProp5(11);
        $obj->Save();
        $obj->Save(); // nothing
    }
    
    public function testSaveAllObjects() : void
    {   
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj1 = PolyObject5a::Create($objdb); $id1 = $obj1->ID();
        $obj2 = new PolyObject5a($objdb, array('id'=>($id2='testid1234'),'testprop1'=>5,'testprop5'=>7)); $obj2->SetTestProp1(55);
        $obj3 = new PolyObject5a($objdb, array('id'=>($id3='testid5678'),'testprop1'=>15,'testprop5'=>17)); $obj3->SetTestProp1(65);
        $obj4 = PolyObject5ab::Create($objdb); $id4 = $obj4->ID();
        $obj5 = new PolyObject5a($objdb, array('id'=>($id5='testid9999'),'testprop1'=>15,'testprop5'=>17)); $obj5->DeltaTestProp4c(10);
        
        $database->expects($this->exactly(11))->method('write')
            ->withConsecutive( // insert first, in order, then updates
                [ 'INSERT INTO a2obj_core_database_polyobject1 (id) VALUES (:d0)', array('d0'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 (id) VALUES (:d0)', array('d0'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 (type,id) VALUES (:d0,:d1)', array('d0'=>13,'d1'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a (type,id) VALUES (:d0,:d1)', array('d0'=>100,'d1'=>$id1) ],
                [ 'INSERT INTO a2obj_core_database_polyobject1 (id) VALUES (:d0)', array('d0'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject2 (id) VALUES (:d0)', array('d0'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject4 (type,id) VALUES (:d0,:d1)', array('d0'=>13,'d1'=>$id4) ],
                [ 'INSERT INTO a2obj_core_database_polyobject5a (type,id) VALUES (:d0,:d1)', array('d0'=>102,'d1'=>$id4) ],
                [ 'UPDATE a2obj_core_database_polyobject1 SET testprop1=:d0 WHERE id=:id', array('d0'=>55,'id'=>$id2) ],
                [ 'UPDATE a2obj_core_database_polyobject1 SET testprop1=:d0 WHERE id=:id', array('d0'=>65,'id'=>$id3) ],
                [ 'UPDATE a2obj_core_database_polyobject4 SET testprop4c=testprop4c+:d0 WHERE id=:id', array('d0'=>10,'id'=>$id5) ]
            )->willReturn(1);
        
        $objdb->SaveObjects(); // insert/update
        $objdb->SaveObjects(); // nothing to do now
    }
    
    public function testDeleteObject() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = new PolyObject5a($objdb, array('id'=>($id='testid1234')));
        
        $qstr = 'DELETE a2obj_core_database_polyobject1, a2obj_core_database_polyobject2, a2obj_core_database_polyobject4, a2obj_core_database_polyobject5a '.
            'FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
            'WHERE a2obj_core_database_polyobject1.id = :d0';
        
        $database->expects($this->once())->method('write')
            ->with($qstr, array('d0'=>$id))->willReturn(1);
        
        $obj->Delete();
        
        $this->assertTrue($obj->isDeleted());
    }

    public function testDeleteByQueryYesReturning() : void
    {        
        // loading by every base class should yield the same results!
        foreach (array(PolyObject0::class, PolyObject1::class, PolyObject2::class, PolyObject3::class, PolyObject4::class) as $class)
        {
            $database = $this->createMock(Database::class);
            $objdb = new ObjectDatabase($database);
            $database->method('SupportsRETURNING')->willReturn(true);
            
            // first we load the objects in advance so we can check isDeleted()
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
            
            $database->expects($this->exactly(3))->method('read')
                ->withConsecutive(self::select1, self::select2, self::select3)
                ->willReturnOnConsecutiveCalls([self::row1], [self::row2], []);
                
            $objs = $objdb->LoadObjectsByQuery($class, $q);
            
            $this->assertSame(2, count($objs));
            $id1 = self::id1; $obj1 = $objs[$id1];
            $id2 = self::id2; $obj2 = $objs[$id2];
            
            $ondel1 = $this->createMock(OnDelete::class); $ondel1->expects($this->once())->method('Delete'); $obj1->SetOnDelete($ondel1);
            $ondel2 = $this->createMock(OnDelete::class); $ondel2->expects($this->once())->method('Delete'); $obj2->SetOnDelete($ondel2);
            
            // now we delete the objects and hope it deletes the same ones
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
            
            $deleteStr1 =
                'DELETE a2obj_core_database_polyobject1, a2obj_core_database_polyobject2, a2obj_core_database_polyobject4, a2obj_core_database_polyobject5a '.
                'FROM a2obj_core_database_polyobject1 '.
                'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
                'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
                'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
                'WHERE testprop1 > :d0 RETURNING *';
                
            $deleteStr2 =
                'DELETE a2obj_core_database_polyobject1, a2obj_core_database_polyobject2, a2obj_core_database_polyobject4 '.
                'FROM a2obj_core_database_polyobject1 '.
                'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
                'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
                'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1) RETURNING *';

            $database->expects($this->exactly(3))->method('readwrite')
                ->withConsecutive(
                    [$deleteStr1, array('d0'=>3)], 
                    [$deleteStr2, array('d0'=>3, 'd1'=>18)],
                    [$deleteStr2, array('d0'=>3, 'd1'=>5)])
                ->willReturnOnConsecutiveCalls(
                    [self::row1], 
                    [self::row2], []);
            
            $numDeleted = $objdb->DeleteObjectsByQuery($class, $q);
            
            $this->assertSame($numDeleted, 2);
            $this->assertTrue($obj1->isDeleted());
            $this->assertTrue($obj2->isDeleted());
        }
    }
    
    public function testDeleteByQueryNoReturning() : void
    {       
        // loading by every base class should yield the same results!
        foreach (array(PolyObject0::class, PolyObject1::class, PolyObject2::class, PolyObject3::class, PolyObject4::class) as $class)
        {
            $database = $this->createMock(Database::class);
            $objdb = new ObjectDatabase($database);
            $database->method('SupportsRETURNING')->willReturn(false);
            
            // first we load the objects in advance so we can check isDeleted()
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
            
            $database->expects($this->exactly(6))->method('read') // can only mock read once
                ->withConsecutive(self::select1, self::select2, self::select3, self::select1, self::select2, self::select3)
                ->willReturnOnConsecutiveCalls([self::row1], [self::row2], [], [self::row1], [self::row2], []);
                
            $objs = $objdb->LoadObjectsByQuery($class, $q);
            
            $this->assertSame(2, count($objs));
            $id1 = self::id1; $obj1 = $objs[$id1];
            $id2 = self::id2; $obj2 = $objs[$id2];
            
            $ondel1 = $this->createMock(OnDelete::class); $ondel1->expects($this->once())->method('Delete'); $obj1->SetOnDelete($ondel1);
            $ondel2 = $this->createMock(OnDelete::class); $ondel2->expects($this->once())->method('Delete'); $obj2->SetOnDelete($ondel2);
            
            // now we delete the objects and hope it deletes the same ones
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
    
            $deleteStr1 =
                'DELETE a2obj_core_database_polyobject1, a2obj_core_database_polyobject2, a2obj_core_database_polyobject4, a2obj_core_database_polyobject5a '.
                'FROM a2obj_core_database_polyobject1 '.
                'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
                'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
                'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
                'WHERE a2obj_core_database_polyobject1.id = :d0';
                
            $deleteStr2 =
                'DELETE a2obj_core_database_polyobject1, a2obj_core_database_polyobject2, a2obj_core_database_polyobject4 '.
                'FROM a2obj_core_database_polyobject1 '.
                'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
                'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
                'WHERE a2obj_core_database_polyobject1.id = :d0';
    
            $database->expects($this->exactly(2))->method('write')
                ->withConsecutive(
                    [$deleteStr1, array('d0'=>self::id1)], 
                    [$deleteStr2, array('d0'=>self::id2)])
                ->willReturn(1);
                    
            $numDeleted = $objdb->DeleteObjectsByQuery($class, $q);
            
            $this->assertSame($numDeleted, 2);
            $this->assertTrue($obj1->isDeleted());
            $this->assertTrue($obj2->isDeleted());
        }
    }
    
    public function testLoadedObjects() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(3))->method('read')
            ->withConsecutive(self::select1, self::select2, self::select3)
            ->willReturnOnConsecutiveCalls([self::row1], [self::row2], []);
            
        $database->expects($this->exactly(1))->method('write')->willReturn(1);
            
        $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3));
        
        $objdb->LoadObjectsByQuery(PolyObject3::class, $q);
        $eid = EasyObject::Create($objdb)->Save()->ID();
        
        $this->assertSame(3, $objdb->getLoadedCount());
        
        $loaded = array(
            PolyObject1::class => array(
                self::id1 => PolyObject5aa::class,
                self::id2 => PolyObject5b::class
            ),
            EasyObject::class => array(
                $eid => EasyObject::class
            )
        );
        
        $this->assertSame($loaded, $objdb->getLoadedObjects());
    }
    
    public function testEmptyKeyLoad() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive(
                ["SELECT * FROM a2obj_core_database_easyobject WHERE generalKey = :d0", array('d0'=>5)],
                ["SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0", array('d0'=>5)])
            ->willReturnOnConsecutiveCalls([], []);
        
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }
    
    public function testNonUniqueKeyLoad() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT * FROM a2obj_core_database_easyobject WHERE generalKey = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)], [$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)], []);
            
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(5, $obj1->GetGeneralKey());
        
        $this->assertInstanceOf(EasyObject::class, $obj2);
        $this->assertSame($id2, $obj2->ID());
        $this->assertSame(5, $obj2->GetGeneralKey());
        
        // load where generalKey is 6, get nothing back...
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6));
        
        //should return the same objects w/o loading from DB
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }
    
    private const polySelect1 = array(
        "SELECT * FROM a2obj_core_database_polyobject1 ".
        "JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id ".
        "JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id ".
        "JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id ".
        "WHERE testprop5 = :d0", array('d0'=>55));
    
    public function testUniqueKeyLoad() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id='test123','uniqueKey'=>5)], []);
        
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        
        $this->assertInstanceOf(EasyObject::class, $obj); assert($obj !== null);
        $this->assertSame($id, $obj->ID());
        $this->assertSame(5, $obj->GetUniqueKey());
        
        // load where uniqueKey is 6, get nothing back...
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
        
        //should return the same object w/o loading from DB
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }
    
    public function testNonUniqueKeyPolyLoad() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
            
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        $this->assertInstanceOf(PolyObject5a::class, $obj1);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        assert($obj1 !== null);
        $this->assertSame(55, $obj1->GetTestProp5());
        
        // will NOT call the database again (child classes)
        $objs = $objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj1, $objs[$id1]);
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testUniqueKeyPolyLoad() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5a::class, $obj1);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        assert($obj1 !== null);
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(55, $obj1->GetTestProp5());
        
        // will NOT call the database again (child classes)
        $obj2 = $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertSame($obj1, $obj2);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testNonUniqueKeyDelete() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')
            ->with("SELECT * FROM a2obj_core_database_easyobject WHERE generalKey = :d0", array('d0'=>5))
            ->willReturn([array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertInstanceOf(EasyObject::class, $obj2);
        
        $delstr = "DELETE FROM a2obj_core_database_easyobject WHERE a2obj_core_database_easyobject.id = :d0";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$delstr, array('d0'=>$id1)], [$delstr, array('d0'=>$id2)])
            ->willReturn(1);
        
        $obj1->Delete();
        // should only get one result back now
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2);
        $this->assertSame($obj2, $objs2[$id2]);
        
        $obj2->Delete();
        // should get an empty array now
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
    }
    
    public function testUniqueKeyDelete() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')
            ->with("SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0", array('d0'=>5))
            ->willReturn([array('id'=>$id='test123','uniqueKey'=>5)]);
            
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj); assert($obj !== null);

        $database->expects($this->once())->method('write')
            ->with("DELETE FROM a2obj_core_database_easyobject WHERE a2obj_core_database_easyobject.id = :d0", array('d0'=>$id))
            ->willReturn(1);
            
        $obj->Delete();
        // should return null w/o reloading from DB
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }
    
    public function testNonUniqueKeyPolyDelete() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        
        $obj1->Delete();
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        // will NOT call the database (child classes)
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testUniqueKeyPolyDelete() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>'test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5a::class, $obj1); assert($obj1 !== null);
        
        $obj1->Delete();
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
        
        // will NOT call the database (child classes)
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
    }
    
    public function testNonUniqueKeyModify() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);

        $selstr = "SELECT * FROM a2obj_core_database_easyobject WHERE generalKey = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([$ar1=array('id'=>$id1='test123','generalKey'=>5), array('id'=>$id2='test456','generalKey'=>5)], [$ar1]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs); $obj1 = $objs[$id1]; $obj2 = $objs[$id2];
        
        $this->assertInstanceOf(EasyObject::class, $obj1);
        $this->assertInstanceOf(EasyObject::class, $obj2);
        
        $updstr = "UPDATE a2obj_core_database_easyobject SET generalKey=:d0 WHERE id=:id";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$updstr, array('id'=>$id1,'d0'=>6)],[$updstr, array('id'=>$id2,'d0'=>6)])
            ->willReturn(1);
        
        $obj1->SetGeneralKey(6)->Save();

        // generalKey value 5 should now return one w/o reloading from DB
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2); $this->assertSame($obj2, $objs2[$id2]);
        
        // generalKey value 6 should now return one but must reload from DB (could be others)
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6);
        $this->assertCount(1, $objs2); $this->assertSame($obj1, $objs2[$id1]);
        
        $obj2->SetGeneralKey(6)->Save();
        
        // generalKey value 5 should now return empty w/o reloading
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5));
        
        // generalKey value 6 should now return both w/o reloading
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 6);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }
    
    public function testUniqueKeyModify() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $selstr = "SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>5)],[$selstr, array('d0'=>6)])
            ->willReturnOnConsecutiveCalls([array('id'=>$id='test123','uniqueKey'=>5)], []);
            
        $obj = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj); assert($obj !== null);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
        
        $database->expects($this->once())->method('write')
            ->with("UPDATE a2obj_core_database_easyobject SET uniqueKey=:d0 WHERE id=:id", array('id'=>$id,'d0'=>6))
            ->willReturn(1);
        
        $obj->SetUniqueKey(6)->Save();

        // uniqueKey value 5 should now return null w/o reloading from DB
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        // should return the object with the new value w/o reloading from DB
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6));
    }
    
    public function testNonUniqueKeyPolyModify() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $obj1 = $objs[$id1];
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);
        
        $obj1->SetTestProp5(66)->Save();
        
        // the child classes should return empty w/o re-calling the DB
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        // the object will not be cached under 66 as it's not loaded yet, so nothing else to check
    }
    
    public function testUniqueKeyPolyModify() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)
            ->willReturn([array('id'=>$id1='test123','type'=>101,'testprop5'=>55)]);
        $database->method('write')->willReturn(1);
        
        $obj1 = $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertInstanceOf(PolyObject5aa::class, $obj1);  assert($obj1 !== null);
        $this->assertSame($obj1->ID(), $id1);
        
        $obj1->SetTestProp5(66)->Save();
        
        // the child class should return the object w/ the new value w/o re-calling the DB
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 66));
        $this->assertSame($obj1, $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 66));
        $this->assertSame($obj1, $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 66));
    }
    
    public function testNonUniqueKeyInsert() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj1 = EasyObject::Create($objdb); $id1 = $obj1->ID();
        $obj2 = EasyObject::Create($objdb); $id2 = $obj2->ID();
        
        $selstr = "SELECT * FROM a2obj_core_database_easyobject WHERE generalKey = :d0";
        $database->expects($this->exactly(2))->method('read')
            ->withConsecutive([$selstr, array('d0'=>33)], [$selstr, array('d0'=>5)])
            ->willReturnOnConsecutiveCalls([], [array('id'=>$id1,'generalKey'=>5)]);
            
        // unlike unique, querying 33 does not set us up to load 5 w/o a query
        $this->assertCount(0, $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 33)); // different value!

        $insstr = "INSERT INTO a2obj_core_database_easyobject (generalKey,id) VALUES (:d0,:d1)";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$insstr, array('d0'=>5,'d1'=>$id1)], [$insstr, array('d0'=>5,'d1'=>$id2)])
            ->willReturn(1);

        // load via generalKey 5 - still requires a query since there could be others!
        $obj1->SetGeneralKey(5)->Save();
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(1, $objs2); $this->assertSame($obj1, $objs2[$id1]);
        
        // load via generalKey 5 - should return both objects w/o a query now
        $obj2->SetGeneralKey(5)->Save();
        $objs2 = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 5);
        $this->assertCount(2, $objs2);
        $this->assertSame($obj1, $objs2[$id1]);
        $this->assertSame($obj2, $objs2[$id2]);
    }
    
    public function testUniqueKeyInsert() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $obj = EasyObject::Create($objdb);
        
        $database->expects($this->exactly(1))->method('read')
            ->with("SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0", array('d0'=>6))
            ->willReturn(array());
        
        // have to do a uniqueKey query so the DB knows it's a unique key
        $this->assertNull($objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 6)); // different value!

        $database->expects($this->exactly(1))->method('write')
            ->with("INSERT INTO a2obj_core_database_easyobject (uniqueKey,id) VALUES (:d0,:d1)", array('d0'=>5,'d1'=>$obj->ID()))
            ->willReturn(1);
        
        $obj->SetUniqueKey(5)->Save();
        
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
    }
    
    public function testNonUniqueKeyPolyInsert() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)->willReturn([]);
        $database->method('write')->willReturn(1);
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55));
        
        $obj = PolyObject5aa::Create($objdb)->SetTestProp5(55)->Save();
        
        $this->assertEmpty($objdb->LoadObjectsByKey(PolyObject5ab::class, 'testprop5', 55));
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5aa::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj, $objs[$obj->ID()]);
        
        $objs = $objdb->LoadObjectsByKey(PolyObject5a::class, 'testprop5', 55);
        $this->assertCount(1, $objs); $this->assertSame($obj, $objs[$obj->ID()]);
    }
    
    public function testUniqueKeyPolyInsert() : void
    {
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(1))->method('read')->with(...self::polySelect1)->willReturn([]);
        $database->method('write')->willReturn(1);
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
        
        $obj = PolyObject5aa::Create($objdb)->SetTestProp5(55)->Save();
        
        $this->assertNull($objdb->TryLoadUniqueByKey(PolyObject5ab::class, 'testprop5', 55));
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(PolyObject5aa::class, 'testprop5', 55));
        $this->assertSame($obj, $objdb->TryLoadUniqueByKey(PolyObject5a::class, 'testprop5', 55));
    }
    
    public function testNonUniqueNullKey() : void
    {
        // test inserting objects with values null/0 and test they're treatly differently
        
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->expects($this->exactly(2))->method('read')->willReturn([]);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', null);
        $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 0);

        $obj1 = EasyObject::Create($objdb)->SetGeneralKey(null); $id1 = $obj1->ID();
        $obj2 = EasyObject::Create($objdb)->SetGeneralKey(0); $id2 = $obj2->ID();
        
        $insstr1 = "INSERT INTO a2obj_core_database_easyobject (generalKey,id) VALUES (NULL,:d0)";
        $insstr2 = "INSERT INTO a2obj_core_database_easyobject (generalKey,id) VALUES (:d0,:d1)";
        $database->expects($this->exactly(2))->method('write')
            ->withConsecutive([$insstr1, array('d0'=>$id1)], [$insstr2, array('d0'=>0,'d1'=>$id2)])
            ->willReturn(1);
            
        $obj1->Save(); $obj2->Save();
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', null);
        $this->assertCount(1, $objs); $this->assertSame($obj1, $objs[$id1]);
        
        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'generalKey', 0);
        $this->assertCount(1, $objs); $this->assertSame($obj2, $objs[$id2]);
    }
    
    public function testUniqueNullKey() : void
    {
        // NULL is not actually a unique value! can have more than one
        // setting a unique key to/from null should move it from/to the unique cache
        
        $database = $this->createMock(Database::class);
        $objdb = new ObjectDatabase($database);
        
        $database->method('write')->willReturn(1);
        
        $database->expects($this->exactly(3))->method('read')
            ->withConsecutive(
                ["SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey = :d0", array('d0'=>5)],
                ["SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey IS NULL", array()], // LoadObjectsByKey
                ["SELECT * FROM a2obj_core_database_easyobject WHERE uniqueKey IS NULL", array()]) // TryLoadUniqueByKey
            ->willReturnOnConsecutiveCalls(
                [array('id'=>$id1='test123','uniqueKey'=>5)], 
                [array('id'=>$id2='test456','uniqueKey'=>null), array('id'=>$id3='test789','uniqueKey'=>null)], 
                [array('id'=>$id1='test123','uniqueKey'=>null), array('id'=>$id3='test789','uniqueKey'=>null)]);
        
        $obj1 = $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5);
        $this->assertInstanceOf(EasyObject::class, $obj1); assert($obj1 !== null);
        $this->assertSame($id1, $obj1->ID());
        $this->assertSame(5, $obj1->GetUniqueKey());

        $objs = $objdb->LoadObjectsByKey(EasyObject::class, 'uniqueKey', null);
        $this->assertCount(2, $objs);
        $obj2 = $objs[$id2]; $obj3 = $objs[$id3];
        $this->assertSame($id2, $obj2->ID());
        $this->assertSame($id3, $obj3->ID());
        $this->assertNull($obj2->GetUniqueKey());
        $this->assertNull($obj3->GetUniqueKey());
        
        $obj1->SetUniqueKey(null)->Save();
        $this->assertCount(3, $objdb->LoadObjectsByKey(EasyObject::class, 'uniqueKey', null));
        
        $obj2->SetUniqueKey(5)->Save();
        $this->assertSame($obj2, $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', 5));
        
        $this->expectException(UniqueKeyException::class);
        $objdb->TryLoadUniqueByKey(EasyObject::class, 'uniqueKey', null); /** @phpstan-ignore-line */
    }
    
    public function testLimitOffset() : void
    {
        $sel1 = 'SELECT * FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'JOIN a2obj_core_database_polyobject5a ON a2obj_core_database_polyobject5a.id = a2obj_core_database_polyobject4.id '.
            'WHERE a2obj_core_database_polyobject1.id IN (SELECT id FROM (SELECT id FROM a2obj_core_database_polyobject1 '.
                'WHERE testprop1 > :d0 LIMIT 3 OFFSET 2) AS t)';
        
        $sel2 = 'SELECT * FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'WHERE a2obj_core_database_polyobject1.id IN (SELECT id FROM (SELECT id FROM a2obj_core_database_polyobject1 '.
                'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1) LIMIT 3 OFFSET 2) AS t)';
        
        $sel3a = 'SELECT * FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'WHERE a2obj_core_database_polyobject1.id IN (SELECT id FROM (SELECT id FROM a2obj_core_database_polyobject1 '.
                'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1) LIMIT 3 OFFSET 2) AS t)';
        
        $sel3b = 'SELECT * FROM a2obj_core_database_polyobject1 '.
            'JOIN a2obj_core_database_polyobject2 ON a2obj_core_database_polyobject2.id = a2obj_core_database_polyobject1.id '.
            'JOIN a2obj_core_database_polyobject4 ON a2obj_core_database_polyobject4.id = a2obj_core_database_polyobject2.id '.
            'WHERE (testprop1 > :d0 AND a2obj_core_database_polyobject4.type = :d1) LIMIT 3 OFFSET 2';
            
        // loading by every base class should yield the same results!
        foreach (array(PolyObject0::class, PolyObject1::class, PolyObject2::class, PolyObject3::class, PolyObject4::class) as $class)
        {
            $database = $this->createMock(Database::class);
            $objdb = new ObjectDatabase($database);
            
            $q = new QueryBuilder(); $q->Where($q->GreaterThan('testprop1',3))->Limit(3)->Offset(2);
            
            $sel3 = ($class === PolyObject4::class) ? $sel3b : $sel3a;
            
            $database->expects($this->exactly(3))->method('read')
                ->withConsecutive(
                    array($sel1, array('d0'=>3)), 
                    array($sel2, array('d0'=>3, 'd1'=>18)), 
                    array($sel3, array('d0'=>3, 'd1'=>5)))
                ->willReturnOnConsecutiveCalls([self::row1], [self::row2], []);
                
            $objs = $objdb->LoadObjectsByQuery($class, $q);
            
            $this->assertSame(2, count($objs));
            $id1 = self::id1; $obj1 = $objs[$id1];
            $id2 = self::id2; $obj2 = $objs[$id2];
            
            $this->assertInstanceof(PolyObject5a::class, $obj1);
            assert($obj1 instanceof PolyObject5a);
            $this->assertSame($id1, $obj1->ID());
            
            $this->assertInstanceof(PolyObject5b::class, $obj2);
            assert($obj2 instanceof PolyObject5b);
            $this->assertSame($id2, $obj2->ID());
        }
    }
}

