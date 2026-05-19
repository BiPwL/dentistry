<?php
require_once __DIR__ . '/../lib/db.php';

$roles = DB::all('SELECT * FROM role ORDER BY id');
assert(count($roles) === 4, 'Expected 4 roles');

$admin = DB::one('SELECT * FROM user WHERE role_id = 1');
assert($admin !== null, 'Admin must exist');
assert($admin['email'] === 'admin@dentistry.local', 'Admin email mismatch');

echo "DB smoke: OK\n";
