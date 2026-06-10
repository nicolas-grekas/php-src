--TEST--
Closures in constant expressions are serializable as declaration-site references
--FILE--
<?php

#[Attribute(Attribute::TARGET_ALL)]
class A {
    public function __construct(public mixed $cb = null, public mixed $extra = null) {}
}

#[A(static function () { return 'class'; })]
class Demo {
    #[A(static function () { return 'const'; })]
    public const FOO = 1;

    #[A(cb: [static function () { return 'prop-1'; }, static function (): string { return 'prop-2'; }])]
    public string $name = '';

    #[A(static function () { return 'method'; })]
    public function m(
        #[A(static function () { return 'param'; })]
        int $x = 0,
    ): void {}
}

enum E {
    #[A(static function () { return 'case'; })]
    case X;
}

$closures = [
    'class' => (new ReflectionClass(Demo::class))->getAttributes()[0]->getArguments()[0],
    'const' => (new ReflectionClassConstant(Demo::class, 'FOO'))->getAttributes()[0]->getArguments()[0],
    'prop-1' => (new ReflectionProperty(Demo::class, 'name'))->getAttributes()[0]->getArguments()['cb'][0],
    'prop-2' => (new ReflectionProperty(Demo::class, 'name'))->getAttributes()[0]->getArguments()['cb'][1],
    'method' => (new ReflectionMethod(Demo::class, 'm'))->getAttributes()[0]->getArguments()[0],
    'param' => (new ReflectionParameter([Demo::class, 'm'], 'x'))->getAttributes()[0]->getArguments()[0],
    'case' => (new ReflectionClassConstant(E::class, 'X'))->getAttributes()[0]->getArguments()[0],
];

foreach ($closures as $expected => $closure) {
    $r = new ReflectionFunction($closure);
    $id = $r->getConstExprId();
    $scope = $r->getClosureScopeClass()->name;

    $unserialized = unserialize(serialize($closure));
    $recreated = Closure::fromConstExpr($scope, $id);

    var_dump($expected === $closure() && $expected === $unserialized() && $expected === $recreated());
}

// Ids are assigned in canonical walk order: class attributes first, then
// constants, then properties, then methods (including parameters).
$ids = array_map(
    static fn ($c) => (new ReflectionFunction($c))->getConstExprId(),
    $closures
);
var_dump($ids);

?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
array(7) {
  ["class"]=>
  int(0)
  ["const"]=>
  int(1)
  ["prop-1"]=>
  int(2)
  ["prop-2"]=>
  int(3)
  ["method"]=>
  int(4)
  ["param"]=>
  int(5)
  ["case"]=>
  int(0)
}
