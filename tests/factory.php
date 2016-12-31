<?php

use PHPUnit\Framework\TestCase;
use jugger\di\Di;
use jugger\di\Factory;

class FactoryTest extends TestCase
{
    public function testBase()
    {
        Di::$f = new Factory();
        Di::$f['a'] = "a";
        Di::$f['b'] = function() {
            return "b";
        };

        /*
         * get test
         */

        $this->assertEquals(Di::$f['a'], "a");
        $this->assertEquals(Di::$f['b'], "b");
        $this->assertNull(Di::$f['d'], null);

        /*
         * set test
         */

        Di::$f['c'] = 123;
        Di::$f['c'] = 456;

        $this->assertEquals(Di::$f['c'], 456);
    }
}
