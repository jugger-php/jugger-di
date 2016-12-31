<?php

use PHPUnit\Framework\TestCase;
use jugger\di\Di;
use jugger\di\Container;

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
        $test3 = $con->createObjectFromClassName('Test3');

        $this->assertInstanceOf(Test1::class, $test3->t1);
        $this->assertInstanceOf(Test2::class, $test3->t2);
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
        $test3 = $con->createObjectFromArray([
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
        catch (\Exception $e) {
            $this->assertInstanceOf(ClassIsSet::class, $e);
        }
    }

    /**
     * @depends testGet
     */
    public function testUnset()
    {
        Di::$c['Test6'] = 'Test1';
        Di::$c['Test7'] = 'Test1';

        // not unset cached data
        $t6 = Di::$c['Test6'];
        unset(Di::$c['Test6']);
        $this->assertTrue($t6 === Di::$c['Test6']);

        // ok
        unset(Di::$c['Test7']);
    }

    /**
     * @depends testGet
     */
    public function testCache()
    {
        $t1 = Di::$c['Test1'];
        $t2 = Di::$c->createObject('Test1');
        $t3 = Di::$c['Test1'];
        $t4 = Di::$c->createObject('Test1');

        $this->assertTrue($t1 !== $t2);
        $this->assertTrue($t1 === $t3);
        $this->assertTrue($t1 !== $t4);
        $this->assertTrue($t2 !== $t4);
    }
}
