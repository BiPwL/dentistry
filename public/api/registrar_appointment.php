<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }

$apptId = (int) ($_GET['id'] ?? 0);
$a = DB::one(
    "SELECT a.id, a.slot_start, a.status, p.last_name, p.first_name, p.middle_name
       FROM appointment a JOIN user p ON p.id = a.patient_id WHERE a.id = :id",
    ['id' => $apptId]
);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }

$services = [];
$total = 0.0;
foreach (DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id'=>$apptId]) as $s) {
    $services[] = ['name' => $s['name'], 'price' => fmt_price($s['price_at_time'])];
    $total += (float) $s['price_at_time'];
}
$payment = DB::one("SELECT method FROM payment WHERE appointment_id = :id", ['id'=>$apptId]);

echo json_encode([
    'ok' => true,
    'appointment' => [
        'id'           => (int) $a['id'],
        'patient_fio'  => $a['last_name'].' '.$a['first_name'].' '.$a['middle_name'],
        'status'       => $a['status'],
        'status_label' => appt_status_label($a['status']),
        'when'         => fmt_dt($a['slot_start']),
        'total'        => fmt_price($total),
        'can_pay'      => ($a['status'] === 'performed' && $payment === null),
        'paid'         => $payment !== null,
        'pay_method'   => $payment['method'] ?? null,
    ],
    'services' => $services,
], JSON_UNESCAPED_UNICODE);
