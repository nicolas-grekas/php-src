--TEST--
Unserializing invalid or stale Closure declaration-site references
--FILE--
<?php

#[Attribute(Attribute::TARGET_ALL)]
class A {
    public function __construct(public mixed $cb = null) {}
}

class Demo {
    #[A(static function () { return 'ok'; })]
    public int $p = 0;
}

$closure = (new ReflectionProperty(Demo::class, 'p'))->getAttributes()[0]->getArguments()[0];
$r = new ReflectionFunction($closure);
$id = $r->getConstExprId();
$line = $r->getStartLine();

$mk = static fn (string $class, int $id, int $line) => sprintf(
    'O:7:"Closure":3:{s:5:"class";s:%d:"%s";s:2:"id";i:%d;s:4:"line";i:%d;}',
    strlen($class), $class, $id, $line
);

// Sanity check: a valid reference works.
var_dump(unserialize($mk('Demo', $id, $line))());

$payloads = [
    'empty data' => 'O:7:"Closure":0:{}',
    'missing keys' => 'O:7:"Closure":1:{s:5:"class";s:4:"Demo";}',
    'wrong types' => 'O:7:"Closure":3:{s:5:"class";s:4:"Demo";s:2:"id";s:1:"0";s:4:"line";i:1;}',
    'unknown class' => $mk('NoSuchClass', $id, $line),
    'internal class' => $mk('stdClass', $id, $line),
    'unknown id' => $mk('Demo', 999, $line),
    'negative id' => $mk('Demo', -1, $line),
    'stale line' => $mk('Demo', $id, $line + 1),
];

foreach ($payloads as $name => $payload) {
    try {
        unserialize($payload);
        echo "$name: unserialized!?\n";
    } catch (Exception $e) {
        echo "$name: {$e->getMessage()}\n";
    }
}

// __unserialize() cannot be used to reinitialize a live closure.
try {
    $closure->__unserialize(['class' => 'Demo', 'id' => $id, 'line' => $line]);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
string(2) "ok"
empty data: Invalid serialization data for Closure object
missing keys: Invalid serialization data for Closure object
wrong types: Invalid serialization data for Closure object
unknown class: Invalid serialization data for Closure object (cannot load class "NoSuchClass")
internal class: Invalid serialization data for Closure object (cannot load class "stdClass")
unknown id: Invalid serialization data for Closure object (constant-expression closure 999 of class Demo not found)
negative id: Invalid serialization data for Closure object (constant-expression closure -1 of class Demo not found)
stale line: Invalid serialization data for Closure object (constant-expression closure 0 of class Demo not found)
Cannot unserialize an already initialized Closure
