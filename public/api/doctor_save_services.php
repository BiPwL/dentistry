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

$appt = DB::one('SELECT id FROM appointment WHERE id = :id AND doctor_id = :d', ['id' => $apptId, 'd' => $docId]);
if ($appt === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена.']);
    exit;
}

$ids = $_POST['service_ids'] ?? [];
if (!is_array($ids)) $ids = [];
$ids = array_values(array_unique(array_map('intval', $ids)));

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    DB::exec('DELETE FROM appointment_service WHERE appointment_id = :id', ['id' => $apptId]);
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $valid = DB::all("SELECT id, price FROM service WHERE id IN ($in)", $ids);
        foreach ($valid as $s) {
            DB::exec(
                'INSERT INTO appointment_service (appointment_id, service_id, price_at_time) VALUES (:a, :s, :p)',
                ['a' => $apptId, 's' => (int) $s['id'], 'p' => $s['price']]
            );
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить услуги.']);
    exit;
}

echo json_encode(['ok' => true]);
