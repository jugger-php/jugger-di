<?php

namespace jugger\di;

use jugger\base\ArrayAccessTrait;

/**
 * Контейнер зависимостей
 */
class Container implements \ArrayAccess
{
    use ArrayAccessTrait;

    protected $data = [];
    protected $cache = [];

    public function __construct(array $depencyList)
    {
        foreach ($depencyList as $class => $value) {
            $this->offsetSet($class, $value);
        }
    }

    public function __isset($class)
    {
        return isset($this->data[$class]);
    }

    public function __set($class, $config)
    {
        if (isset($this->data[$class])) {
            return false;
        }
        $this->data[$class] = $config;
        return true;
    }

    public function __unset($class)
    {
        if (isset($this->cache[$class])) {
            return false;
        }
        unset($this->data[$class]);
        return true;
    }

    public function __get($className)
    {
        if (!$this->offsetExists($className)) {
            return null;
        }
        elseif (isset($this->cache[$className])) {
            return $this->cache[$className];
        }
        else {
            return $this->cache[$className] = $this->createObject($className);
        }
    }

    /**
     * Метод для создания объекта (имеено создания, поэтому кеш обходиться)
     * @param  string $className    имя класса
     * @return mixed                объект класса
     */
    public function createObject(string $className)
    {
        $object = null;
        $config = $this->data[$className];

        if (is_callable($config)) {
            $object = call_user_func_array($config, [$this]);
        }
        elseif (is_array($config)) {
            $object = $this->createObjectFromArray($config);
        }
        elseif (is_string($config)) {
            $object = $this->createObjectFromClassName($config);
        }
        else {
            throw new \ErrorException("Invalide config of class '{$className}', config type of '". gettype($config) ."'");
        }

        return $object;
    }

    /**
     * Если массив свойств, то он должен содержать создаваемый класс и его свойства:
     *      [
     *          'class' => 'class\name\Space',
     *          'property1' => 'value',
     *          'property2' => 'value',
     *          // ...
     *      ]
     *
     * @param  array  $classData конфиг для создания класса
     * @return object
     */
    public function createObjectFromArray(array $config)
    {
        $className = $config['class'];
        unset($config['class']);

        $object = $this->createObjectFromClassName($className);
        foreach ($config as $property => $value) {
            $object->$property = $value;
        }

        return $object;
    }

    /**
     * Если строка, то это должен быть создаваемый класс.
     * Если конструктор данного класса не содержит параметров - то создается новый экземпляр,
     * Если конструктор данного класса содержит атрибуты с класами из контейнера - то они автоматически подставляются.
     *
     *  Пример класса:
     *  class Test
     *  {
     *      public function __construct(FooInterface $a, BarInterface $b) { ... }
     *  }
     *
     *  То вызов будет иметь вид:
     *  $container['Test'] = new Test($container['FooInterface'], $container['BarInterface']);
     *
     * @param  string $className имя класса
     * @return object
     */
    public function createObjectFromClassName(string $className)
    {
        $class = new \ReflectionClass($className);
        $construct = $class->getConstructor();
        if ($construct) {
            $constructParams = $construct->getParameters();
        }
        else {
            return $class->newInstance();
        }

        $args = [];
        $object = null;
        foreach ($constructParams as $p) {
            $parametrClass = $p->getClass();
            if ($parametrClass === null) {
                $object = $class->newInstance();
                break;
            }

            $parametrClassName = $parametrClass->getName();
            $parametrValue = $this->offsetGet($parametrClassName);

            if ($parametrValue) {
                // pass
            }
            elseif ($p->isOptional()) {
                $parametrValue = $p->getDefaultValue();
            }

            $args[] = $parametrValue;
        }

        if ($object) {
            // pass
        }
        elseif (empty($args)) {
            $object = $class->newInstance();
        }
        else {
            $object = $class->newInstanceArgs($args);
        }

        return $object;
    }
}
