<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$apptId = (int) ($_POST['appointment_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
if (!in_array($status, ['created','confirmed','performed','noshow'], true)) { echo json_encode(['ok'=>false,'error'=>'Недопустимый статус.']); exit; }

$a = DB::one("SELECT id FROM appointment WHERE id = :id", ['id'=>$apptId]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }

DB::exec("UPDATE appointment SET status = :st WHERE id = :id", ['st'=>$status,'id'=>$apptId]);
echo json_encode(['ok'=>true]);
