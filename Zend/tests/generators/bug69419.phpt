--TEST--
Bug #69419: Returning compatible sub generator produces no warning
--FILE--
<?php

function & genRefInner() {
    $var = 1;
    yield $var;
}

function & genRefOuter() {
    return genRefInner();
}

foreach(genRefOuter() as $i) {
    var_dump($i);
}

?>
--EXPECTF--
int(1)
