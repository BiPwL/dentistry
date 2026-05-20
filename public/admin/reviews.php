<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid();
    if (($_POST['action'] ?? '') === 'del') { DB::exec("DELETE FROM review WHERE id=:id", ['id'=>(int)($_POST['id'] ?? 0)]); }
    header('Location: /admin/reviews.php'); exit;
}
$_pageTitle = 'Отзывы';
$rows = DB::all(
    "SELECT r.id, r.rating, r.body, r.created_at,
            p.last_name p_last, p.first_name p_first,
            d.last_name d_last, d.first_name d_first
       FROM review r JOIN user p ON p.id=r.patient_id JOIN user d ON d.id=r.doctor_id
      ORDER BY r.created_at DESC"
);
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-9"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Отзывы</h1><a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if (empty($rows)): ?><p class="text-muted">Отзывов пока нет.</p><?php else: ?>
        <table class="table align-middle">
            <thead><tr><th>Дата</th><th>Врач</th><th>Пациент</th><th>Оценка</th><th>Текст</th><th></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><?= h(fmt_dt($r['created_at'])) ?></td>
                    <td class="small"><?= h($r['d_last'].' '.mb_substr($r['d_first'],0,1).'.') ?></td>
                    <td class="small"><?= h($r['p_last'].' '.mb_substr($r['p_first'],0,1).'.') ?></td>
                    <td><?= (int)$r['rating'] ?>★</td>
                    <td class="small"><?= h((string)$r['body']) ?></td>
                    <td><form method="post" onsubmit="return confirm('Удалить отзыв?');"><?= Csrf::field() ?><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-danger btn-sm">Удалить</button></form></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    <?php endif; ?>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
