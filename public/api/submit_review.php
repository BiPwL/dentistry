<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');
if (!Auth::hasRole('patient')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ только для пациентов.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$pid    = (int) Auth::user()['id'];
$apptId = (int) ($_POST['appointment_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$body   = trim((string) ($_POST['body'] ?? ''));
if ($rating < 1 || $rating > 5) { echo json_encode(['ok'=>false,'error'=>'Оценка 1–5.']); exit; }

$a = DB::one("SELECT doctor_id FROM appointment WHERE id=:id AND patient_id=:p AND status='completed'", ['id'=>$apptId,'p'=>$pid]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена или не завершена.']); exit; }
if (DB::one("SELECT id FROM review WHERE appointment_id=:id", ['id'=>$apptId]) !== null) { echo json_encode(['ok'=>false,'error'=>'Отзыв уже оставлен.']); exit; }

DB::exec("INSERT INTO review (appointment_id, patient_id, doctor_id, rating, body) VALUES (:a,:p,:d,:r,:b)",
    ['a'=>$apptId,'p'=>$pid,'d'=>(int)$a['doctor_id'],'r'=>$rating,'b'=>($body !== '' ? $body : null)]);
echo json_encode(['ok'=>true]);
