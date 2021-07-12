--TEST--
Assigning the result of a non-reference function by-reference should not leak
--FILE--
<?php

function func() {
    return [0];
}

$x = $y =& func();
var_dump($x, $y);

?>
--EXPECTF--
array(1) {
  [0]=>
  int(0)
}
array(1) {
  [0]=>
  int(0)
}
