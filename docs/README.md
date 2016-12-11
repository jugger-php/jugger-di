# jugger-di

Контейнер зависимостей - объект, который содержит список абстракций и их реализаций.
В аббревиатуре SOLID, данному явлению отдана буква `D`.
В общем виде, работа с контейнером выглядит следующим образом:
```php
Di::$c['AbstractClass'] = 'ConcreteClass';

// или пример на мишках
Di::$c['Bear'] = 'WhiteBear';

// кормешка
$feedingBear = function(Bear $b) {
    // pass    
};

// кормим белого медведя
$whiteBear = Di::$c['Bear'];
$feedingBear($whiteBear);
```

Пример дурацкий, но позновательный. О том как это работает, ниже.

## Инициализация

Для создания контейнера, нужно просто создать соответствующий объект:
```php
$container = new Container([
    'Test1' => 'Test1',
    'Test2' => [
        'class' => 'Test2',
        'property1' => 'value1',
        'property2' => 'value2',
        'property3' => 'value3',
    ],
]);
```

Для дальнейшего использования и доступа к контейнеру, необходимо инциализировать статическую переменную `Container::$c`.
Также для удобства доступа можно использовать псевдоним класса `Di`:
```php
Di::$c = new Di([
    'Test1' => 'Test1',
    'Test2' => [
        'class' => 'Test2',
        'property1' => 'value1',
        'property2' => 'value2',
        'property3' => 'value3',
    ],
]);
```

## Доступ к объектам

Контейнер реализует интерфейс `ArrayAccess`, поэтому доступ к объектам можно получить 2-мя способами:
```php
$object1 = Di::$c['class1'];
$object1 = Di::get('class1');

// если зависимость не задана, то возвращает 'null'
$isNull = Di::$c['not exist class'];
```

## Read-only

Зависимость можно установить один раз, если конфиг для объекта уже задан, то при повторной инициализации возникнет исключение `ClassIsSet`.

## Установка зависимостей

Если класс имеет публичный конструктор без параметров, то достаточно указать полное имя класса:
```php
// $obj = new path\to\Class();
//
Di::$c['path\to\AbstractClass'] = 'path\to\Class';
```

Если выбранный объект имеет в конструкторе хинтованные параметры, то контейнер автоматически их подставит:
```php
class Test1 {}
class Test2 {}
class Test3
{
    public function __construct(Test1 $t1, Test2 $t2) { }
}

Di::$c['Test1'] = 'Test1';
Di::$c['Test2'] = 'Test2';
Di::$c['Test3'] = 'Test3';

// $test1 = Di::$c['Test1'];
// $test2 = Di::$c['Test2'];
// $test3 = new Test3($test1, $test2);
//
$test3 = Di::$c['Test3'];
```

Также можно сразу проводить инициализацию свойств объекта:
```php
// $obj = new path\to\Class();
// $obj->property1 = 'value1';
// $obj->property2 = 'value2';
// $obj->property3 = 'value3';
//
Di::$c['path\to\AbstractClass'] = [
    'class' => 'path\to\Class',
    'property1' => 'value1',
    'property2' => 'value2',
    'property3' => 'value3',
];
```

Также, что логично, можно комбинировать оба варианта:
```php
class Test1 {}
class Test2 {}
class Test3
{
    public function __construct(Test1 $t1, Test2 $t2) { }
}

Di::$c['Test1'] = 'Test1';
Di::$c['Test2'] = 'Test2';
Di::$c['Test3'] = [
    'class' => 'Test3',
    't2' => null,
];

// $test1 = Di::$c['Test1'];
// $test2 = Di::$c['Test2'];
// $test3 = new Test3($test1, $test2);
// $test3->t2 = null;
//
$test3 = Di::$c['Test3'];
```

Если предыдущие способы не подходят, то в качестве конфига можно указать анонимную функцию:
```php
Di::$c['Test4'] = function(Container $c) {
    $obj = new Test4(1,2,3);
    $obj->prop1 = $c['Test4'];
    return $obj;
};
```

Указывать нужно именно `Closure`, иначе ничего не сработает (если не понимаете почему, какого хера невнимательно читаете?!).

## Билдеры

Методы, которые занимаются созданием объектов из конфигов, доступны публично:
```php
$con = new Container([
    'Test1' => 'Test1',
    'Test2' => 'Test2',
]);

// создание по имени класса
//
// $test1 = Di::$c['Test1'];
// $test2 = Di::$c['Test2'];
// $test3 = new Test3($test1, $test2);
//
$test3 = $con->createObjectFromClassName('Test3');

// создание по конфигу
//
// $test1 = Di::$c['Test1'];
// $test2 = Di::$c['Test2'];
// $test3 = new Test3($test1, $test2);
// $test3->t2 = null;
//
$test3 = $con->createObjectFromArray([
    'class' => 'Test3',
    't2' => null,
]);
```
