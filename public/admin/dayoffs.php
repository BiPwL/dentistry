<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/appointment.php';

Auth::requireRole('admin');

$doctors = DB::all("SELECT id, last_name, first_name, middle_name FROM user WHERE role_id = 3 ORDER BY last_name");
$selDoctor = (int) ($_GET['doctor_id'] ?? $_POST['doctor_id'] ?? ($doctors[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $date   = trim((string) ($_POST['off_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $isDoc  = DB::one("SELECT 1 FROM user WHERE id = :id AND role_id = 3", ['id' => $selDoctor]);
        if ($isDoc !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try { DB::exec("INSERT INTO doctor_day_off (doctor_id, off_date, reason) VALUES (:d,:dt,:r)", ['d' => $selDoctor, 'dt' => $date, 'r' => ($reason !== '' ? $reason : null)]); }
            catch (Throwable $e) { /* дубликат — игнор */ }
        }
    } elseif ($action === 'del') {
        DB::exec("DELETE FROM doctor_day_off WHERE id = :id", ['id' => (int) ($_POST['id'] ?? 0)]);
    }
    header('Location: /admin/dayoffs.php?doctor_id=' . $selDoctor);
    exit;
}

$daysOff = $selDoctor > 0
    ? DB::all("SELECT id, off_date, reason FROM doctor_day_off WHERE doctor_id = :d AND off_date >= CURDATE() ORDER BY off_date", ['d' => $selDoctor])
    : [];

$_pageTitle = 'Выходные врачей';
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="clinic-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Выходные врачей</h1>
                <a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
            </div>

            <form method="get" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label class="form-label mb-0 small">Врач</label>
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= $d['id'] == $selDoctor ? 'selected' : '' ?>>
                                <?= h($d['last_name'] . ' ' . $d['first_name'] . ' ' . $d['middle_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selDoctor > 0): ?>
                <form method="post" class="row g-2 align-items-end mb-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="doctor_id" value="<?= (int) $selDoctor ?>">
                    <div class="col-auto"><label class="form-label mb-0 small">Дата</label><input type="date" name="off_date" class="form-control" required></div>
                    <div class="col-auto"><label class="form-label mb-0 small">Причина</label><input type="text" name="reason" class="form-control" placeholder="Отпуск"></div>
                    <div class="col-auto"><button class="btn btn-orange">Добавить</button></div>
                </form>

                <?php if (empty($daysOff)): ?>
                    <p class="text-muted small mb-0">Выходных не запланировано.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($daysOff as $do): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= h(ru_date_label($do['off_date'])) ?><?= $do['reason'] ? ' — ' . h($do['reason']) : '' ?></span>
                                <form method="post" class="m-0">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="action" value="del">
                                    <input type="hidden" name="doctor_id" value="<?= (int) $selDoctor ?>">
                                    <input type="hidden" name="id" value="<?= (int) $do['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm">Удалить</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
