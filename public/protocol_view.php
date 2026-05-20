<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user   = Auth::user();
$uid    = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id, a.doctor_id,
            pr.protocol_text, pr.recommendations,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) { http_response_code(404); echo 'Протокол не найден.'; exit; }
$allowed = ($uid === (int) $row['doctor_id']) || ($uid === (int) $row['patient_id']) || ($user['role_code'] === 'admin');
if (!$allowed) { http_response_code(403); echo 'Нет доступа.'; exit; }

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id' => $apptId]);

$patientFio = $row['p_last'].' '.$row['p_first'].' '.$row['p_mid'];
$doctorFio  = $row['d_last'].' '.$row['d_first'].' '.$row['d_mid'];

$body  = '<h1>Протокол приёма</h1>';
$body .= '<div class="row"><span class="lbl">Клиника:</span> ' . h(SITE_NAME) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($patientFio) . '</div>';
$body .= '<div class="row"><span class="lbl">Врач:</span> ' . h($doctorFio) . '</div>';
$body .= '<div class="row"><span class="lbl">Дата приёма:</span> ' . h(fmt_dt($row['slot_start'])) . '</div>';
if (!empty($services)) {
    $body .= '<h2>Оказанные услуги</h2><table><tr><th>Услуга</th><th class="right">Стоимость</th></tr>';
    foreach ($services as $s) $body .= '<tr><td>' . h($s['name']) . '</td><td class="right">' . h(fmt_price($s['price_at_time'])) . '</td></tr>';
    $body .= '</table>';
}
$body .= '<h2>Протокол</h2><div>' . nl2br(h($row['protocol_text'])) . '</div>';
if (!empty($row['recommendations'])) {
    $body .= '<h2>Рекомендации</h2><div>' . nl2br(h($row['recommendations'])) . '</div>';
}

pdf_render_inline(pdf_document($body, 'Протокол приёма'), 'protocol_' . $apptId . '.pdf');
