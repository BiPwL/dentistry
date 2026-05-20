<?php
/** @var array $user админ */
$users = DB::all(
    "SELECT u.id, u.last_name, u.first_name, u.middle_name, u.email, u.role_id
       FROM user u ORDER BY u.role_id, u.last_name"
);
$roles = DB::all("SELECT id, name FROM role ORDER BY id");

$stat = [
    'patients'   => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=4")['c'],
    'doctors'    => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=3")['c'],
    'services'   => (int) DB::one("SELECT COUNT(*) c FROM service")['c'],
    'articles'   => (int) DB::one("SELECT COUNT(*) c FROM blog_article")['c'],
    'appts'      => (int) DB::one("SELECT COUNT(*) c FROM appointment")['c'],
    'revenue'    => (float) DB::one("SELECT COALESCE(SUM(total_amount),0) s FROM payment")['s'],
];
$byStatus = [];
foreach (DB::all("SELECT status, COUNT(*) c FROM appointment GROUP BY status") as $r) {
    $byStatus[$r['status']] = (int) $r['c'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Панель администратора</h1>
    <form method="post" action="/logout.php" class="m-0"><?= Csrf::field() ?><button class="btn btn-link text-muted">Выйти</button></form>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Пациенты', $stat['patients']],
        ['Врачи', $stat['doctors']],
        ['Услуги', $stat['services']],
        ['Статьи блога', $stat['articles']],
        ['Записей всего', $stat['appts']],
        ['Выручка', fmt_price($stat['revenue'])],
    ];
    foreach ($cards as [$label, $val]): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="clinic-card p-3 text-center h-100">
                <div class="h4 mb-0 text-orange-2"><?= h((string) $val) ?></div>
                <div class="small text-muted"><?= h($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="clinic-card p-3 mb-4">
    <h5 class="mb-3">Записи по статусам</h5>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (APPT_STATUSES as $st): ?>
            <span class="status-badge <?= h(appt_status_class($st)) ?>"><?= h(appt_status_label($st)) ?>: <?= (int) ($byStatus[$st] ?? 0) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<div class="clinic-card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Пользователи и роли</h5>
        <div class="d-flex gap-2">
            <a href="/services.php" class="btn btn-outline-orange btn-sm">Каталог услуг</a>
            <a href="/blog.php" class="btn btn-outline-orange btn-sm">Блог</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>ФИО</th><th>Email</th><th style="width:260px;">Роль</th></tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= h($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['middle_name']) ?></td>
                        <td class="small"><?= h($u['email']) ?></td>
                        <td>
                            <?php if ((int) $u['id'] === (int) $user['id']): ?>
                                <span class="text-muted small">— вы (роль не меняется) —</span>
                            <?php else: ?>
                                <form method="post" action="/api/admin_set_role.php" class="d-flex gap-2">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <select name="role_id" class="form-select form-select-sm">
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?= (int) $r['id'] ?>" <?= $r['id'] == $u['role_id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-orange btn-sm">OK</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
