<?php

namespace jugger\di;

use jugger\base\ArrayAccessTrait;

/**
 * Фабрика объектов
 */
class Factory implements \ArrayAccess
{
    use ArrayAccessTrait;

    protected $vars = [];

    public function __set(string $name, $value)
    {
        $this->vars[$name] = $value;
    }

    public function __get(string $name)
    {
        if (isset($this->$name)) {
            $value = $this->vars[$name];
            if ($value instanceof \Closure) {
                return $value();
            }
            else {
                return $value;
            }
        }
        return null;
    }

    public function __isset(string $name)
    {
        return isset($this->vars[$name]);
    }

    public function __unset(string $name)
    {
        unset($this->vars[$name]);
    }
}
