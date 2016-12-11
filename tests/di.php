<?php

use PHPUnit\Framework\TestCase;
use jugger\di\Di;
use jugger\di\Container;
use jugger\di\ClassIsSet;
use jugger\di\ClassAlreadyCached;

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

class DiTest extends TestCase
{
    public function testCreate()
    {
        Container::$c = new Container([
            'Test1' => 'Test1',
            'Test2' => [
                'class' => 'Test2',
                'property1' => 'value1',
                'property2' => 'value2',
                'property3' => 'value3',
            ],
        ]);

        $this->assertEquals(Di::$c, Container::$c);

        Di::$c['Test3'] = 'Test3';
        Di::$c['Test4'] = function(Container $c) {
            return 123;
        };
    }

    /**
     * @depends testCreate
     */
    public function testCreateClass()
    {
        $test3 = Di::$c->createObjectFromClassName('Test3');

        $this->assertInstanceOf(Test1::class, $test3->t1);
        $this->assertInstanceOf(Test2::class, $test3->t2);
    }

    /**
     * @depends testCreate
     */
    public function testCreateClassFromArray()
    {
        $test3 = Di::$c->createObjectFromArray([
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

        $this->assertEquals(Di::$c['Test1'], Di::get('Test1'));

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

        // get Test5
        Di::$c['Test6'];

        // throw
        try {
            unset(Di::$c['Test6']);
        }
        catch (\Exception $e) {
            $this->assertInstanceOf(ClassAlreadyCached::class, $e);
        }

        // ok
        unset(Di::$c['Test7']);
    }
}
