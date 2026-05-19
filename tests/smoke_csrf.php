<?php
require_once __DIR__ . '/../lib/csrf.php';

$t1 = Csrf::token();
$t2 = Csrf::token();
assert($t1 === $t2, 'Token must be stable within session');
assert(strlen($t1) === 64, 'Token must be 64 hex chars');
assert(Csrf::check($t1) === true, 'Valid token must pass');
assert(Csrf::check('wrong') === false, 'Invalid token must fail');
assert(Csrf::check(null) === false, 'Null token must fail');

echo "CSRF smoke: OK\n";
