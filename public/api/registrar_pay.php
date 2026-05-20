<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::hasRole('registrar')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Доступ запрещён.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный запрос.']); exit; }

$apptId = (int) ($_POST['appointment_id'] ?? 0);
$method = (string) ($_POST['method'] ?? '');
if (!in_array($method, ['cash','card'], true)) { echo json_encode(['ok'=>false,'error'=>'Выберите способ оплаты.']); exit; }

$a = DB::one("SELECT id, status FROM appointment WHERE id = :id", ['id'=>$apptId]);
if ($a === null) { echo json_encode(['ok'=>false,'error'=>'Запись не найдена.']); exit; }
if ($a['status'] !== 'performed') { echo json_encode(['ok'=>false,'error'=>'Оплата возможна только для исполненной записи.']); exit; }
if (DB::one("SELECT id FROM payment WHERE appointment_id = :id", ['id'=>$apptId]) !== null) { echo json_encode(['ok'=>false,'error'=>'Запись уже оплачена.']); exit; }

$total = (float) (DB::one("SELECT COALESCE(SUM(price_at_time),0) AS t FROM appointment_service WHERE appointment_id = :id", ['id'=>$apptId])['t'] ?? 0);

$methodId = ($method === 'cash') ? 1 : 2;

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    DB::exec("INSERT INTO payment (appointment_id, method_id, total_amount) VALUES (:a,:m,:t)", ['a'=>$apptId,'m'=>$methodId,'t'=>$total]);
    DB::exec("UPDATE appointment SET status = 'completed' WHERE id = :id", ['id'=>$apptId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>'Не удалось провести оплату.']); exit;
}
echo json_encode(['ok'=>true, 'receipt_url' => '/receipt.php?appointment_id=' . $apptId]);
