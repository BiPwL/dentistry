<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/appointment.php';

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

$patient   = Auth::user();
$patientId = (int) $patient['id'];

if (!empty($patient['booking_ban_until']) && $patient['booking_ban_until'] >= date('Y-m-d')) {
    echo json_encode(['ok' => false, 'error' => 'Онлайн-запись временно ограничена до ' . $patient['booking_ban_until'] . ' из-за неявки.']);
    exit;
}

$doctorId  = (int) ($_POST['doctor_id'] ?? 0);
$slotStart = trim((string) ($_POST['slot_start'] ?? ''));

if (DB::one('SELECT id FROM user WHERE id = :id AND role_id = 3', ['id' => $doctorId]) === null) {
    echo json_encode(['ok' => false, 'error' => 'Врач не найден.']);
    exit;
}
if (!is_valid_slot($slotStart)) {
    echo json_encode(['ok' => false, 'error' => 'Недопустимое время записи.']);
    exit;
}
if (DB::one('SELECT id FROM appointment WHERE doctor_id = :d AND slot_start = :s', ['d' => $doctorId, 's' => $slotStart]) !== null) {
    echo json_encode(['ok' => false, 'error' => 'Это время уже занято. Выберите другое.']);
    exit;
}

try {
    DB::exec(
        'INSERT INTO appointment (patient_id, doctor_id, slot_start, slot_end, status)
         VALUES (:p, :d, :s, :e, \'created\')',
        ['p' => $patientId, 'd' => $doctorId, 's' => $slotStart, 'e' => slot_end_for($slotStart)]
    );
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Это время уже занято. Выберите другое.']);
    exit;
}

echo json_encode(['ok' => true, 'appointment_id' => DB::lastId()]);
