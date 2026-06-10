--TEST--
Serializable closures are gated by the unserialize() allowed_classes filter
--FILE--
<?php

#[Attribute(Attribute::TARGET_ALL)]
class A {
    public function __construct(public mixed $cb = null) {}
}

class Demo {
    #[A(static function () { return 'ok'; })]
    #[A(strlen(...))]
    public int $p = 0;
}

$attrs = (new ReflectionProperty(Demo::class, 'p'))->getAttributes();
$payloads = [
    'anonymous' => serialize($attrs[0]->getArguments()[0]),
    'fcc site' => serialize($attrs[1]->getArguments()[0]),
];

// The recommended safe-unserialize practice (allowed_classes => false) blocks
// every Closure payload: it becomes __PHP_Incomplete_Class and __unserialize()
// is never invoked, exactly like any other object-injection gadget.
foreach ($payloads as $name => $payload) {
    $r = unserialize($payload, ['allowed_classes' => false]);
    var_dump($name, $r instanceof __PHP_Incomplete_Class);
}

// A list that does not contain Closure also blocks it.
$r = unserialize($payloads['fcc site'], ['allowed_classes' => ['stdClass']]);
var_dump($r instanceof __PHP_Incomplete_Class);

// Closure must be explicitly opted in.
$r = unserialize($payloads['fcc site'], ['allowed_classes' => ['Closure']]);
var_dump($r instanceof Closure, $r('test'));

?>
--EXPECT--
string(9) "anonymous"
bool(true)
string(8) "fcc site"
bool(true)
bool(true)
bool(true)
int(4)
