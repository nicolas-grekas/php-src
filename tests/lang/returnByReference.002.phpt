--TEST--
Returning a reference from a function.
--FILE--
<?php
function &returnRef() {
        global $a;
        return $a;
}

function returnVal() {
        global $a;
        return $a;
}

$a = "original";
$b =& returnVal();
$b = "changed";
var_dump($a); //expecting no warning + "original"

$a = "original";
$b =& returnRef();
$b = "changed";
var_dump($a); //expecting "changed"
?>
--EXPECTF--
string(8) "original"
string(7) "changed"
