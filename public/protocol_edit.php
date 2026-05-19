<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/sanitize.php';
require_once __DIR__ . '/../lib/appointment.php';

Auth::requireRole('doctor');
$docId  = (int) Auth::user()['id'];
$apptId = (int) ($_GET['appointment_id'] ?? $_POST['appointment_id'] ?? 0);

$appt = DB::one(
    "SELECT a.id, a.slot_start,
            p.last_name AS p_last, p.first_name AS p_first, p.middle_name AS p_mid,
            d.last_name AS d_last, d.first_name AS d_first, d.middle_name AS d_mid
       FROM appointment a
       JOIN user p ON p.id = a.patient_id
       JOIN user d ON d.id = a.doctor_id
      WHERE a.id = :id AND a.doctor_id = :d",
    ['id' => $apptId, 'd' => $docId]
);
if ($appt === null) {
    http_response_code(404);
    header('Location: /profile.php');
    exit;
}

$services = DB::all(
    "SELECT s.name, aps.price_at_time
       FROM appointment_service aps JOIN service s ON s.id = aps.service_id
      WHERE aps.appointment_id = :id ORDER BY s.name",
    ['id' => $apptId]
);
$protocol = DB::one('SELECT * FROM protocol WHERE appointment_id = :id', ['id' => $apptId]);

$_pageTitle = 'Протокол приёма';
$errors = [];
$form = [
    'protocol_text'   => $protocol['protocol_text'] ?? '',
    'recommendations' => $protocol['recommendations'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $form['protocol_text']   = trim((string) ($_POST['protocol_text'] ?? ''));
    $form['recommendations'] = trim((string) ($_POST['recommendations'] ?? ''));
    if ($form['protocol_text'] === '') $errors['protocol_text'] = 'Заполните протокол приёма.';

    if (empty($errors)) {
        DB::exec(
            'INSERT INTO protocol (appointment_id, protocol_text, recommendations)
             VALUES (:a, :t, :r)
             ON DUPLICATE KEY UPDATE protocol_text = VALUES(protocol_text), recommendations = VALUES(recommendations)',
            ['a' => $apptId, 't' => $form['protocol_text'], 'r' => $form['recommendations']]
        );
        header('Location: /profile.php?appt=' . $apptId);
        exit;
    }
}

$patientFio = $appt['p_last'] . ' ' . $appt['p_first'] . ' ' . $appt['p_mid'];
$doctorFio  = $appt['d_last'] . ' ' . $appt['d_first'] . ' ' . $appt['d_mid'];

require __DIR__ . '/../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="clinic-card p-4">
            <h1 class="h3 mb-4">Протокол приёма</h1>

            <dl class="row mb-3">
                <dt class="col-sm-3">Пациент</dt><dd class="col-sm-9"><?= h($patientFio) ?></dd>
                <dt class="col-sm-3">Врач</dt><dd class="col-sm-9"><?= h($doctorFio) ?></dd>
                <dt class="col-sm-3">Дата и время</dt><dd class="col-sm-9"><?= h(fmt_dt($appt['slot_start'])) ?></dd>
                <dt class="col-sm-3">Услуги</dt>
                <dd class="col-sm-9">
                    <?php if (empty($services)): ?>
                        <span class="text-muted">не отмечены</span>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach ($services as $s): ?>
                                <li><?= h($s['name']) ?> — <?= h(fmt_price($s['price_at_time'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </dd>
            </dl>

            <form method="post" novalidate>
                <?= Csrf::field() ?>
                <input type="hidden" name="appointment_id" value="<?= (int) $apptId ?>">
                <div class="mb-3">
                    <label class="form-label">Протокол приёма</label>
                    <textarea name="protocol_text" rows="6" class="form-control<?= isset($errors['protocol_text']) ? ' is-invalid' : '' ?>"><?= h($form['protocol_text']) ?></textarea>
                    <?php if (isset($errors['protocol_text'])): ?><div class="invalid-feedback"><?= h($errors['protocol_text']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Рекомендации</label>
                    <textarea name="recommendations" rows="4" class="form-control"><?= h($form['recommendations']) ?></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-orange">Сохранить</button>
                    <a href="/profile.php?appt=<?= (int) $apptId ?>" class="btn btn-outline-orange">Назад к расписанию</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
