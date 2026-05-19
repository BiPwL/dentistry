<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для пациентов.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос.']);
    exit;
}

$patientId = (int) Auth::user()['id'];
$apptId    = (int) ($_POST['appointment_id'] ?? 0);

$appt = DB::one(
    "SELECT id FROM appointment WHERE id = :id AND patient_id = :p AND status = 'created'",
    ['id' => $apptId, 'p' => $patientId]
);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись нельзя отменить.']);
    exit;
}

DB::exec('DELETE FROM appointment WHERE id = :id', ['id' => $apptId]);
echo json_encode(['ok' => true]);
