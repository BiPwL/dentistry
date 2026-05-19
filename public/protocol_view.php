<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/appointment.php';

if (!Auth::isAuthenticated()) {
    header('Location: /login.php');
    exit;
}
$user   = Auth::user();
$uid    = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id, a.doctor_id,
            pr.protocol_text, pr.recommendations, pr.created_at,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN protocol pr ON pr.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) {
    http_response_code(404);
    echo 'Протокол не найден.';
    exit;
}
$allowed = ($uid === (int) $row['doctor_id']) || ($uid === (int) $row['patient_id']) || ($user['role_code'] === 'admin');
if (!$allowed) {
    http_response_code(403);
    echo 'Нет доступа.';
    exit;
}

$services = DB::all(
    "SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name",
    ['id' => $apptId]
);

$_pageTitle = 'Протокол приёма';
require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="clinic-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Протокол приёма</h1>
                <button class="btn btn-outline-orange btn-sm" onclick="window.print()">Печать</button>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-3">Клиника</dt><dd class="col-sm-9"><?= h(SITE_NAME) ?></dd>
                <dt class="col-sm-3">Пациент</dt><dd class="col-sm-9"><?= h($row['p_last'] . ' ' . $row['p_first'] . ' ' . $row['p_mid']) ?></dd>
                <dt class="col-sm-3">Врач</dt><dd class="col-sm-9"><?= h($row['d_last'] . ' ' . $row['d_first'] . ' ' . $row['d_mid']) ?></dd>
                <dt class="col-sm-3">Дата приёма</dt><dd class="col-sm-9"><?= h(fmt_dt($row['slot_start'])) ?></dd>
            </dl>

            <?php if (!empty($services)): ?>
                <h6>Оказанные услуги</h6>
                <ul>
                    <?php foreach ($services as $s): ?>
                        <li><?= h($s['name']) ?> — <?= h(fmt_price($s['price_at_time'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h6>Протокол</h6>
            <p style="white-space: pre-wrap;"><?= h($row['protocol_text']) ?></p>

            <?php if (!empty($row['recommendations'])): ?>
                <h6>Рекомендации</h6>
                <p style="white-space: pre-wrap;"><?= h($row['recommendations']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
