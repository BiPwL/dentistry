<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sanitize.php';

if (!Auth::isAuthenticated()) { header('Location: /login.php'); exit; }
$user = Auth::user();
$uid  = (int) $user['id'];
$apptId = (int) ($_GET['appointment_id'] ?? 0);

$row = DB::one(
    "SELECT a.id, a.slot_start, a.patient_id,
            pay.method, pay.total_amount, pay.paid_at,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN payment pay ON pay.appointment_id = a.id
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id",
    ['id' => $apptId]
);
if ($row === null) { http_response_code(404); echo 'Чек не найден.'; exit; }

$allowed = ($user['role_code'] === 'registrar') || ($user['role_code'] === 'admin') || ($uid === (int) $row['patient_id']);
if (!$allowed) { http_response_code(403); echo 'Нет доступа.'; exit; }

$services = DB::all("SELECT s.name, aps.price_at_time FROM appointment_service aps JOIN service s ON s.id = aps.service_id WHERE aps.appointment_id = :id ORDER BY s.name", ['id'=>$apptId]);
$methodLabel = $row['method'] === 'cash' ? 'Наличными' : 'Картой';

$_pageTitle = 'Чек';
require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="clinic-card p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h1 class="h4 mb-0">Кассовый чек</h1>
                <button class="btn btn-outline-orange btn-sm" onclick="window.print()">Печать</button>
            </div>

            <div class="mb-3 small">
                <div class="fw-semibold"><?= h(SITE_NAME) ?></div>
                <div><?= h(CLINIC_ADDRESS) ?></div>
                <div>тел. <?= h(CLINIC_PHONE) ?></div>
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-4">Пациент</dt><dd class="col-sm-8"><?= h($row['p_last'].' '.$row['p_first'].' '.$row['p_mid']) ?></dd>
                <dt class="col-sm-4">Врач</dt><dd class="col-sm-8"><?= h($row['d_last'].' '.$row['d_first'].' '.$row['d_mid']) ?></dd>
                <dt class="col-sm-4">Дата приёма</dt><dd class="col-sm-8"><?= h(fmt_dt($row['slot_start'])) ?></dd>
                <dt class="col-sm-4">Дата оплаты</dt><dd class="col-sm-8"><?= h(fmt_dt($row['paid_at'])) ?></dd>
                <dt class="col-sm-4">Способ оплаты</dt><dd class="col-sm-8"><?= h($methodLabel) ?></dd>
            </dl>

            <table class="table">
                <thead><tr><th>Услуга</th><th class="text-end">Стоимость</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr><td><?= h($s['name']) ?></td><td class="text-end"><?= h(fmt_price($s['price_at_time'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="fw-bold"><td>Итого</td><td class="text-end"><?= h(fmt_price($row['total_amount'])) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
