<?php
declare(strict_types=1);

// Запускать из CLI: php cli/send_reminders.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/sanitize.php';

$rows = DB::all(
    "SELECT a.id, a.slot_start,
            p.email, p.last_name, p.first_name, p.middle_name,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE DATE(a.slot_start) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))
        AND a.status IN ('created','confirmed')
        AND a.reminder_sent_at IS NULL"
);

$sent = 0;
foreach ($rows as $r) {
    $docFio = $r['d_last'].' '.$r['d_first'].' '.$r['d_mid'];
    $subject = 'Напоминание о приёме';
    $bodyText = "Здравствуйте, " . $r['last_name'] . ' ' . $r['first_name'] . "!\n\n"
        . "Напоминаем о приёме в " . SITE_NAME . " завтра, " . fmt_dt($r['slot_start']) . ".\n"
        . "Врач: " . $docFio . ".\n\n"
        . "Если планы изменились — отмените запись в личном кабинете.";
    if (Mailer::send($r['email'], $subject, $bodyText)) {
        DB::exec("UPDATE appointment SET reminder_sent_at = NOW() WHERE id = :id", ['id' => (int) $r['id']]);
        $sent++;
    }
}

echo "Reminders processed: " . count($rows) . ", sent: " . $sent . PHP_EOL;
