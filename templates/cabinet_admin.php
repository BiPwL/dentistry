<?php
/** @var array $user админ */
$users = DB::all(
    "SELECT u.id, u.last_name, u.first_name, u.middle_name, u.email, u.role_id
       FROM user u ORDER BY u.role_id, u.last_name"
);
$roles = DB::all("SELECT id, name FROM role ORDER BY id");

// ── Период статистики: всё время / текущий месяц ──
$period = (($_GET['period'] ?? 'all') === 'month') ? 'month' : 'all';
$pWhere  = '';
$pParams = [];
if ($period === 'month') {
    $pWhere  = ' AND a.slot_start >= :mStart AND a.slot_start < :mEnd';
    $pParams = [
        'mStart' => date('Y-m-01 00:00:00'),
        'mEnd'   => date('Y-m-01 00:00:00', strtotime('first day of next month')),
    ];
}

// Каталожные счётчики — всегда за всё время
$entityStat = [
    'patients' => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=4")['c'],
    'doctors'  => (int) DB::one("SELECT COUNT(*) c FROM user WHERE role_id=3")['c'],
    'services' => (int) DB::one("SELECT COUNT(*) c FROM service")['c'],
    'articles' => (int) DB::one("SELECT COUNT(*) c FROM blog_article")['c'],
];

// Записи по статусам (период)
$byStatus = [];
foreach (DB::all("SELECT a.status, COUNT(*) c FROM appointment a WHERE 1=1 $pWhere GROUP BY a.status", $pParams) as $r) {
    $byStatus[$r['status']] = (int) $r['c'];
}
$apptsTotal = array_sum($byStatus);

// Выручка (период) — из оплат
$revenue = (float) DB::one(
    "SELECT COALESCE(SUM(pay.total_amount),0) s
       FROM payment pay JOIN appointment a ON a.id = pay.appointment_id
      WHERE 1=1 $pWhere",
    $pParams
)['s'];

// Услуги: количество и выручка (период, только оплаченные записи)
$serviceRows = DB::all(
    "SELECT s.name, COUNT(*) cnt, COALESCE(SUM(aps.price_at_time),0) revenue
       FROM appointment_service aps
       JOIN service s ON s.id = aps.service_id
       JOIN appointment a ON a.id = aps.appointment_id
       JOIN payment pay ON pay.appointment_id = a.id
      WHERE 1=1 $pWhere
      GROUP BY s.id, s.name
      ORDER BY revenue DESC",
    $pParams
);

// ── Данные для диаграмм ──
$statusColorMap = ['created'=>'#C8E6C9','confirmed'=>'#B7CEC1','performed'=>'#B7B7AE','completed'=>'#C7C7C7','noshow'=>'#F5B7B7'];
$statusLabels = $statusValues = $statusColors = [];
foreach (APPT_STATUSES as $st) {
    $statusLabels[] = appt_status_label($st);
    $statusValues[] = (int) ($byStatus[$st] ?? 0);
    $statusColors[] = $statusColorMap[$st];
}

$palette = ['#F4A261','#E76F51','#F6BD60','#E9C46A','#F4978E','#FFB4A2','#DDA15E','#BC6C25','#9C6644'];
$svcLabels = $svcRevenue = $svcColors = [];
$i = 0;
foreach ($serviceRows as $sr) {
    $svcLabels[]  = $sr['name'];
    $svcRevenue[] = (float) $sr['revenue'];
    $svcColors[]  = $palette[$i % count($palette)];
    $i++;
}
$svcTotal = array_sum($svcRevenue);

$chartData = [
    'statusLabels' => $statusLabels, 'statusValues' => $statusValues, 'statusColors' => $statusColors,
    'svcLabels' => $svcLabels, 'svcRevenue' => $svcRevenue, 'svcColors' => $svcColors,
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Панель администратора</h1>
    <form method="post" action="/logout.php" class="m-0"><?= Csrf::field() ?><button class="btn btn-link text-muted">Выйти</button></form>
</div>

<div class="btn-group mb-3" role="group">
    <a href="/profile.php?period=all" class="btn btn-sm <?= $period === 'all' ? 'btn-orange' : 'btn-outline-orange' ?>">За всё время</a>
    <a href="/profile.php?period=month" class="btn btn-sm <?= $period === 'month' ? 'btn-orange' : 'btn-outline-orange' ?>">Текущий месяц</a>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Пациенты', $entityStat['patients']],
        ['Врачи', $entityStat['doctors']],
        ['Услуги', $entityStat['services']],
        ['Статьи блога', $entityStat['articles']],
        ['Записей (' . ($period === 'month' ? 'месяц' : 'всего') . ')', $apptsTotal],
        ['Выручка (' . ($period === 'month' ? 'месяц' : 'всего') . ')', fmt_price($revenue)],
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

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="clinic-card p-3 h-100">
            <h5 class="mb-3">Записи по статусам</h5>
            <?php if ($apptsTotal === 0): ?>
                <p class="text-muted small mb-0">Нет записей за период.</p>
            <?php else: ?>
                <div style="height:260px;"><canvas id="statusChart"></canvas></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="clinic-card p-3 h-100">
            <h5 class="mb-3">Услуги: количество и доля в выручке</h5>
            <?php if (empty($serviceRows)): ?>
                <p class="text-muted small mb-0">Нет оплаченных услуг за период.</p>
            <?php else: ?>
                <div class="row align-items-center">
                    <div class="col-md-6"><div style="height:240px;"><canvas id="svcChart"></canvas></div></div>
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Услуга</th><th class="text-end">Кол-во</th><th class="text-end">Выручка</th><th class="text-end">Доля</th></tr></thead>
                            <tbody>
                                <?php foreach ($serviceRows as $idx => $sr): ?>
                                    <?php $share = $svcTotal > 0 ? round((float) $sr['revenue'] / $svcTotal * 100) : 0; ?>
                                    <tr>
                                        <td><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:<?= h($palette[$idx % count($palette)]) ?>"></span><?= h($sr['name']) ?></td>
                                        <td class="text-end"><?= (int) $sr['cnt'] ?></td>
                                        <td class="text-end"><?= h(fmt_price($sr['revenue'])) ?></td>
                                        <td class="text-end"><?= (int) $share ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
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

<script type="application/json" id="admin-chart-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('admin-chart-data');
    if (!el || typeof Chart === 'undefined') return;
    var data = JSON.parse(el.textContent);
    var common = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
    var sc = document.getElementById('statusChart');
    if (sc) new Chart(sc, { type: 'doughnut', data: { labels: data.statusLabels, datasets: [{ data: data.statusValues, backgroundColor: data.statusColors }] }, options: common });
    var vc = document.getElementById('svcChart');
    if (vc && data.svcLabels.length) new Chart(vc, { type: 'doughnut', data: { labels: data.svcLabels, datasets: [{ data: data.svcRevenue, backgroundColor: data.svcColors }] }, options: common });
})();
</script>
