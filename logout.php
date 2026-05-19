<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/csrf.php';

// Только POST, защищённый CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /');
    exit;
}
Csrf::requireValid();
Session::logout();
header('Location: /');
exit;
