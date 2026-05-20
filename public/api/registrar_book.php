<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/appointment.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$doctorId  = (int) ($_POST['doctor_id'] ?? 0);
$patientId = (int) ($_POST['patient_id'] ?? 0);
$slot      = (string) ($_POST['slot_start'] ?? '');

$doc = DB::one("SELECT id FROM user WHERE id = :id AND role_id = 3", ['id' => $doctorId]);
$pat = DB::one("SELECT id FROM user WHERE id = :id AND role_id = 4", ['id' => $patientId]);
if ($doc === null || $pat === null) { echo json_encode(['ok'=>false,'error'=>'Врач или пациент не найден.']); exit; }
if (!is_valid_slot($slot)) { echo json_encode(['ok'=>false,'error'=>'Недопустимый слот.']); exit; }

$taken = DB::one("SELECT id FROM appointment WHERE doctor_id = :d AND slot_start = :s", ['d'=>$doctorId,'s'=>$slot]);
if ($taken !== null) { echo json_encode(['ok'=>false,'error'=>'Слот уже занят.']); exit; }

DB::exec(
    "INSERT INTO appointment (patient_id, doctor_id, slot_start, slot_end, status) VALUES (:p,:d,:s,:e,'created')",
    ['p'=>$patientId,'d'=>$doctorId,'s'=>$slot,'e'=>slot_end_for($slot)]
);
echo json_encode(['ok'=>true]);
