<?php
require_once __DIR__ . '/../lib/mailer.php';

$logFile = __DIR__ . '/../data/mail.log';
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
}

$ok = Mailer::send('test@example.com', 'Test subject', 'Test body line 1');
assert($ok === true, 'Mailer::send must return true in fallback mode');
assert(file_exists($logFile), 'data/mail.log must exist after send');

$contents = file_get_contents($logFile);
assert(str_contains($contents, 'TO: test@example.com'), 'log must contain TO');
assert(str_contains($contents, 'SUBJECT: Test subject'), 'log must contain SUBJECT');
assert(str_contains($contents, 'Test body line 1'), 'log must contain body');

echo "Mailer smoke: OK\n";
