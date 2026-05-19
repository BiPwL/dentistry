<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('doctor')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для врачей.']);
    exit;
}

$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_GET['id'] ?? 0);

$a = DB::one(
    "SELECT a.id, a.slot_start, a.status,
            p.last_name, p.first_name, p.middle_name
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
      WHERE a.id = :id AND a.doctor_id = :d",
    ['id' => $apptId, 'd' => $docId]
);
if ($a === null) {
    echo json_encode(['ok' => false, 'error' => 'Запись не найдена.']);
    exit;
}

$provided = [];
foreach (DB::all('SELECT service_id FROM appointment_service WHERE appointment_id = :id', ['id' => $apptId]) as $r) {
    $provided[(int) $r['service_id']] = true;
}
$services = [];
foreach (DB::all('SELECT id, name, price FROM service ORDER BY name') as $s) {
    $services[] = [
        'id'      => (int) $s['id'],
        'name'    => $s['name'],
        'price'   => fmt_price($s['price']),
        'checked' => isset($provided[(int) $s['id']]),
    ];
}

$hasProtocol = DB::one('SELECT id FROM protocol WHERE appointment_id = :id', ['id' => $apptId]) !== null;

echo json_encode([
    'ok' => true,
    'appointment' => [
        'id'            => (int) $a['id'],
        'patient_fio'   => $a['last_name'] . ' ' . $a['first_name'] . ' ' . $a['middle_name'],
        'status'        => $a['status'],
        'status_label'  => appt_status_label($a['status']),
        'when'          => fmt_dt($a['slot_start']),
        'can_perform'   => $a['status'] === 'confirmed',
        'has_protocol'  => $hasProtocol,
    ],
    'services' => $services,
], JSON_UNESCAPED_UNICODE);
