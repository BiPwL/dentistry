<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
$_pageTitle = 'Специализации';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            try { DB::exec("INSERT INTO specialization (name) VALUES (:n)", ['n' => $name]); }
            catch (Throwable $e) { /* дубликат — игнор */ }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0); $name = trim((string)($_POST['name'] ?? ''));
        if ($id > 0 && $name !== '') DB::exec("UPDATE specialization SET name=:n WHERE id=:id", ['n'=>$name,'id'=>$id]);
    } elseif ($action === 'del') {
        $id = (int)($_POST['id'] ?? 0);
        $used = DB::one("SELECT 1 FROM doctor_profile WHERE specialization_id=:id LIMIT 1", ['id'=>$id]);
        if ($used === null) { DB::exec("DELETE FROM specialization WHERE id=:id", ['id'=>$id]); }
        else { $error = 'Нельзя удалить: специализация используется врачом.'; }
    }
    if ($error === null) { header('Location: /admin/specializations.php'); exit; }
}

$rows = DB::all("SELECT s.id, s.name, (SELECT COUNT(*) FROM doctor_profile dp WHERE dp.specialization_id=s.id) AS used FROM specialization s ORDER BY s.name");
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Специализации</h1>
        <a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="d-flex gap-2 mb-3">
        <?= Csrf::field() ?><input type="hidden" name="action" value="add">
        <input type="text" name="name" class="form-control" placeholder="Новая специализация" required>
        <button class="btn btn-orange">Добавить</button>
    </form>
    <table class="table align-middle">
        <thead><tr><th>Название</th><th>Врачей</th><th style="width:230px;"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <input type="text" name="name" class="form-control form-control-sm" value="<?= h($r['name']) ?>">
                </td>
                <td><?= (int)$r['used'] ?></td>
                <td>
                        <button class="btn btn-outline-orange btn-sm">Сохранить</button>
                    </form>
                    <?php if ((int)$r['used'] === 0): ?>
                        <form method="post" class="d-inline" onsubmit="return confirm('Удалить?');">
                            <?= Csrf::field() ?><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm">Удалить</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
