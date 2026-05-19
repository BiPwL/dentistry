<?php
require_once __DIR__ . '/../lib/email_code.php';

$email   = 'codecheck@example.com';
$purpose = 'register';

DB::exec('DELETE FROM email_code WHERE email = :e', ['e' => $email]);

$payload = ['last_name' => 'Тест', 'pwd' => 'hash'];
$code = EmailCode::issue($email, $purpose, $payload);
assert(strlen($code) === 6, 'Code must be 6 chars');
assert(ctype_digit($code), 'Code must be all digits');

$row = EmailCode::find($email, $purpose, $code);
assert($row !== null, 'Issued code must be found');
assert($row['email'] === $email);
assert($row['purpose'] === $purpose);

assert(EmailCode::find($email, $purpose, '000000') === null || $code === '000000', 'Wrong code must not be found');

$out = EmailCode::consume($email, $purpose, $code);
assert($out !== null, 'Consume must return payload');
assert($out['last_name'] === 'Тест', 'Payload must round-trip');
assert(EmailCode::find($email, $purpose, $code) === null, 'After consume, code must be gone');

assert(EmailCode::consume($email, $purpose, $code) === null, 'Second consume must return null');

EmailCode::purgeExpired();

echo "EmailCode smoke: OK\n";
