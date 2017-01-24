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

    public function __construct(array $depencyList = [])
    {
        foreach ($depencyList as $class => $value) {
            $this->$class = $value;
        }
    }

    public function __set($name, $config)
    {
        if (isset($this->data[$name])) {
            throw new \ErrorException("Object '{$name}' already exists");
        }
        $this->data[$name] = $config;
    }

    public function __get($name)
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $callback = $this->data[$name] ?? null;
        if (is_null($callback)) {
            return null;
        }
        elseif ($callback instanceof \Closure) {
            return ($callback)($this);
        }
        else {
            return $this->cache[$name] = $this->create($name);
        }
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        if (isset($this->cache[$name])) {
            throw new \ErrorException("Object '{$name}' already cached");
        }
        unset($this->data[$name]);
    }

    /**
     * Метод для создания объекта (имеено создания, поэтому кеш обходиться)
     * @param  string $className    имя класса
     * @return mixed                объект класса
     */
    public function create(string $className)
    {
        $object = null;
        $config = $this->data[$className];

        if (is_array($config)) {
            $object = $this->createFromArray($config);
        }
        elseif (is_string($config)) {
            $object = $this->createFromClassName($config);
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
    public function createFromArray(array $config)
    {
        $className = $config['class'];
        unset($config['class']);

        $object = $this->createFromClassName($className);
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
    public function createFromClassName(string $className)
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
            $parametrValue = $this->$parametrClassName;

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
