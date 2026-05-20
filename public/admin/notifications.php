<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::requireRole('admin');
$_pageTitle = 'Журнал писем';
$rows = DB::all("SELECT recipient, subject, created_at FROM notification_log ORDER BY created_at DESC LIMIT 200");
require __DIR__ . '/../../templates/header.php';
?>
<div class="row justify-content-center"><div class="col-md-9"><div class="clinic-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Журнал отправленных писем</h1>
        <a href="/profile.php" class="btn btn-outline-orange btn-sm">В кабинет</a>
    </div>
    <?php if (empty($rows)): ?>
        <p class="text-muted">Писем пока нет.</p>
    <?php else: ?>
        <table class="table table-sm">
            <thead><tr><th>Дата</th><th>Получатель</th><th>Тема</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td class="small"><?= h(fmt_dt($r['created_at'])) ?></td><td class="small"><?= h($r['recipient']) ?></td><td><?= h($r['subject']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div></div></div>
<?php require __DIR__ . '/../../templates/footer.php'; ?>
