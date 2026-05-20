<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/pdf.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user = Auth::user();
$uid  = (int) $user['id'];
$role = $user['role_code'];

$patientId = $uid;
if ($role !== 'patient') {
    $patientId = (int) ($_GET['patient_id'] ?? 0);
    if ($role !== 'admin' && $role !== 'doctor' && $role !== 'registrar') { http_response_code(403); echo 'Нет доступа.'; exit; }
}

$patient = DB::one("SELECT last_name, first_name, middle_name FROM user WHERE id = :id AND role_id = 4", ['id' => $patientId]);
if ($patient === null) { http_response_code(404); echo 'Пациент не найден.'; exit; }
$patientFio = $patient['last_name'].' '.$patient['first_name'].' '.$patient['middle_name'];

$protocols = DB::all(
    "SELECT a.id AS appt_id, a.slot_start, pr.protocol_text, pr.recommendations,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.patient_id = :pid
      ORDER BY a.slot_start DESC",
    ['pid' => $patientId]
);

$body  = '<h1>Медицинская карта</h1>';
$body .= '<div class="row"><span class="lbl">Клиника:</span> ' . h(SITE_NAME) . '</div>';
$body .= '<div class="row"><span class="lbl">Пациент:</span> ' . h($patientFio) . '</div>';
if (empty($protocols)) {
    $body .= '<p class="muted">Протоколов приёма пока нет.</p>';
} else {
    foreach ($protocols as $p) {
        $docFio = $p['d_last'].' '.$p['d_first'].' '.$p['d_mid'];
        $body .= '<h2>' . h(fmt_dt($p['slot_start'])) . ' — ' . h($docFio) . '</h2>';

        $services = DB::all(
            "SELECT s.name, aps.price_at_time
               FROM appointment_service aps JOIN service s ON s.id = aps.service_id
              WHERE aps.appointment_id = :id ORDER BY s.name",
            ['id' => (int) $p['appt_id']]
        );
        if (!empty($services)) {
            $body .= '<table><tr><th>Услуга</th><th class="right">Стоимость</th></tr>';
            foreach ($services as $s) {
                $body .= '<tr><td>' . h($s['name']) . '</td><td class="right">' . h(fmt_price($s['price_at_time'])) . '</td></tr>';
            }
            $body .= '</table>';
        }

        $body .= '<div>' . nl2br(h($p['protocol_text'])) . '</div>';
        if (!empty($p['recommendations'])) {
            $body .= '<div class="muted"><b>Рекомендации:</b> ' . nl2br(h($p['recommendations'])) . '</div>';
        }
    }
}

pdf_render_inline(pdf_document($body, 'Медицинская карта'), 'med_card_' . $patientId . '.pdf');
