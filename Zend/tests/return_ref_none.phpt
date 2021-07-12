--TEST--
Argument-less return from by-ref function
--FILE--
<?php

function &test() {
    return;
}

$ref =& test();

?>
--EXPECTF--
