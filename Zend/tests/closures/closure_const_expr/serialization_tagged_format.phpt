--TEST--
Closure serialization payload is a tagged union with a class-relative line
--FILE--
<?php

#[Attribute(Attribute::TARGET_ALL)]
class A {
    public function __construct(public mixed $cb = null) {}
}

class Demo {
    #[A(static function () { return 'anon'; })]
    #[A(strlen(...))]
    public int $p = 0;
}

$attrs = (new ReflectionProperty(Demo::class, 'p'))->getAttributes();
$anon = $attrs[0]->getArguments()[0];
$fcc = $attrs[1]->getArguments()[0];

// The payload is [ <object properties>, [ <tag>, <tag payload> ] ]. The
// properties slot is empty, the tag is "const-expr". An anonymous closure
// carries [class, id, line]; a first-class callable carries just [class, id].
var_dump($anon->__serialize());
var_dump($fcc->__serialize());

// The line is relative to the declaring class, so it equals the offset of the
// closure from the class declaration, not an absolute line number.
$relLine = $anon->__serialize()[1][1][2];
$expected = (new ReflectionFunction($anon))->getStartLine() - (new ReflectionClass(Demo::class))->getStartLine();
var_dump($relLine === $expected);

// Because the line is class-relative, the same class declared at a different
// offset in the file (here, five blank lines earlier) produces the identical
// reference: an edit above the class does not invalidate stored payloads.
eval("\n\n\n\n\n" . 'class Shifted {
    #[A(static function () { return "anon"; })]
    public int $p = 0;
}');
$shifted = (new ReflectionProperty('Shifted', 'p'))->getAttributes()[0]->getArguments()[0];
var_dump($shifted->__serialize()[1][1][2] === $relLine);

?>
--EXPECT--
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(2) {
    [0]=>
    string(10) "const-expr"
    [1]=>
    array(3) {
      [0]=>
      string(4) "Demo"
      [1]=>
      string(4) "$p@0"
      [2]=>
      int(1)
    }
  }
}
array(2) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(2) {
    [0]=>
    string(10) "const-expr"
    [1]=>
    array(2) {
      [0]=>
      string(4) "Demo"
      [1]=>
      string(9) "$p@strlen"
    }
  }
}
bool(true)
bool(true)
