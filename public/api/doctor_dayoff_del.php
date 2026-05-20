<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
Auth::requireRole('doctor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();
$did = (int) Auth::user()['id'];
DB::exec("DELETE FROM doctor_day_off WHERE id=:id AND doctor_id=:d", ['id'=>(int)($_POST['id'] ?? 0),'d'=>$did]);
header('Location: /profile.php'); exit;
