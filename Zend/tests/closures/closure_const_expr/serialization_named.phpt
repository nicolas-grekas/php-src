--TEST--
First-class callable references in constant expressions serialize as declaration sites
--FILE--
<?php

namespace App {
    function helper(): string { return 'ns-helper'; }
}

namespace {

#[Attribute(Attribute::TARGET_ALL)]
class A {
    public function __construct(public mixed $cb = null) {}
}

class Validators {
    public static function check(): string { return 'cross-class'; }
}

class Base {
    protected static function prot(): string { return 'prot-' . static::class; }
}

class Demo extends Base {
    #[A(self::priv(...))]
    #[A(self::prot(...))]
    #[A(parent::prot(...))]
    #[A(Validators::check(...))]
    #[A(strlen(...))]
    #[A(App\helper(...))]
    public int $p = 0;

    private static function priv(): string { return 'priv'; }
}

$attrs = (new ReflectionProperty(Demo::class, 'p'))->getAttributes();
foreach ($attrs as $i => $attr) {
    $closure = $attr->getArguments()[0];
    $payload = serialize($closure);
    $u = unserialize($payload);
    $args = $i === 4 ? ['abcd'] : [];
    // The payload references the declaring class, ids are stable, and the
    // recreated closure behaves identically (including static:: binding).
    var_dump(
        str_contains($payload, '"Demo"')
        && $u(...$args) === $closure(...$args)
        && serialize($u) === $payload
    );
}

// parent:: and self:: forms resolve their distinct static:: bindings.
var_dump(unserialize(serialize($attrs[1]->getArguments()[0]))());
var_dump(unserialize(serialize($attrs[2]->getArguments()[0]))());

// Runtime-created named closures are not declaration sites and refuse,
// even when an identical reference exists in an attribute.
foreach ([strlen(...), Validators::check(...), Closure::fromCallable('strlen')] as $closure) {
    try {
        serialize($closure);
        echo "serialized!?\n";
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
    }
}

// The name-based payload format does not exist: only site references resolve.
foreach ([
    'O:7:"Closure":1:{s:8:"function";s:6:"strlen";}',
    'O:7:"Closure":2:{s:5:"class";s:10:"Validators";s:6:"method";s:5:"check";}',
] as $payload) {
    try {
        unserialize($payload);
        echo "unserialized!?\n";
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
    }
}

}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(9) "prot-Demo"
string(9) "prot-Base"
Serialization of 'Closure' is not allowed
Serialization of 'Closure' is not allowed
Serialization of 'Closure' is not allowed
Invalid serialization data for Closure object
Invalid serialization data for Closure object
