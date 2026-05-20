<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user = Auth::user();
$uid  = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id,
            pm.name AS method_label, pay.total_amount, pay.paid_at,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN payment pay ON pay.appointment_id = a.id
       JOIN payment_method pm ON pm.id = pay.method_id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) { http_response_code(404); echo 'Чек не найден.'; exit; }
$allowed = ($user['role_code'] === 'registrar') || ($user['role_code'] === 'admin') || ($uid === (int) $row['patient_id']);
if (!$allowed) { http_response_code(403); echo 'Нет доступа.'; exit; }

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id' => $apptId]);
$methodLabel = $row['method_label'];

$body  = '<h1>Кассовый чек</h1>';
$body .= '<div class="row tot">' . h(SITE_NAME) . '</div>';
$body .= '<div class="row muted">' . h(CLINIC_ADDRESS) . '</div>';
$body .= '<div class="row muted">тел. ' . h(CLINIC_PHONE) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($row['p_last'].' '.$row['p_first'].' '.$row['p_mid']) . '</div>';
$body .= '<div class="row"><span class="lbl">Врач:</span> ' . h($row['d_last'].' '.$row['d_first'].' '.$row['d_mid']) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата приёма:</span> ' . h(fmt_dt($row['slot_start'])) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата оплаты:</span> ' . h(fmt_dt($row['paid_at'])) . '</div>';
$body .= '<div class="row"><span class="lbl">Способ оплаты:</span> ' . h($methodLabel) . '</div>';
$body .= '<table><tr><th>Услуга</th><th class="right">Стоимость</th></tr>';
foreach ($services as $s) $body .= '<tr><td>' . h($s['name']) . '</td><td class="right">' . h(fmt_price($s['price_at_time'])) . '</td></tr>';
$body .= '<tr class="tot"><td>Итого</td><td class="right">' . h(fmt_price($row['total_amount'])) . '</td></tr></table>';

pdf_render_inline(pdf_document($body, 'Чек'), 'receipt_' . $apptId . '.pdf');
