<?php

namespace jugger\di;

use ArrayAccess;
use ReflectionClass;

/**
 * Контейнер зависимостей
 */
class Container implements ArrayAccess
{
    /**
     * Контейнер
     */
    public static $c;

    public static function get($name)
    {
        return self::$c[$name];
    }

    protected $data = [];
    protected $cache = [];

    public function __construct(array $depencyList)
    {
        foreach ($depencyList as $class => $value) {
            $this->offsetSet($class, $value);
        }
    }

    public function offsetExists($class)
    {
        return isset($this->data[$class]);
    }

    public function offsetSet($class, $config)
    {
        if (isset($this->data[$class])) {
            throw new ClassIsSet($class);
        }
        $this->data[$class] = $config;
    }

    public function offsetUnset($class)
    {
        if (isset($this->cache[$class])) {
            throw new ClassAlreadyCached($class);
        }
        unset($this->data[$class]);
    }

    public function offsetGet($className)
    {
        if (!$this->offsetExists($className)) {
            return null;
        }
        elseif (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

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
            throw new ErrorException("Invalide parametr '{$className}', type of '". gettype($config) ."'");
        }

        return $this->cache[$className] = $object;
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
        $class = new ReflectionClass($className);
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
            else {
                throw new NotFoundClass("parametr class: '{$parametrClassName}', class: '{$className}'");
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

/**
 * Псевдоним для контейнера
 */
class Di extends Container {}
