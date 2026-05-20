<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Доступ только для пациентов.']);
    exit;
}

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$doctor = DB::one('SELECT id FROM user WHERE id = :id AND role_id = 3', ['id' => $doctorId]);
if ($doctor === null) {
    echo json_encode(['ok' => false, 'error' => 'Врач не найден.']);
    exit;
}

$dates = booking_dates(14);
if (empty($dates)) {
    echo json_encode(['ok' => true, 'days' => []]);
    exit;
}
$from = $dates[0] . ' 00:00:00';
$to   = end($dates) . ' 23:59:59';

$rows = DB::all(
    'SELECT slot_start FROM appointment WHERE doctor_id = :d AND slot_start BETWEEN :f AND :t',
    ['d' => $doctorId, 'f' => $from, 't' => $to]
);
$booked = [];
foreach ($rows as $r) {
    $booked[date('Y-m-d H:i:s', strtotime($r['slot_start']))] = true;
}

$days = [];
foreach ($dates as $date) {
    if (doctor_is_off($doctorId, $date)) continue;
    $slots = [];
    foreach (slot_hours() as $h) {
        $start = sprintf('%s %02d:00:00', $date, $h);
        if (strtotime($start) <= time()) continue;
        if (isset($booked[$start])) continue;
        $slots[] = ['start' => $start, 'label' => slot_label($h)];
    }
    if (!empty($slots)) {
        $days[] = ['date' => $date, 'label' => ru_date_label($date), 'slots' => $slots];
    }
}

echo json_encode(['ok' => true, 'days' => $days]);
