<?php
require_once __DIR__ . '/../lib/password.php';

assert(password_strength('') === 0, 'empty → 0');
assert(password_strength('abc') === 0, 'too short → 0');
assert(password_strength('aaaaaaaa') === 1, 'lowercase only → 1 class');
assert(password_strength('Aaaaaaaa') === 2, 'lower+upper → 2 classes');
assert(password_strength('Aaaaaaa1') === 3, 'lower+upper+digit → 3 classes');
assert(password_strength('Password1!') === 4, 'all 4 classes → 4');
assert(password_strength('VeryStrongPwd123!') === 4, 'capped at 4');

assert(password_strength_label(0) === 'Очень слабый');
assert(password_strength_label(4) === 'Отличный');

assert(password_strength_color(0) === 'bg-danger');
assert(password_strength_color(4) === 'bg-success');

$hash = password_make_hash('Password1!');
assert(str_starts_with($hash, '$2y$12$'), 'bcrypt cost=12');
assert(password_verify('Password1!', $hash), 'verify own hash');

echo "Password smoke: OK\n";
