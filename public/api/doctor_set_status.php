<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('doctor')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для врачей.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_POST['appointment_id'] ?? 0);

$appt = DB::one(
    "SELECT id FROM appointment WHERE id = :id AND doctor_id = :d AND status = 'confirmed'",
    ['id' => $apptId, 'd' => $docId]
);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Статус нельзя изменить.']);
    exit;
}

DB::exec("UPDATE appointment SET status = 'performed' WHERE id = :id", ['id' => $apptId]);
echo json_encode(['ok' => true]);
