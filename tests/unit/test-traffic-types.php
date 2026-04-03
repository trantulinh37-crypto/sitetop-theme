<?php
$valid = array('keyword_search','traffic_direct');
assert_false(in_array('traffic_social', $valid), 'No social V2');
assert_true(in_array('keyword_search', $valid), 'Keyword ok');
assert_true(in_array('traffic_direct', $valid), 'Direct ok');
$x = null; $t = isset($x) ? $x : '1step'; assert_equals('1step', $t, 'Default type');
echo "  ✓ traffic\n";
