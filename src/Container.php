<?php

namespace jugger\di;

use ArrayAccess;
use Exception;
use ErrorException;
use ReflectionClass;
use jugger\base\Singleton;

class Container extends Singleton implements ArrayAccess
{
    protected $data = [];
    protected $cache = [];

    public function init(array $depencyList)
    {
        foreach ($depencyList as $class => $value) {
            $this->offsetSet($class, $value);
        }
    }

    public static function get($name)
    {
        return self::getInstance()[$name];
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        if (isset($this->cache[$offset])) {
            throw new ErrorException("Class '{$offset}' is already cached");
        }
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        if (isset($this->cache[$offset])) {
            throw new ErrorException("Class '{$offset}' is already cached");
        }
        unset($this->data[$offset]);
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
        $classData = $this->data[$className];

        if (is_callable($classData)) {
            $object = call_user_func_array($classData, [$this]);
        }
        elseif (is_array($classData)) {
            $object = $this->createObjectFromArray($classData);
        }
        elseif (is_string($classData)) {
            $object = $this->createObjectFromClassName($classData);
        }
        else {
            throw new ErrorException("Invalide parametr '{$className}' type of '". gettype($classData) ."'");
        }

        return $this->cache[$className] = $object;
    }

    /**
     * Создает класс который наследуется от класса из контейнера, реализующего интерфейс
     * Например:
     *  // пакет 'db'
     *  namespace db {
     *      class Query {}
     *  }
     *  // пакет 'ar'
     *  namespace ar {
     *      interface QueryInterface {}
     *  }
     *  // добавление класса в контейнер
     *  $container::createInstance([
     *      'ar\QueryInterface' => function(Container $c) {
     *          return new class
     *              extends db\Query
     *              implements ar\QueryInterface {};
     *      }
     *  ]);
     *  // использование
     *  namespace ar {
     *      // создаем класс в текущем пространстве имен 'ar\Query'
     *      Container::getInstance()->createClass('ar\Query', 'ar\QueryInterface');
     *      // наследуемся от него
     *      class ActiveQuery extends Query {}
     *  }
     *
     * @param  string $className
     * @param  string $targetInterface
     */
    public function createClass($className, $targetInterface)
    {
        $targetClass = $this->offsetGet($targetInterface);
        if ($targetClass) {
            $targetClass = get_class($targetClass);
        }
        else {
            throw new Exception("Not found class implemented interface '{$targetInterface}'");
        }

        $className = '\\'. trim($className, '\\');
        if (class_exists($className)) {
            return false;
        }

        $targetClass = '\\'. trim($targetClass, '\\');
        $targetInterface = '\\'. trim($targetInterface, '\\');

        $classData = explode('\\', $className);
        $classNameShort = end($classData);
        $namespace = array_reduce($classData, function($c, $i) use($classNameShort) {
            if ($classNameShort == $i) {
                return $c;
            }
            elseif (!$c) {
                return $i;
            }
            return $c .'\\'. $i;
        });

        eval("
        namespace {$namespace} {
            class {$classNameShort} extends {$targetClass} implements {$targetInterface} {}
        }
        ");

        return true;
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
    protected function createObjectFromArray(array $classData)
    {
        $className = $classData['class'];
        unset($classData['class']);

        $object = (new ReflectionClass($className))->newInstance();
        foreach ($classData as $property => $value) {
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
    protected function createObjectFromClassName(string $className)
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
        foreach ($constructParams as $p) {
            $parametrClass = $p->getClass();
            if ($parametrClass === null) {
                $object = $class->newInstance();
                break;
            }

            $parametrValue = $this->offsetGet($parametrClass);
            if (is_null($parametrValue)) {
                if ($p->isOptional()) {
                    $parametrValue = $p->getDefaultValue();
                }
                else {
                    throw new ErrorException("Not found class '{$parametrClass}' as parametr for '{$className}'");
                }
            }

            $args[] = $parametrValue;
        }

        if (is_null($object) && !empty($args)) {
            $object = $class->newInstanceArgs($args);
        }
        elseif (is_null($object)) {
            $object = $class->newInstance();
        }

        return $object;
    }
}
