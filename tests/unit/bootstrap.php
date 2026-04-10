<?php
if (!function_exists('current_time')) { function current_time($f){return date($f);} }
function traffictop_current_time(){return date('Y-m-d H:i:s');}
$GLOBALS['test_results'] = array('passed'=>0,'failed'=>0,'errors'=>array());
function assert_equals($e,$a,$m=''){if($e==$a)$GLOBALS['test_results']['passed']++;else{$GLOBALS['test_results']['failed']++;$GLOBALS['test_results']['errors'][]=$m." (expected: $e, got: $a)";}}
function assert_true($v,$m=''){assert_equals(true,(bool)$v,$m);}
function assert_false($v,$m=''){assert_equals(false,(bool)$v,$m);}
