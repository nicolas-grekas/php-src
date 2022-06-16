--TEST--
Clone readonly class
--FILE--
<?php

readonly class Test {
    public int $prop;

    public function __construct(int $prop) {
        $this->prop = $prop;
    }

    public function withProp(int $prop) {
        $clone = clone $this;
        unset($clone->prop);
        $clone->prop = $prop;

        return $clone;
    }
}

$test = new Test(1);
$test2 = $test->withProp(2);

var_dump($test->prop);
var_dump($test2->prop);

?>
--EXPECT--
int(1)
int(2)
