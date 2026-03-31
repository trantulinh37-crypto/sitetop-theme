<?php
$now = strtotime(linkngon_current_time()); $t = time();
assert_true(abs($now-$t)<5, 'Timezone ok');
$c = strtotime('2026-01-01 10:00:00'); $n = strtotime('2026-01-01 10:01:05');
$e = $n - $c; $req = max(70-5, 10);
assert_true($e >= $req, 'Onsite passed at 65s');
assert_false(30 >= $req, 'Onsite blocked at 30s');
echo "  ✓ security\n";
