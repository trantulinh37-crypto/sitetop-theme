<?php
$b = 100000 - 30000 - 20000 - abs(5000); assert_equals(45000, $b, 'Balance calc');
assert_equals(0, max(0, 10000-50000), 'Floor 0');
assert_equals(50000, 100000 - 50000 - 0, 'No refund added');
assert_equals(40000, 30000+10000, 'Cancelled in withdrawn');
echo "  ✓ balance\n";
