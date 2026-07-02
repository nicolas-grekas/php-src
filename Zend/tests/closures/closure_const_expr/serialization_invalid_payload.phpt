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

$mk = static fn (string $class, string $id, int $line) => sprintf(
    'O:7:"Closure":3:{s:5:"class";s:%d:"%s";s:2:"id";s:%d:"%s";s:4:"line";i:%d;}',
    strlen($class), $class, strlen($id), $id, $line
);

// Sanity check: a valid reference works.
var_dump(unserialize($mk('Demo', $id, $line))());

$payloads = [
    'empty data' => 'O:7:"Closure":0:{}',
    'missing keys' => 'O:7:"Closure":1:{s:5:"class";s:4:"Demo";}',
    'wrong types' => 'O:7:"Closure":3:{s:5:"class";s:4:"Demo";s:2:"id";i:0;s:4:"line";i:1;}',
    'unknown class' => $mk('NoSuchClass', $id, $line),
    'internal class' => $mk('stdClass', $id, $line),
    'unknown site' => $mk('Demo', '$nope@0', $line),
    'malformed id' => $mk('Demo', 'no-at-sign', $line),
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
unknown site: Invalid serialization data for Closure object (constant-expression closure "$nope@0" of class Demo not found)
malformed id: Invalid serialization data for Closure object (constant-expression closure "no-at-sign" of class Demo not found)
stale line: Invalid serialization data for Closure object (constant-expression closure "$p@0" of class Demo not found)
Cannot unserialize an already initialized Closure
