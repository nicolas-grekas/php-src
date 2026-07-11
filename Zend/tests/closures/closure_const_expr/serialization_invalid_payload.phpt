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
$id = (new ReflectionFunction($closure))->getConstExprId();
// The stored line is relative to the declaring class.
$rel = (new ReflectionFunction($closure))->getStartLine() - (new ReflectionClass(Demo::class))->getStartLine();

// Serialize an array and rebrand it as a Closure object payload, so we can
// feed __unserialize() arbitrary tagged-union shapes.
$obj = static fn (array $data): string => 'O:7:"Closure":' . substr(serialize($data), 2);

// Tagged-union payload: [ [], ["const-expr", [class, id, line?]] ].
$mk = static function (string $class, string $id, ?int $line) use ($obj): string {
    $payload = $line === null ? [$class, $id] : [$class, $id, $line];
    return $obj([[], ['const-expr', $payload]]);
};

// Sanity check: a valid reference works.
var_dump(unserialize($mk('Demo', $id, $rel))());

$payloads = [
    'empty data' => $obj([]),
    'one element' => $obj([[]]),
    'unknown tag' => $obj([[], ['whatever', ['Demo', $id, $rel]]]),
    'tag not list' => $obj([[], 'const-expr']),
    'wrong id type' => $obj([[], ['const-expr', ['Demo', 0, $rel]]]),
    'unknown class' => $mk('NoSuchClass', $id, $rel),
    'internal class' => $mk('stdClass', $id, $rel),
    'unknown site' => $mk('Demo', '$nope@0', $rel),
    'malformed id' => $mk('Demo', 'no-at-sign', $rel),
    'anon missing line' => $mk('Demo', $id, null),
    'stale line' => $mk('Demo', $id, $rel + 1),
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
    $closure->__unserialize([[], ['const-expr', ['Demo', $id, $rel]]]);
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
string(2) "ok"
empty data: Invalid serialization data for Closure object
one element: Invalid serialization data for Closure object
unknown tag: Invalid serialization data for Closure object
tag not list: Invalid serialization data for Closure object
wrong id type: Invalid serialization data for Closure object
unknown class: Invalid serialization data for Closure object (cannot load class "NoSuchClass")
internal class: Invalid serialization data for Closure object (cannot load class "stdClass")
unknown site: Invalid serialization data for Closure object (constant-expression closure "$nope@0" of class Demo not found)
malformed id: Invalid serialization data for Closure object (constant-expression closure "no-at-sign" of class Demo not found)
anon missing line: Invalid serialization data for Closure object
stale line: Invalid serialization data for Closure object (constant-expression closure "$p@0" of class Demo not found)
Cannot unserialize an already initialized Closure
