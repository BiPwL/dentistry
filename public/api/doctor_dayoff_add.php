<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
Auth::requireRole('doctor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Location: /profile.php'); exit; }
Csrf::requireValid();
$did = (int) Auth::user()['id'];
$date = trim((string)($_POST['off_date'] ?? ''));
$reason = trim((string)($_POST['reason'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    try { DB::exec("INSERT INTO doctor_day_off (doctor_id, off_date, reason) VALUES (:d,:dt,:r)", ['d'=>$did,'dt'=>$date,'r'=>($reason!==''?$reason:null)]); }
    catch (Throwable $e) { /* дубликат — игнор */ }
}
header('Location: /profile.php'); exit;
