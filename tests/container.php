<?php

use PHPUnit\Framework\TestCase;
use jugger\di\Di;
use jugger\di\Container;
use jugger\db\Query;
use jugger\db\Command;
use jugger\db\driver\MysqliConnection;

class Test1 {}

class Test2
{
    var $property1;
    var $property2;
    var $property3;
}

class Test3
{
    var $t1;
    var $t2;

    public function __construct(Test1 $t1, Test2 $t2)
    {
        $this->t1 = $t1;
        $this->t2 = $t2;
    }
}

class Test8
{
    var $t1;
    var $p1;

    public function __construct(Test1 $t1, $p1 = 'test')
    {
        $this->t1 = $t1;
        $this->p1 = $p1;
    }
}

class Test9
{
    var $p1;
    var $p2;

    public function __construct($p1, $p2)
    {
        $this->p1 = $p1;
        $this->p2 = $p2;
    }
}

class ContainerTest extends TestCase
{
    public function testCreate()
    {
        Di::$c = new Container([
            'Test1' => 'Test1',
            'Test2' => [
                'class' => 'Test2',
                'property1' => 'value1',
                'property2' => 'value2',
                'property3' => 'value3',
            ],
        ]);
        Di::$c['Test3'] = 'Test3';
        Di::$c['Test4'] = function(Container $c) {
            return 123;
        };
    }

    /**
     * @depends testCreate
     */
    public function testAccess()
    {
        $this->assertNotEmpty(Di::$c->Test1);
        $this->assertTrue(Di::$c->Test1 === Di::$c['Test1']);
    }

    /**
     * @depends testCreate
     */
    public function testCreateClass()
    {
        $con = new Container([
            'Test1' => 'Test1',
            'Test2' => 'Test2',
        ]);

        // test depends parametrs
        $test3 = $con->createFromClassName('Test3');
        $this->assertInstanceOf(Test1::class, $test3->t1);
        $this->assertInstanceOf(Test2::class, $test3->t2);

        // test depends/options parametrs
        $test8 = $con->createFromClassName('Test8');
        $this->assertEquals($test8->p1, 'test');
        $this->assertInstanceOf(Test1::class, $test8->t1);

        // test options parametrs
        $test9 = $con->createFromClassName('Test9');
        $this->assertNull($test9->p1);
        $this->assertNull($test9->p2);
    }

    /**
     * @depends testCreate
     */
    public function testCreateClassFromArray()
    {
        $con = new Container([
            'Test1' => 'Test1',
            'Test2' => 'Test2',
        ]);
        $test3 = $con->createFromArray([
            'class' => 'Test3',
            't2' => null,
        ]);

        $this->assertInstanceOf(Test1::class, $test3->t1);
        $this->assertNull($test3->t2);
    }

    /**
     * @depends testCreate
     */
    public function testGet()
    {
        $test1 = Di::$c['Test1'];
        $test2 = Di::$c['Test2'];
        $test3 = Di::$c['Test3'];
        $test4 = Di::$c['Test4'];
        $test5 = Di::$c['Test5'];

        $this->assertInstanceOf(Test1::class, $test1);
        $this->assertInstanceOf(Test2::class, $test2);
        $this->assertInstanceOf(Test3::class, $test3);

        $this->assertEquals($test2->property1, 'value1');
        $this->assertEquals($test2->property2, 'value2');
        $this->assertEquals($test2->property3, 'value3');

        $this->assertInstanceOf(Test1::class, $test3->t1);
        $this->assertInstanceOf(Test2::class, $test3->t2);

        $this->assertEquals($test4, 123);
        $this->assertNull($test5);
    }

    /**
     * @depends testCreate
     */
    public function testReadOnly()
    {
        Di::$c['Test5'] = 'Test1';
        try {
            Di::$c['Test5'] = 'Test1';
        }
        catch (\ErrorException $e) {
            return;
        }
        $this->assertTrue(false);
    }

    /**
     * @depends testGet
     */
    public function testUnset()
    {
        Di::$c['Test7'] = 'Test1';
        unset(Di::$c['Test7']);
        $this->assertNull(Di::$c['Test7']);
    }

    /**
     * @depends testUnset
     */
    public function testUnsetException()
    {
        $this->expectException(\ErrorException::class);

        Di::$c['Test6'] = 'Test1';
        $class = Di::$c['Test6'];
        unset(Di::$c['Test6']);
    }

    /**
     * @depends testUnsetException
     */
    public function testCache()
    {
        $t1 = Di::$c['Test1'];
        $t2 = Di::$c->create('Test1');
        $t3 = Di::$c['Test1'];
        $t4 = Di::$c->create('Test1');

        $this->assertTrue($t1 !== $t2);
        $this->assertTrue($t1 === $t3);
        $this->assertTrue($t1 !== $t4);
        $this->assertTrue($t2 !== $t4);
    }

    /**
     * @depends testCache
     */
    public function testFactory()
    {
        Di::$c->db = function($c) {
            return new MysqliConnection();
        };
        Di::$c->query = function($c) {
            return new Query($c->db);
        };
        Di::$c->command = function($c) {
            return new Command($c->db);
        };

        $q1 = Di::$c->query;
        $q2 = Di::$c->query;

        $this->assertTrue($q1 !== $q2);
        $this->assertInstanceOf(Query::class, Di::$c->query);
        $this->assertInstanceOf(Command::class, Di::$c->command);
        $this->assertInstanceOf(MysqliConnection::class, Di::$c->db);
    }
}
